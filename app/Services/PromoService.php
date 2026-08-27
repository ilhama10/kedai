<?php

namespace App\Services;

use App\Models\Promo;

class PromoService
{
    /**
     * Calculate applicable promos deterministically for a branch order.
     *
     * @param int $branchId
     * @param float $subtotal
     * @param array $items Array of items with ['menu_id' => int, 'category_id' => int, 'line_total' => float]
     * @param array $appliedPromoCodes Optional promo codes passed
     * @return array ['total_discount' => float, 'applied_promos' => array]
     */
    public function calculatePromos(int $branchId, float $subtotal, array $items, array $appliedPromoCodes = []): array
    {
        $now = now();
        $query = Promo::where('branch_id', $branchId)
            ->where('status', 'ACTIVE')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->where('min_purchase', '<=', $subtotal)
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'asc');

        if (!empty($appliedPromoCodes)) {
            $query->whereIn('code', $appliedPromoCodes);
        }

        $eligiblePromos = $query->get();

        $totalDiscount = 0.0;
        $appliedPromos = [];
        $hasNonStackableApplied = false;

        foreach ($eligiblePromos as $promo) {
            // Check stackability rules
            if ($hasNonStackableApplied && !$promo->is_stackable) {
                continue;
            }

            // Calculate discount amount based on promo type & targeting
            $discount = 0.0;

            if ($promo->menus()->count() > 0 || $promo->categories()->count() > 0) {
                // Item/Category specific promo
                $targetedMenuIds = $promo->menus()->pluck('menus.id')->toArray();
                $targetedCategoryIds = $promo->categories()->pluck('categories.id')->toArray();

                $eligibleItemSubtotal = 0.0;
                foreach ($items as $item) {
                    $menuId = $item['menu_id'] ?? null;
                    $categoryId = $item['category_id'] ?? null;

                    if (($menuId && in_array($menuId, $targetedMenuIds)) || ($categoryId && in_array($categoryId, $targetedCategoryIds))) {
                        $eligibleItemSubtotal += $item['line_total'];
                    }
                }

                if ($eligibleItemSubtotal > 0) {
                    if ($promo->discount_type === 'percentage') {
                        $discount = ($eligibleItemSubtotal * $promo->discount_value) / 100;
                    } else {
                        $discount = min($promo->discount_value, $eligibleItemSubtotal);
                    }
                }
            } else {
                // Transaction-wide promo
                if ($promo->discount_type === 'percentage') {
                    $discount = ($subtotal * $promo->discount_value) / 100;
                } else {
                    $discount = $promo->discount_value;
                }
            }

            // Apply max_discount cap if specified
            if ($promo->max_discount && $promo->max_discount > 0) {
                $discount = min($discount, (float)$promo->max_discount);
            }

            // Prevent total discount from exceeding subtotal
            $discount = min($discount, max(0.0, $subtotal - $totalDiscount));

            if ($discount > 0) {
                $totalDiscount += $discount;
                $appliedPromos[] = [
                    'promo_id' => $promo->id,
                    'promo_name' => $promo->name,
                    'code' => $promo->code,
                    'discount_amount' => $discount,
                ];

                if (!$promo->is_stackable) {
                    $hasNonStackableApplied = true;
                }
            }
        }

        return [
            'total_discount' => round($totalDiscount, 2),
            'applied_promos' => $appliedPromos,
        ];
    }
}
