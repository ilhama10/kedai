<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Branch;
use App\Models\BranchMenu;
use App\Models\Category;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemVariant;
use App\Models\OrderItemAddon;
use App\Models\OrderPromo;
use App\Models\Payment;
use App\Models\Variant;
use App\Models\Addon;
use App\Services\PromoService;

class PublicMenuController extends Controller
{
    protected $promoService;

    public function __construct(PromoService $promoService)
    {
        $this->promoService = $promoService;
    }

    public function getBranchMenu($branchId)
    {
        $branch = Branch::where('status', 'ACTIVE')->findOrFail($branchId);

        $categories = Category::where('status', 'ACTIVE')
            ->orderBy('sort_order', 'asc')
            ->get();

        $branchMenus = BranchMenu::with(['menu.category', 'menu.variants', 'menu.addons'])
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->get();

        $promos = \App\Models\Promo::where('branch_id', $branch->id)
            ->where('status', 'ACTIVE')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();

        $formattedMenus = $branchMenus->map(function ($bm) {
            $menu = $bm->menu;
            return [
                'id' => $bm->id,
                'menu_id' => $menu->id,
                'category_id' => $menu->category_id,
                'category_name' => $menu->category ? $menu->category->name : '',
                'name' => $menu->name,
                'description' => $menu->description,
                'price' => (float)$bm->price,
                'availability' => $bm->availability,
                'is_featured' => (bool)$bm->is_featured,
                'image_url' => $bm->branch_image ? asset('storage/' . $bm->branch_image) : ($menu->master_image ? asset('storage/' . $menu->master_image) : null),
                'variants' => $menu->variants->where('status', 'ACTIVE')->map(fn($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price' => (float)$v->price,
                ])->values(),
                'addons' => $menu->addons->where('status', 'ACTIVE')->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'price' => (float)$a->price,
                    'image_url' => $a->image ? asset('storage/' . $a->image) : null,
                ])->values(),
            ];
        });

        return response()->json([
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'qris_image_url' => $branch->qris_image ? asset('storage/' . $branch->qris_image) : null,
            ],
            'categories' => $categories,
            'menus' => $formattedMenus,
            'promos' => $promos,
        ]);
    }

    public function verifyTableToken($token)
    {
        $table = Table::with('branch')
            ->where('qr_code_token', $token)
            ->where('status', 'available')
            ->firstOrFail();

        return response()->json([
            'valid' => true,
            'branch' => $table->branch,
            'table' => [
                'id' => $table->id,
                'table_number' => $table->table_number,
            ],
        ]);
    }

    public function getBranchTables($branchId)
    {
        $branch = Branch::where('status', 'ACTIVE')->findOrFail($branchId);
        $tables = Table::where('branch_id', $branch->id)
            ->where('status', 'available')
            ->orderBy('table_number', 'asc')
            ->get(['id', 'table_number', 'status', 'capacity']);

        return response()->json($tables);
    }

    public function verifyTakeawayToken($token)
    {
        // Token format for takeaway: TAKEAWAY-{branch_code}-{hash}
        $parts = explode('-', $token);
        if (count($parts) < 2 || $parts[0] !== 'TAKEAWAY') {
            return response()->json(['message' => 'Invalid Take Away token.'], 404);
        }

        $branchCode = $parts[1];
        $branch = Branch::where('code', $branchCode)->where('status', 'ACTIVE')->firstOrFail();

        return response()->json([
            'valid' => true,
            'order_type' => 'TAKE_AWAY',
            'branch' => $branch,
        ]);
    }

    public function createOrder(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'order_type' => 'required|in:DINE_IN,TAKE_AWAY',
            'table_id' => 'required_if:order_type,DINE_IN|nullable|exists:tables,id',
            'customer_name' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_ids' => 'nullable|array',
            'items.*.addon_ids' => 'nullable|array',
            'items.*.notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'promo_codes' => 'nullable|array',
        ]);

        $branchId = $request->branch_id;

        // Verify table belongs to branch if DINE_IN
        if ($request->order_type === 'DINE_IN') {
            $table = Table::where('id', $request->table_id)
                ->where('branch_id', $branchId)
                ->first();

            if (!$table) {
                return response()->json(['message' => 'Meja tidak ditemukan pada cabang ini.'], 422);
            }
        }

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

            if (!$bm) {
                return response()->json(['message' => 'Menu tidak aktif pada cabang ini.'], 422);
            }

            if ($bm->availability === 'sold_out') {
                return response()->json(['message' => "Menu '{$bm->menu->name}' sedang habis (SOLD OUT)."], 422);
            }

            $unitPrice = (float)$bm->price;
            $selectedVariants = [];
            if (!empty($itemInput['variant_ids'])) {
                $variants = Variant::whereIn('id', $itemInput['variant_ids'])
                    ->where('menu_id', $bm->menu_id)
                    ->get();

                foreach ($variants as $v) {
                    $unitPrice += (float)$v->price;
                    $selectedVariants[] = [
                        'variant_id' => $v->id,
                        'name_snapshot' => $v->name,
                        'price_snapshot' => (float)$v->price,
                    ];
                }
            }

            $selectedAddons = [];
            if (!empty($itemInput['addon_ids'])) {
                $addons = Addon::whereIn('id', $itemInput['addon_ids'])->get();
                foreach ($addons as $a) {
                    $unitPrice += (float)$a->price;
                    $selectedAddons[] = [
                        'addon_id' => $a->id,
                        'name_snapshot' => $a->name,
                        'price_snapshot' => (float)$a->price,
                    ];
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

        // Calculate Promos Server-Side
        $promoRes = $this->promoService->calculatePromos(
            $branchId,
            $calculatedSubtotal,
            $promoItemsPayload,
            $request->promo_codes ?? []
        );

        $discountAmount = $promoRes['total_discount'];
        $taxRate = 10.0; // 10% Tax Rate (PB1)
        $dutiableAmount = max(0.0, $calculatedSubtotal - $discountAmount);
        $taxAmount = round($dutiableAmount * 0.10, 2);
        $totalAmount = round($dutiableAmount + $taxAmount, 2);

        // Security: Cryptographically Secure Customer Access Token
        $rawAccessToken = Str::random(60);
        $tokenHash = hash('sha256', $rawAccessToken);

        // Generate Order Number
        $branchCode = Branch::find($branchId)->code;
        $order = Order::createWithUniqueOrderNumber([
            'customer_access_token_hash' => $tokenHash,
            'branch_id' => $branchId,
            'table_id' => $request->order_type === 'DINE_IN' ? $request->table_id : null,
            'customer_name' => $request->customer_name,
            'order_type' => $request->order_type,
            'subtotal' => $calculatedSubtotal,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_status' => 'UNPAID',
            'kitchen_status' => 'NOT_SENT',
            'notes' => $request->notes ?? null,
        ], $branchCode);

        // Insert Items, Variants & Addons
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
                OrderItemVariant::create([
                    'order_item_id' => $orderItem->id,
                    'variant_id' => $v['variant_id'],
                    'name_snapshot' => $v['name_snapshot'],
                    'price_snapshot' => $v['price_snapshot'],
                ]);
            }

            foreach ($itemData['addons'] as $a) {
                OrderItemAddon::create([
                    'order_item_id' => $orderItem->id,
                    'addon_id' => $a['addon_id'],
                    'name_snapshot' => $a['name_snapshot'],
                    'price_snapshot' => $a['price_snapshot'],
                ]);
            }
        }

        // Insert Order Promos
        foreach ($promoRes['applied_promos'] as $p) {
            OrderPromo::create([
                'order_id' => $order->id,
                'promo_id' => $p['promo_id'],
                'promo_name_snapshot' => $p['promo_name'],
                'discount_amount' => $p['discount_amount'],
            ]);
        }

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'access_token' => $rawAccessToken, // Returned raw to customer
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'order_type' => $order->order_type,
                'subtotal' => $order->subtotal,
                'discount_amount' => $order->discount_amount,
                'total_amount' => $order->total_amount,
                'payment_status' => $order->payment_status,
                'customer_status' => $order->derived_customer_status,
            ],
        ], 201);
    }

    public function submitPayment(Request $request, $accessToken)
    {
        $tokenHash = hash('sha256', $accessToken);
        $order = Order::where('customer_access_token_hash', $tokenHash)->firstOrFail();

        if ($order->payment_status === 'PAID') {
            return response()->json(['message' => 'Pesanan ini sudah lunas (PAID).'], 422);
        }

        $request->validate([
            'method' => 'required|in:QRIS,CASH',
            'verification_method' => 'required_if:method,QRIS|in:upload_proof,show_to_cashier',
            'proof_image' => 'nullable|required_if:verification_method,upload_proof|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($request->method === 'QRIS') {
            $proofPath = null;
            if ($request->verification_method === 'upload_proof' && $request->hasFile('proof_image')) {
                $proofPath = $request->file('proof_image')->store('payment_proofs', 'public');
            }

            Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'method' => 'QRIS',
                    'status' => 'PENDING_VERIFICATION',
                    'amount' => $order->total_amount,
                    'proof_image' => $proofPath,
                    'verification_method' => $request->verification_method,
                ]
            );

            // Update order state to PENDING_VERIFICATION (Customer NEVER sets PAID)
            $order->payment_status = 'PENDING_VERIFICATION';
            $order->save();
        } else {
            // CASH: Customer creates CASH order -> remains UNPAID until Cashier confirms
            Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'method' => 'CASH',
                    'status' => 'UNPAID',
                    'amount' => $order->total_amount,
                ]
            );
        }

        return response()->json([
            'message' => 'Informasi pembayaran berhasil dikirim.',
            'payment_status' => $order->payment_status,
            'customer_status' => $order->derived_customer_status,
        ]);
    }

    public function getOrderStatus($accessToken)
    {
        $tokenHash = hash('sha256', $accessToken);
        $order = Order::with(['orderItems.variants', 'orderItems.addons', 'orderPromos', 'payment', 'table', 'branch'])
            ->where('customer_access_token_hash', $tokenHash)
            ->firstOrFail();

        return response()->json([
            'order' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'branch_name' => $order->branch->name,
                'order_type' => $order->order_type,
                'table_number' => $order->table ? $order->table->table_number : null,
                'subtotal' => (float)$order->subtotal,
                'discount_amount' => (float)$order->discount_amount,
                'total_amount' => (float)$order->total_amount,
                'payment_status' => $order->payment_status,
                'kitchen_status' => $order->kitchen_status,
                'customer_status' => $order->derived_customer_status,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'items' => $order->orderItems->map(fn($item) => [
                    'name' => $item->menu_name_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price' => (float)$item->unit_price_snapshot,
                    'total_price' => (float)$item->total_price,
                    'variants' => $item->variants->pluck('name_snapshot')->toArray(),
                    'addons' => $item->addons->pluck('name_snapshot')->toArray(),
                    'notes' => $item->notes,
                ]),
                'promos' => $order->orderPromos->pluck('promo_name_snapshot')->toArray(),
            ],
        ]);
    }
}
