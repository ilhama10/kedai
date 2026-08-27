<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Branches
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('qris_image')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });

        // Add foreign key constraint to users.branch_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        // 2. Tables (meja)
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('table_number', 20);
            $table->string('qr_code_token', 64)->unique();
            $table->enum('status', ['available', 'occupied'])->default('available');
            $table->timestamps();
        });

        // 3. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });

        // 4. Master Menus
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->string('sku', 50)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->string('master_image')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 5. Branch Menus
        Schema::create('branch_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
            $table->decimal('price', 12, 2);
            $table->string('branch_image')->nullable();
            $table->enum('availability', ['available', 'sold_out'])->default('available');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->unique(['branch_id', 'menu_id']);
        });

        // 6. Variants
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 7. Addons
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->string('image')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 8. Menu Addons Mapping
        Schema::create('menu_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
            $table->foreignId('addon_id')->constrained('addons')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['menu_id', 'addon_id']);
        });

        // 9. Promos
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->enum('discount_type', ['percentage', 'fixed_amount']);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('min_purchase', 12, 2)->default(0);
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->integer('priority')->default(0);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('promo_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promos')->onDelete('cascade');
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
        });

        Schema::create('promo_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promos')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
        });

        // 10. Inventories
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('item_name');
            $table->string('unit', 20);
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->decimal('cost_per_unit', 12, 2)->default(0);
            $table->timestamps();
        });

        // 11. Recipes (Single-target constraint: menu_id OR variant_id OR addon_id)
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('menu_id')->nullable()->constrained('menus')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('variants')->onDelete('cascade');
            $table->foreignId('addon_id')->nullable()->constrained('addons')->onDelete('cascade');
            $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20);
            $table->timestamps();
        });

        // 12. Stock Movements
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade');
            $table->enum('type', ['ORDER_DEDUCTION', 'MANUAL_IN', 'MANUAL_OUT', 'WASTE', 'ADJUSTMENT']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity_change', 12, 3);
            $table->decimal('stock_before', 12, 3);
            $table->decimal('stock_after', 12, 3);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 13. Orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->string('customer_access_token_hash', 64)->unique();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('table_id')->nullable()->constrained('tables')->onDelete('set null');
            $table->string('customer_name');
            $table->enum('order_type', ['DINE_IN', 'TAKE_AWAY']);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->enum('payment_status', ['UNPAID', 'PENDING_VERIFICATION', 'PAID'])->default('UNPAID');
            $table->enum('kitchen_status', ['NOT_SENT', 'WAITING', 'PREPARING', 'READY', 'COMPLETED'])->default('NOT_SENT');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 14. Order Promos
        Schema::create('order_promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('promo_id')->nullable()->constrained('promos')->onDelete('set null');
            $table->string('promo_name_snapshot');
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();
        });

        // 15. Order Items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('menu_id')->constrained('menus')->onDelete('restrict');
            $table->string('menu_name_snapshot');
            $table->decimal('unit_price_snapshot', 12, 2);
            $table->decimal('cost_amount_snapshot', 12, 2)->default(0);
            $table->integer('quantity');
            $table->decimal('total_price', 12, 2);
            $table->text('notes')->nullable();
            $table->string('image_snapshot')->nullable();
            $table->timestamps();
        });

        // 16. Order Item Variants
        Schema::create('order_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('variants')->onDelete('set null');
            $table->string('name_snapshot');
            $table->decimal('price_snapshot', 12, 2)->default(0);
            $table->timestamps();
        });

        // 17. Order Item Addons
        Schema::create('order_item_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->foreignId('addon_id')->nullable()->constrained('addons')->onDelete('set null');
            $table->string('name_snapshot');
            $table->decimal('price_snapshot', 12, 2)->default(0);
            $table->timestamps();
        });

        // 18. Order Item Costs (Detailed Historical COGS)
        Schema::create('order_item_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->onDelete('set null');
            $table->string('inventory_name_snapshot');
            $table->decimal('quantity_consumed', 12, 3);
            $table->string('unit_snapshot', 20);
            $table->decimal('cost_per_unit_snapshot', 12, 2);
            $table->decimal('cost_amount', 12, 2);
            $table->timestamps();
        });

        // 19. Payments (UNIQUE constraint on order_id)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->onDelete('cascade');
            $table->enum('method', ['QRIS', 'CASH']);
            $table->enum('status', ['UNPAID', 'PENDING_VERIFICATION', 'PAID'])->default('UNPAID');
            $table->decimal('amount', 12, 2);
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('cash_change', 12, 2)->nullable();
            $table->string('proof_image')->nullable();
            $table->enum('verification_method', ['upload_proof', 'show_to_cashier'])->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // 20. Printers
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['RECEIPT', 'KITCHEN']);
            $table->enum('connection_type', ['BROWSER', 'LOCAL_AGENT', 'LAN', 'USB'])->default('BROWSER');
            $table->json('connection_config')->nullable();
            $table->enum('paper_width', ['58mm', '80mm'])->default('58mm');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });

        // 21. Print Jobs
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->enum('type', ['RECEIPT', 'KITCHEN_TICKET']);
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->json('payload');
            $table->enum('status', ['PENDING', 'PRINTING', 'PRINTED', 'FAILED'])->default('PENDING');
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->string('printer_name')->nullable();
            $table->string('idempotency_token', 100)->unique();
            $table->boolean('is_reprint')->default(false);
            $table->timestamps();
        });

        // 22. Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('printers');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_costs');
        Schema::dropIfExists('order_item_addons');
        Schema::dropIfExists('order_item_variants');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_promos');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('promo_categories');
        Schema::dropIfExists('promo_menus');
        Schema::dropIfExists('promos');
        Schema::dropIfExists('menu_addons');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('variants');
        Schema::dropIfExists('branch_menus');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('tables');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['role', 'branch_id', 'status']);
        });

        Schema::dropIfExists('branches');
    }
};
