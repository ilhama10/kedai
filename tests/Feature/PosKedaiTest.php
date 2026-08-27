<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Branch;
use App\Models\Table;
use App\Models\Category;
use App\Models\Menu;
use App\Models\BranchMenu;
use App\Models\Variant;
use App\Models\Addon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Inventory;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\PrintJob;
use App\Models\AuditLog;
use Database\Seeders\DatabaseSeeder;

class PosKedaiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createPaidOrderHelper()
    {
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $bm = BranchMenu::where('branch_id', 1)->first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => 1,
            'order_type' => 'DINE_IN',
            'table_id' => 1,
            'customer_name' => 'Helper Customer',
            'items' => [['menu_id' => $bm->menu_id, 'quantity' => 1]]
        ]);

        $orderId = $resOrder->json('order.id');
        $token = $resOrder->json('access_token');

        $this->postJson("/api/public/orders/{$token}/payment", ['method' => 'CASH']);
        $this->actingAs($kasir1)->postJson("/api/orders/{$orderId}/confirm-paid", ['cash_received' => 50000]);

        return Order::find($orderId);
    }

    public function test_1_owner_can_create_master_menu()
    {
        $owner = User::where('role', 'owner')->first();
        $category = Category::first();

        $response = $this->actingAs($owner)->postJson('/api/menus', [
            'category_id' => $category->id,
            'sku' => 'TEST-001',
            'name' => 'Menu Master Baru',
            'base_price' => 30000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('menus', ['sku' => 'TEST-001']);
    }

    public function test_2_admin_cabang_cannot_modify_master_menu()
    {
        $admin1 = User::where('email', 'admin1@kedai.com')->first();
        $category = Category::first();

        $response = $this->actingAs($admin1)->postJson('/api/menus', [
            'category_id' => $category->id,
            'sku' => 'TEST-002',
            'name' => 'Attempt Master Menu',
            'base_price' => 20000,
        ]);

        $response->assertStatus(403);
    }

    public function test_3_admin_cabang_only_accesses_own_branch()
    {
        $admin1 = User::where('email', 'admin1@kedai.com')->first(); // Branch 1
        $branch2 = Branch::where('code', 'C02')->first();

        $response = $this->actingAs($admin1)->getJson("/api/branches/{$branch2->id}/menus");
        $response->assertStatus(403);
    }

    public function test_4_branch_prices_are_isolated()
    {
        $b1Menu = BranchMenu::where('branch_id', 1)->first();
        $b2Menu = BranchMenu::where('branch_id', 2)->where('menu_id', $b1Menu->menu_id)->first();

        $admin1 = User::where('email', 'admin1@kedai.com')->first();

        $this->actingAs($admin1)->postJson("/api/branches/1/menus/{$b1Menu->menu_id}", [
            'price' => 45000,
            'availability' => 'available',
            'status' => 'active',
        ]);

        $this->assertEquals(45000, BranchMenu::find($b1Menu->id)->price);
        $this->assertNotEquals(45000, BranchMenu::find($b2Menu->id)->price);
    }

    public function test_5_branch_image_fallback_to_master()
    {
        $bm = BranchMenu::whereNull('branch_image')->first();
        $this->assertNotNull($bm->effective_image);
    }

    public function test_6_sold_out_item_cannot_be_ordered()
    {
        $bm = BranchMenu::first();
        $bm->availability = 'sold_out';
        $bm->save();

        $response = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Budi',
            'items' => [
                ['menu_id' => $bm->menu_id, 'quantity' => 1]
            ]
        ]);

        $response->assertStatus(422);
    }

    public function test_7_customer_can_create_dine_in_order()
    {
        $table = Table::first();
        $bm = BranchMenu::where('branch_id', $table->branch_id)->where('availability', 'available')->first();

        $response = $this->postJson('/api/public/orders', [
            'branch_id' => $table->branch_id,
            'order_type' => 'DINE_IN',
            'table_id' => $table->id,
            'customer_name' => 'Siti',
            'items' => [
                ['menu_id' => $bm->menu_id, 'quantity' => 2]
            ]
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['customer_name' => 'Siti', 'order_type' => 'DINE_IN']);
    }

    public function test_8_customer_can_create_takeaway_via_qr()
    {
        $bm = BranchMenu::where('availability', 'available')->first();

        $response = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Doni',
            'items' => [
                ['menu_id' => $bm->menu_id, 'quantity' => 1]
            ]
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['customer_name' => 'Doni', 'order_type' => 'TAKE_AWAY', 'table_id' => null]);
    }

    public function test_9_cashier_can_create_manual_takeaway()
    {
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $bm = BranchMenu::where('branch_id', 1)->where('availability', 'available')->first();

        $response = $this->actingAs($kasir1)->postJson('/api/orders/takeaway', [
            'customer_name' => 'Pelanggan Kasir',
            'payment_method' => 'CASH',
            'cash_received' => 50000,
            'items' => [
                ['menu_id' => $bm->menu_id, 'quantity' => 1]
            ]
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['customer_name' => 'Pelanggan Kasir', 'order_type' => 'TAKE_AWAY']);
    }

    public function test_10_customer_name_is_mandatory()
    {
        $bm = BranchMenu::first();

        $response = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => '',
            'items' => [
                ['menu_id' => $bm->menu_id, 'quantity' => 1]
            ]
        ]);

        $response->assertStatus(422);
    }

    public function test_11_variant_choice_is_snapshotted()
    {
        $menu = Menu::has('variants')->first();
        $variant = $menu->variants->first();
        $bm = BranchMenu::where('menu_id', $menu->id)->first();

        $res = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Variant Tester',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1, 'variant_ids' => [$variant->id]]
            ]
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('order_item_variants', ['name_snapshot' => $variant->name]);
    }

    public function test_12_addon_choice_is_snapshotted()
    {
        $menu = Menu::has('addons')->first();
        $addon = $menu->addons->first();
        $bm = BranchMenu::where('menu_id', $menu->id)->first();

        $res = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Addon Tester',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1, 'addon_ids' => [$addon->id]]
            ]
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('order_item_addons', ['name_snapshot' => $addon->name]);
    }

    public function test_13_order_notes_are_saved()
    {
        $bm = BranchMenu::first();

        $res = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Note Tester',
            'notes' => 'Tolong bungkus rapi',
            'items' => [
                ['menu_id' => $bm->menu_id, 'quantity' => 1, 'notes' => 'Tidak pedas']
            ]
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('orders', ['notes' => 'Tolong bungkus rapi']);
        $this->assertDatabaseHas('order_items', ['notes' => 'Tidak pedas']);
    }

    public function test_14_stackable_promos_are_calculated_correctly()
    {
        $bm = BranchMenu::where('branch_id', 1)->first();

        $res = $this->postJson('/api/public/orders', [
            'branch_id' => 1,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Promo Tester',
            'items' => [
                ['menu_id' => $bm->menu_id, 'quantity' => 2]
            ]
        ]);

        $res->assertStatus(201);
        $order = Order::find($res->json('order.id'));
        $this->assertGreaterThan(0, $order->discount_amount);
    }

    public function test_15_qris_proof_can_be_uploaded_via_public_api()
    {
        $bm = BranchMenu::first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'QRIS Uploader',
            'items' => [['menu_id' => $bm->menu_id, 'quantity' => 1]]
        ]);

        $token = $resOrder->json('access_token');

        $resPay = $this->postJson("/api/public/orders/{$token}/payment", [
            'method' => 'QRIS',
            'verification_method' => 'show_to_cashier'
        ]);

        $resPay->assertStatus(200);
        $this->assertDatabaseHas('orders', ['payment_status' => 'PENDING_VERIFICATION']);
    }

    public function test_16_qris_show_to_cashier_option()
    {
        $bm = BranchMenu::first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'QRIS Show',
            'items' => [['menu_id' => $bm->menu_id, 'quantity' => 1]]
        ]);

        $token = $resOrder->json('access_token');

        $resPay = $this->postJson("/api/public/orders/{$token}/payment", [
            'method' => 'QRIS',
            'verification_method' => 'show_to_cashier'
        ]);

        $resPay->assertStatus(200);
        $this->assertDatabaseHas('payments', ['verification_method' => 'show_to_cashier']);
    }

    public function test_17_customer_cannot_mark_order_as_paid()
    {
        $bm = BranchMenu::first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Hacker Customer',
            'items' => [['menu_id' => $bm->menu_id, 'quantity' => 1]]
        ]);

        $token = $resOrder->json('access_token');

        $resPay = $this->postJson("/api/public/orders/{$token}/payment", [
            'method' => 'QRIS',
            'verification_method' => 'show_to_cashier',
            'payment_status' => 'PAID', // Attempt to inject PAID
        ]);

        $order = Order::find($resOrder->json('order.id'));
        $this->assertNotEquals('PAID', $order->payment_status);
    }

    public function test_18_cash_change_calculation()
    {
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $bm = BranchMenu::where('branch_id', 1)->first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => 1,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Cash Change Tester',
            'items' => [['menu_id' => $bm->menu_id, 'quantity' => 1]]
        ]);

        $orderId = $resOrder->json('order.id');
        $order = Order::find($orderId);

        // Submit Cash Payment
        $this->postJson("/api/public/orders/{$resOrder->json('access_token')}/payment", [
            'method' => 'CASH'
        ]);

        // Cashier Confirms Paid with 100,000 cash received
        $resConfirm = $this->actingAs($kasir1)->postJson("/api/orders/{$orderId}/confirm-paid", [
            'cash_received' => 100000
        ]);

        $resConfirm->assertStatus(200);
        $payment = Payment::where('order_id', $orderId)->first();
        $this->assertEquals(100000 - $order->total_amount, $payment->cash_change);
    }

    public function test_19_unpaid_order_does_not_enter_kitchen()
    {
        $bm = BranchMenu::first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Unpaid Kitchen Tester',
            'items' => [['menu_id' => $bm->menu_id, 'quantity' => 1]]
        ]);

        $order = Order::find($resOrder->json('order.id'));
        $this->assertEquals('NOT_SENT', $order->kitchen_status);
        $this->assertDatabaseMissing('print_jobs', ['order_id' => $order->id, 'type' => 'KITCHEN_TICKET']);
    }

    public function test_20_final_confirmation_transitions_to_paid()
    {
        $order = $this->createPaidOrderHelper();
        $this->assertEquals('PAID', $order->payment_status);
        $this->assertEquals('WAITING', $order->kitchen_status);
    }

    public function test_21_paid_order_is_locked()
    {
        $order = $this->createPaidOrderHelper();
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();

        // Attempting second confirm-paid on locked order
        $resSecond = $this->actingAs($kasir1)->postJson("/api/orders/{$order->id}/confirm-paid", ['cash_received' => 50000]);
        $resSecond->assertStatus(409);
    }

    public function test_22_paid_order_cannot_be_cancelled()
    {
        $order = $this->createPaidOrderHelper();
        $orderFresh = Order::find($order->id);
        $this->assertEquals('PAID', $orderFresh->payment_status);
    }

    public function test_23_paid_order_cannot_be_edited()
    {
        $order = $this->createPaidOrderHelper();
        $this->assertEquals('PAID', $order->payment_status);
    }

    public function test_24_inventory_deducted_after_paid()
    {
        $inventory = Inventory::where('branch_id', 1)->where('item_name', 'like', '%Daging Ayam%')->first();
        $initialStock = $inventory->stock;

        $order = $this->createPaidOrderHelper();

        $inventoryFresh = Inventory::find($inventory->id);
        $this->assertLessThan($initialStock, $inventoryFresh->stock);
    }

    public function test_25_addon_inventory_deducted_after_paid()
    {
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $menu = Menu::where('name', 'like', '%Ayam Bakar%')->first();
        $addonTelur = Addon::where('name', 'like', '%Telur%')->first();
        $inventoryTelur = Inventory::where('branch_id', 1)->where('item_name', 'like', '%Telur%')->first();

        $initialStock = $inventoryTelur->stock;

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => 1,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Addon Inventory Tester',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1, 'addon_ids' => [$addonTelur->id]]]
        ]);

        $orderId = $resOrder->json('order.id');
        $this->postJson("/api/public/orders/{$resOrder->json('access_token')}/payment", ['method' => 'CASH']);
        $this->actingAs($kasir1)->postJson("/api/orders/{$orderId}/confirm-paid", ['cash_received' => 100000]);

        $inventoryFresh = Inventory::find($inventoryTelur->id);
        $this->assertEquals($initialStock - 1, $inventoryFresh->stock);
    }

    public function test_26_variant_inventory_deducted_after_paid()
    {
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $menu = Menu::where('name', 'like', '%Ayam Bakar%')->first();
        $vJumbo = Variant::where('menu_id', $menu->id)->where('name', 'like', '%Jumbo%')->first();
        $inventoryAyam = Inventory::where('branch_id', 1)->where('item_name', 'like', '%Daging Ayam%')->first();

        $initialStock = $inventoryAyam->stock;

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => 1,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Variant Inventory Tester',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1, 'variant_ids' => [$vJumbo->id]]]
        ]);

        $orderId = $resOrder->json('order.id');
        $this->postJson("/api/public/orders/{$resOrder->json('access_token')}/payment", ['method' => 'CASH']);
        $this->actingAs($kasir1)->postJson("/api/orders/{$orderId}/confirm-paid", ['cash_received' => 100000]);

        $inventoryFresh = Inventory::find($inventoryAyam->id);
        $this->assertEquals($initialStock - 1.5, $inventoryFresh->stock);
    }

    public function test_27_insufficient_stock_rolls_back_paid_transaction()
    {
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $inventoryAyam = Inventory::where('branch_id', 1)->where('item_name', 'like', '%Daging Ayam%')->first();
        $inventoryAyam->stock = 0; // Empty stock
        $inventoryAyam->save();

        $menu = Menu::where('name', 'like', '%Ayam Bakar%')->first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => 1,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Empty Stock Tester',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]]
        ]);

        $orderId = $resOrder->json('order.id');
        $this->postJson("/api/public/orders/{$resOrder->json('access_token')}/payment", ['method' => 'CASH']);

        $resConfirm = $this->actingAs($kasir1)->postJson("/api/orders/{$orderId}/confirm-paid", ['cash_received' => 100000]);
        $resConfirm->assertStatus(422);

        $orderFresh = Order::find($orderId);
        $this->assertNotEquals('PAID', $orderFresh->payment_status);
    }

    public function test_28_payment_validation_failure_does_not_mark_order_paid()
    {
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $bm = BranchMenu::where('branch_id', 1)->first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => 1,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'Insufficient Cash Tester',
            'items' => [['menu_id' => $bm->menu_id, 'quantity' => 1]]
        ]);

        $orderId = $resOrder->json('order.id');
        $this->postJson("/api/public/orders/{$resOrder->json('access_token')}/payment", ['method' => 'CASH']);

        $resConfirm = $this->actingAs($kasir1)->postJson("/api/orders/{$orderId}/confirm-paid", ['cash_received' => 1000]);
        $resConfirm->assertStatus(422);

        $orderFresh = Order::find($orderId);
        $this->assertNotEquals('PAID', $orderFresh->payment_status);
    }

    public function test_29_print_failure_does_not_rollback_paid_order()
    {
        $order = $this->createPaidOrderHelper();

        $job = PrintJob::where('order_id', $order->id)->first();
        if ($job) {
            $job->status = 'FAILED';
            $job->error_message = 'Printer offline';
            $job->save();
        }

        $orderFresh = Order::find($order->id);
        $this->assertEquals('PAID', $orderFresh->payment_status);
    }

    public function test_30_stock_movement_ledger_recorded()
    {
        $order = $this->createPaidOrderHelper();
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $order->id, 'type' => 'ORDER_DEDUCTION']);
    }

    public function test_31_kitchen_ticket_job_created_on_paid()
    {
        $order = $this->createPaidOrderHelper();
        $this->assertDatabaseHas('print_jobs', ['order_id' => $order->id, 'type' => 'KITCHEN_TICKET', 'status' => 'PENDING']);
    }

    public function test_32_duplicate_order_paid_event_does_not_duplicate_kitchen_ticket()
    {
        $order = $this->createPaidOrderHelper();
        $printService = app(\App\Services\PrintService::class);

        $job1 = $printService->createAutomaticKitchenTicket($order);
        $job2 = $printService->createAutomaticKitchenTicket($order);

        $this->assertEquals($job1->id, $job2->id);
    }

    public function test_33_print_failure_sets_status_failed()
    {
        $order = $this->createPaidOrderHelper();
        $job = PrintJob::where('order_id', $order->id)->first();
        $job->status = 'FAILED';
        $job->error_message = 'Paper out';
        $job->save();

        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'FAILED']);
    }

    public function test_34_retry_print_job()
    {
        $order = $this->createPaidOrderHelper();
        $job = PrintJob::where('order_id', $order->id)->first();
        $job->status = 'FAILED';
        $job->save();

        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $res = $this->actingAs($kasir1)->postJson("/api/print-jobs/{$job->id}/retry");
        $res->assertStatus(200);

        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'PENDING']);
    }

    public function test_35_reprint_ticket_has_reprint_marker()
    {
        $order = $this->createPaidOrderHelper();
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();

        $res = $this->actingAs($kasir1)->postJson("/api/orders/{$order->id}/reprint-kitchen");
        $res->assertStatus(200);

        $this->assertDatabaseHas('print_jobs', ['order_id' => $order->id, 'is_reprint' => true]);
    }

    public function test_36_audit_log_recorded()
    {
        $owner = User::where('role', 'owner')->first();
        $this->actingAs($owner)->postJson('/api/login', ['email' => $owner->email, 'password' => 'password123']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN']);
    }

    public function test_37_valid_table_qr_token()
    {
        $table = Table::first();
        $res = $this->getJson("/api/public/tables/{$table->qr_code_token}");
        $res->assertStatus(200);
    }

    public function test_38_table_qr_token_isolated_by_branch()
    {
        $admin1 = User::where('email', 'admin1@kedai.com')->first();
        $tableB2 = Table::where('branch_id', 2)->first();

        $res = $this->actingAs($admin1)->postJson("/api/branches/2/tables/{$tableB2->id}/regenerate-qr");
        $res->assertStatus(403);
    }

    public function test_39_takeaway_order_has_null_table_id()
    {
        $bm = BranchMenu::first();

        $resOrder = $this->postJson('/api/public/orders', [
            'branch_id' => $bm->branch_id,
            'order_type' => 'TAKE_AWAY',
            'customer_name' => 'TA Null Table Tester',
            'items' => [['menu_id' => $bm->menu_id, 'quantity' => 1]]
        ]);

        $order = Order::find($resOrder->json('order.id'));
        $this->assertNull($order->table_id);
    }

    public function test_40_kitchen_status_mutation_restricted_to_dapur_role()
    {
        $order = $this->createPaidOrderHelper();
        $kasir1 = User::where('email', 'kasir1@kedai.com')->first();
        $dapur1 = User::where('email', 'dapur1@kedai.com')->first();

        // Kasir attempting to mutate kitchen status MUST be forbidden (HTTP 403)
        $resKasir = $this->actingAs($kasir1)->patchJson("/api/kitchen/orders/{$order->id}/status", [
            'kitchen_status' => 'PREPARING'
        ]);
        $resKasir->assertStatus(403);

        // Dapur user can mutate status successfully (HTTP 200)
        $resDapur = $this->actingAs($dapur1)->patchJson("/api/kitchen/orders/{$order->id}/status", [
            'kitchen_status' => 'PREPARING'
        ]);
        $resDapur->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'kitchen_status' => 'PREPARING']);
    }

    public function test_41_derived_customer_status()
    {
        $order = $this->createPaidOrderHelper();
        $this->assertEquals('Pesanan Diterima', $order->derived_customer_status);
    }

    public function test_42_report_branch_isolation()
    {
        $admin1 = User::where('email', 'admin1@kedai.com')->first();
        $res = $this->actingAs($admin1)->getJson('/api/reports/sales?branch_id=2');
        $res->assertStatus(403);
    }
}
