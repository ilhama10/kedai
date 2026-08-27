<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemVariant;
use App\Models\OrderItemAddon;
use App\Models\OrderPromo;
use App\Models\Payment;
use App\Models\Branch;
use App\Models\BranchMenu;
use App\Models\Variant;
use App\Models\Addon;
use App\Services\StockService;
use App\Services\PrintService;
use App\Services\AuditService;
use App\Services\PromoService;
use Exception;

class OrderController extends Controller
{
    protected $stockService;
    protected $printService;
    protected $auditService;
    protected $promoService;

    public function __construct(
        StockService $stockService,
        PrintService $printService,
        AuditService $auditService,
        PromoService $promoService
    ) {
        $this->stockService = $stockService;
        $this->printService = $printService;
        $this->auditService = $auditService;
        $this->promoService = $promoService;
    }

    private function checkBranchAccess(Request $request, int $branchId)
    {
        $user = $request->user();
        if ($user->isOwner()) {
            return;
        }
        if ((string)$user->branch_id !== (string)$branchId) {
            abort(response()->json(['message' => 'Forbidden: Cannot access orders for another branch.'], 403));
        }
    }

    public function getOrders(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['orderItems.variants', 'orderItems.addons', 'orderPromos', 'payment', 'table', 'branch']);

        if (!$user->isOwner()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('kitchen_status')) {
            $query->where('kitchen_status', $request->kitchen_status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);
        return response()->json($orders);
    }

    public function getOrderDetails(Request $request, $id)
    {
        $order = Order::with(['orderItems.variants', 'orderItems.addons', 'orderItems.costs', 'orderPromos', 'payment', 'table', 'branch'])->findOrFail($id);
        $this->checkBranchAccess($request, $order->branch_id);
        return response()->json($order);
    }

