<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Inventory;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\OrderItemCost;
use Exception;

class StockService
{
    /**
     * Deduct stock for a PAID order atomically inside an active DB transaction.
     * Throws Exception if stock is insufficient, causing full transaction rollback.
     */
    public function deductStockAndSnapshotCogs(Order $order): void
    {
        $requirements = []; // inventory_id => ['required_qty' => float, 'items' => array]

        // 1. Aggregate required ingredients across all order items, variants, and addons
        foreach ($order->orderItems as $item) {
            $itemCogsTotal = 0.0;

            // Fetch Base Menu Recipes
            $menuRecipes = Recipe::where('branch_id', $order->branch_id)
                ->where('menu_id', $item->menu_id)
                ->get();

            foreach ($menuRecipes as $recipe) {
                $qty = $recipe->quantity * $item->quantity;
                $this->addRequirement($requirements, $recipe->inventory_id, $qty, $item, 'menu');
            }

            // Fetch Selected Variant Recipes
            foreach ($item->variants as $itemVariant) {
                if ($itemVariant->variant_id) {
                    $variantRecipes = Recipe::where('branch_id', $order->branch_id)
                        ->where('variant_id', $itemVariant->variant_id)
                        ->get();

                    foreach ($variantRecipes as $recipe) {
                        $qty = $recipe->quantity * $item->quantity;
                        $this->addRequirement($requirements, $recipe->inventory_id, $qty, $item, 'variant');
                    }
                }
            }

            // Fetch Selected Addon Recipes
            foreach ($item->addons as $itemAddon) {
                if ($itemAddon->addon_id) {
                    $addonRecipes = Recipe::where('branch_id', $order->branch_id)
                        ->where('addon_id', $itemAddon->addon_id)
                        ->get();

                    foreach ($addonRecipes as $recipe) {
                        $qty = $recipe->quantity * $item->quantity;
                        $this->addRequirement($requirements, $recipe->inventory_id, $qty, $item, 'addon');
                    }
                }
            }
        }

        if (empty($requirements)) {
            return;
        }

        // 2. Lock inventory rows FOR UPDATE & validate stock availability
        $inventoryIds = array_keys($requirements);
        $inventories = Inventory::whereIn('id', $inventoryIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requirements as $invId => $req) {
            if (!isset($inventories[$invId])) {
                throw new Exception("Inventory item ID {$invId} not found.");
            }

            $inventory = $inventories[$invId];
            if ($inventory->stock < $req['required_qty']) {
                throw new Exception("Stok tidak mencukupi untuk bahan '{$inventory->item_name}'. Dibutuhkan: {$req['required_qty']} {$inventory->unit}, Stok tersedia: {$inventory->stock} {$inventory->unit}.");
            }
        }

        // 3. Deduct stock, record immutable stock_movements, and create order_item_costs snapshots
        foreach ($requirements as $invId => $req) {
            $inventory = $inventories[$invId];
            $stockBefore = (float)$inventory->stock;
            $qtyDeduct = (float)$req['required_qty'];
            $stockAfter = $stockBefore - $qtyDeduct;

            // Deduct stock
            $inventory->stock = $stockAfter;
            $inventory->save();

            // Create Stock Movement Ledger
            StockMovement::create([
                'branch_id' => $order->branch_id,
                'inventory_id' => $invId,
                'type' => 'ORDER_DEDUCTION',
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'quantity_change' => -$qtyDeduct,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'created_by' => auth()->id(),
                'notes' => "Pengurangan stok otomatis untuk Order #{$order->order_number}",
            ]);

            // Distribute COGS to individual order items
            foreach ($req['allocations'] as $alloc) {
                $orderItem = $alloc['order_item'];
                $consumedQty = $alloc['quantity'];
                $costPerUnit = (float)$inventory->cost_per_unit;
                $costAmount = $consumedQty * $costPerUnit;

                OrderItemCost::create([
                    'order_item_id' => $orderItem->id,
                    'inventory_id' => $inventory->id,
                    'inventory_name_snapshot' => $inventory->item_name,
                    'quantity_consumed' => $consumedQty,
                    'unit_snapshot' => $inventory->unit,
                    'cost_per_unit_snapshot' => $costPerUnit,
                    'cost_amount' => $costAmount,
                ]);

                // Update order_items.cost_amount_snapshot
                $orderItem->cost_amount_snapshot = (float)$orderItem->cost_amount_snapshot + $costAmount;
                $orderItem->save();
            }
        }
    }

    private function addRequirement(array &$requirements, int $invId, float $qty, $orderItem, string $targetType): void
    {
        if (!isset($requirements[$invId])) {
            $requirements[$invId] = [
                'required_qty' => 0.0,
                'allocations' => [],
            ];
        }

        $requirements[$invId]['required_qty'] += $qty;
        $requirements[$invId]['allocations'][] = [
            'order_item' => $orderItem,
            'quantity' => $qty,
            'target_type' => $targetType,
        ];
    }
}