    /**
     * Cashier Manual Take Away Order Creation.
     */
    public function createTakeawayOrder(Request $request)
    {
        $user = $request->user();
        $branchId = $user->isOwner() ? ($request->branch_id ?? $user->branch_id) : $user->branch_id;

        if (!$branchId) {
            return response()->json(['message' => 'Branch ID is required.'], 422);
        }

        $this->checkBranchAccess($request, $branchId);

        $request->validate([
            'customer_name' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_ids' => 'nullable|array',
            'items.*.addon_ids' => 'nullable|array',
            'items.*.notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'promo_codes' => 'nullable|array',
            'payment_method' => 'required|in:QRIS,CASH',
            'cash_received' => 'required_if:payment_method,CASH|nullable|numeric|min:0',
        ]);

        // 100% Server-side Price Calculation
        $calculatedSubtotal = 0.0;
        $orderItemsData = [];
        $promoItemsPayload = [];

        foreach ($request->items as $itemInput) {
            $bm = BranchMenu::with('menu')
                ->where('branch_id', $branchId)
                ->where('menu_id', $itemInput['menu_id'])
                ->where('status', 'active')
                ->first();

            if (!$bm || $bm->availability === 'sold_out') {
                return response()->json(['message' => "Menu '{$bm->menu->name}' tidak tersedia."], 422);
            }

            $unitPrice = (float)$bm->price;
            $selectedVariants = [];
            if (!empty($itemInput['variant_ids'])) {
                $variants = Variant::whereIn('id', $itemInput['variant_ids'])->where('menu_id', $bm->menu_id)->get();
                foreach ($variants as $v) {
                    $unitPrice += (float)$v->price;
                    $selectedVariants[] = ['variant_id' => $v->id, 'name_snapshot' => $v->name, 'price_snapshot' => (float)$v->price];
                }
            }

            $selectedAddons = [];
            if (!empty($itemInput['addon_ids'])) {
                $addons = Addon::whereIn('id', $itemInput['addon_ids'])->get();
                foreach ($addons as $a) {
                    $unitPrice += (float)$a->price;
                    $selectedAddons[] = ['addon_id' => $a->id, 'name_snapshot' => $a->name, 'price_snapshot' => (float)$a->price];
                }
            }

            $lineTotal = $unitPrice * (int)$itemInput['quantity'];
            $calculatedSubtotal += $lineTotal;

            $orderItemsData[] = [
                'menu_id' => $bm->menu_id,
                'menu_name_snapshot' => $bm->menu->name,
                'unit_price_snapshot' => $unitPrice,
                'quantity' => (int)$itemInput['quantity'],
                'total_price' => $lineTotal,
                'notes' => $itemInput['notes'] ?? null,
                'image_snapshot' => $bm->branch_image ?? $bm->menu->master_image,
                'variants' => $selectedVariants,
                'addons' => $selectedAddons,
            ];

            $promoItemsPayload[] = [
                'menu_id' => $bm->menu_id,
                'category_id' => $bm->menu->category_id,
                'line_total' => $lineTotal,
            ];
        }

        $promoRes = $this->promoService->calculatePromos($branchId, $calculatedSubtotal, $promoItemsPayload, $request->promo_codes ?? []);
        $discountAmount = $promoRes['total_discount'];
        $taxRate = 10.0; // 10% Tax Rate
        $dutiableAmount = max(0.0, $calculatedSubtotal - $discountAmount);
        $taxAmount = round($dutiableAmount * 0.10, 2);
        $totalAmount = round($dutiableAmount + $taxAmount, 2);

        $rawToken = Str::random(60);
        $tokenHash = hash('sha256', $rawToken);

        $branchCode = Branch::find($branchId)->code;
        $dateStr = now()->format('Ymd');
        $seq = Order::where('branch_id', $branchId)->whereDate('created_at', now()->toDateString())->count() + 1;
        $orderNumber = sprintf("%s-%s-%04d", $branchCode, $dateStr, $seq);

        $order = Order::create([
            'order_number' => $orderNumber,
            'customer_access_token_hash' => $tokenHash,
            'branch_id' => $branchId,
            'table_id' => null, // TAKE_AWAY
            'customer_name' => $request->customer_name,
            'order_type' => 'TAKE_AWAY',
            'subtotal' => $calculatedSubtotal,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_status' => 'UNPAID',
            'kitchen_status' => 'NOT_SENT',
            'notes' => $request->notes ?? null,
        ]);

        foreach ($orderItemsData as $itemData) {
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'menu_id' => $itemData['menu_id'],
                'menu_name_snapshot' => $itemData['menu_name_snapshot'],
                'unit_price_snapshot' => $itemData['unit_price_snapshot'],
                'cost_amount_snapshot' => 0.0,
                'quantity' => $itemData['quantity'],
                'total_price' => $itemData['total_price'],
                'notes' => $itemData['notes'],
                'image_snapshot' => $itemData['image_snapshot'],
            ]);

            foreach ($itemData['variants'] as $v) {
                OrderItemVariant::create(['order_item_id' => $orderItem->id, 'variant_id' => $v['variant_id'], 'name_snapshot' => $v['name_snapshot'], 'price_snapshot' => $v['price_snapshot']]);
            }
            foreach ($itemData['addons'] as $a) {
                OrderItemAddon::create(['order_item_id' => $orderItem->id, 'addon_id' => $a['addon_id'], 'name_snapshot' => $a['name_snapshot'], 'price_snapshot' => $a['price_snapshot']]);
            }
        }

        foreach ($promoRes['applied_promos'] as $p) {
            OrderPromo::create(['order_id' => $order->id, 'promo_id' => $p['promo_id'], 'promo_name_snapshot' => $p['promo_name'], 'discount_amount' => $p['discount_amount']]);
        }

        // Cash Received calculation
        $cashReceived = $request->payment_method === 'CASH' ? (float)$request->cash_received : null;
        $cashChange = ($request->payment_method === 'CASH' && $cashReceived !== null) ? max(0.0, $cashReceived - $totalAmount) : null;

        Payment::create([
            'order_id' => $order->id,
            'method' => $request->payment_method,
            'status' => 'UNPAID',
            'amount' => $totalAmount,
            'cash_received' => $cashReceived,
            'cash_change' => $cashChange,
        ]);

        return response()->json($order->load(['orderItems.variants', 'orderItems.addons', 'orderPromos', 'payment']), 201);
    }

    /**
     * Final Cashier Payment Confirmation — Executed inside Atomic DB Transaction.
     */
    public function confirmPaid(Request $request, $id)
    {
        $user = $request->user();

        // Strict Role Rule: ONLY role 'kasir' can confirm payments! Admin and CEO/Owner cannot confirm payment.
        if (!$user->isKasir()) {
            return response()->json([
                'message' => 'Forbidden: Hanya Kasir yang berhak mengonfirmasi pembayaran pesanan.'
            ], 403);
        }

        $order = Order::findOrFail($id);
        $this->checkBranchAccess($request, $order->branch_id);

        $request->validate([
            'cash_received' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // 1. SELECT FOR UPDATE on order
            $orderLocked = Order::where('id', $order->id)->lockForUpdate()->first();

            // 2. State machine guard: Strictly reject mutation if already PAID
            if ($orderLocked->payment_status === 'PAID') {
                DB::rollBack();
                return response()->json(['message' => 'Pesanan ini sudah lunas (PAID) dan terkunci total.'], 409);
            }

            // 3. Validate Payment Record & Recalculate Cash Change server-side
            $payment = Payment::where('order_id', $orderLocked->id)->first();
            if (!$payment) {
                DB::rollBack();
                return response()->json(['message' => 'Informasi pembayaran tidak ditemukan.'], 422);
            }

            if ($payment->method === 'CASH') {
                $cashReceived = $request->cash_received ?? $payment->cash_received;
                if ($cashReceived === null || $cashReceived < $orderLocked->total_amount) {
                    DB::rollBack();
                    return response()->json(['message' => "Uang tunai kurang. Total: Rp " . number_format($orderLocked->total_amount, 0, ',', '.') . ", Diterima: Rp " . number_format((float)$cashReceived, 0, ',', '.')], 422);
                }

                $payment->cash_received = $cashReceived;
                $payment->cash_change = $cashReceived - $orderLocked->total_amount;
            }

            // 4. Validate & Deduct Inventory Stock + Calculate Historical COGS (Atomic)
            $this->stockService->deductStockAndSnapshotCogs($orderLocked);

            // 5. Update Payment & Order status to PAID
            $payment->status = 'PAID';
            $payment->verified_by = auth()->id();
            $payment->verified_at = now();
            $payment->save();

            $orderLocked->payment_status = 'PAID';
            $orderLocked->kitchen_status = 'WAITING';
            $orderLocked->save();

            // 6. Record Audit Log
            $this->auditService->log(
                'PAYMENT_CONFIRMED',
                'Order',
                $orderLocked->id,
                ['payment_status' => 'UNPAID'],
                ['payment_status' => 'PAID', 'verified_by' => auth()->id()],
                $orderLocked->branch_id
            );

            // 7. Create Automatic Kitchen Ticket Print Job
            $printJob = $this->printService->createAutomaticKitchenTicket($orderLocked);

            DB::commit();

            // AFTER COMMIT SIDE EFFECTS (Events, WebSockets, KDS Notifications)
            DB::afterCommit(function () use ($orderLocked) {
                // Dispatch realtime events
            });

            return response()->json([
                'message' => 'Pesanan berhasil dikonfirmasi LUNAS.',
                'order' => $orderLocked->fresh(['payment', 'orderItems.costs']),
                'print_job' => $printJob,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses pembayaran: ' . $e->getMessage(),
            ], 422);
        }
    }
}
