<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Branch;
use App\Models\Table;
use App\Models\Category;
use App\Models\Menu;
use App\Models\BranchMenu;
use App\Models\Variant;
use App\Models\Addon;
use App\Models\Promo;
use App\Models\Inventory;
use App\Models\Recipe;
use App\Models\Printer;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Branches
        $b1 = Branch::create([
            'code' => 'C01',
            'name' => 'Cabang Utama (Lesehan Center)',
            'address' => 'Jl. Pemuda No. 101, Surabaya',
            'phone' => '081234567891',
            'status' => 'ACTIVE',
        ]);

        $b2 = Branch::create([
            'code' => 'C02',
            'name' => 'Cabang Lesehan 2 (Ahmad Yani)',
            'address' => 'Jl. Ahmad Yani No. 45, Surabaya',
            'phone' => '081234567892',
            'status' => 'ACTIVE',
        ]);

        $b3 = Branch::create([
            'code' => 'C03',
            'name' => 'Cabang Lesehan 3 (Soekarno Hatta)',
            'address' => 'Jl. Soekarno Hatta No. 88, Malang',
            'phone' => '081234567893',
            'status' => 'ACTIVE',
        ]);

        // 2. Default Users
        User::create([
            'name' => 'Owner Kedai',
            'email' => 'owner@kedai.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'branch_id' => null,
            'status' => 'ACTIVE',
        ]);

        foreach ([$b1, $b2, $b3] as $idx => $b) {
            $num = $idx + 1;
            User::create([
                'name' => "Admin Cabang {$num}",
                'email' => "admin{$num}@kedai.com",
                'password' => Hash::make('password123'),
                'role' => 'admin_cabang',
                'branch_id' => $b->id,
                'status' => 'ACTIVE',
            ]);

            User::create([
                'name' => "Kasir Cabang {$num}",
                'email' => "kasir{$num}@kedai.com",
                'password' => Hash::make('password123'),
                'role' => 'kasir',
                'branch_id' => $b->id,
                'status' => 'ACTIVE',
            ]);

            User::create([
                'name' => "Dapur Cabang {$num}",
                'email' => "dapur{$num}@kedai.com",
                'password' => Hash::make('password123'),
                'role' => 'dapur',
                'branch_id' => $b->id,
                'status' => 'ACTIVE',
            ]);
        }

        // 3. Tables per branch
        foreach ([$b1, $b2, $b3] as $b) {
            for ($i = 1; $i <= 10; $i++) {
                $numStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                Table::create([
                    'branch_id' => $b->id,
                    'table_number' => "Meja {$numStr}",
                    'qr_code_token' => Str::random(40),
                    'status' => 'available',
                ]);
            }
        }

        // 4. Categories
        $catMakanan = Category::create(['name' => 'Makanan Utama', 'slug' => 'makanan-utama', 'description' => 'Menu hidangan utama lesehan', 'sort_order' => 1]);
        $catMinuman = Category::create(['name' => 'Minuman', 'slug' => 'minuman', 'description' => 'Aneka minuman segar & hangat', 'sort_order' => 2]);
        $catSnack   = Category::create(['name' => 'Snack & Extra', 'slug' => 'snack-extra', 'description' => 'Camilan dan pendamping', 'sort_order' => 3]);

        // 5. Master Menus
        $mAyamBakar = Menu::create([
            'category_id' => $catMakanan->id,
            'sku' => 'FOOD-001',
            'name' => 'Ayam Bakar Madu Lesehan',
            'slug' => 'ayam-bakar-madu-lesehan',
            'description' => 'Ayam bakar segar bumbu rempah madu khas kedai lesehan.',
            'base_price' => 25000,
            'status' => 'ACTIVE',
        ]);

        $mNasiGoreng = Menu::create([
            'category_id' => $catMakanan->id,
            'sku' => 'FOOD-002',
            'name' => 'Nasi Goreng Spesial Kedai',
            'slug' => 'nasi-goreng-spesial-kedai',
            'description' => 'Nasi goreng racikan spesial dengan telur dan bakso.',
            'base_price' => 20000,
            'status' => 'ACTIVE',
        ]);

        $mBebekGoreng = Menu::create([
            'category_id' => $catMakanan->id,
            'sku' => 'FOOD-003',
            'name' => 'Bebek Goreng Crispy',
            'slug' => 'bebek-goreng-crispy',
            'description' => 'Bebek goreng empuk dengan kremesan crispy gurih.',
            'base_price' => 30000,
            'status' => 'ACTIVE',
        ]);

        $mEsTeh = Menu::create([
            'category_id' => $catMinuman->id,
            'sku' => 'DRINK-001',
            'name' => 'Es Teh Manis Jumbo',
            'slug' => 'es-teh-manis-jumbo',
            'description' => 'Es teh seduh alami racikan khas kedai.',
            'base_price' => 5000,
            'status' => 'ACTIVE',
        ]);

        $mEsJeruk = Menu::create([
            'category_id' => $catMinuman->id,
            'sku' => 'DRINK-002',
            'name' => 'Es Jeruk Peras',
            'slug' => 'es-jeruk-peras',
            'description' => 'Jeruk peras murni dingin segar.',
            'base_price' => 7000,
            'status' => 'ACTIVE',
        ]);

        $mTahuTempe = Menu::create([
            'category_id' => $catSnack->id,
            'sku' => 'SNACK-001',
            'name' => 'Tahu Tempe Goreng Bumbu',
            'slug' => 'tahu-tempe-goreng-bumbu',
            'description' => 'Tahu dan tempe goreng renyah dengan sambal kecap.',
            'base_price' => 8000,
            'status' => 'ACTIVE',
        ]);

        // 6. Variants
        $vRegular = Variant::create(['menu_id' => $mAyamBakar->id, 'name' => 'Porsi Regular', 'price' => 0, 'sort_order' => 1]);
        $vJumbo   = Variant::create(['menu_id' => $mAyamBakar->id, 'name' => 'Porsi Jumbo (Dada Super)', 'price' => 5000, 'sort_order' => 2]);
        $vPedas   = Variant::create(['menu_id' => $mAyamBakar->id, 'name' => 'Level Pedas Mampus', 'price' => 2000, 'sort_order' => 3]);

        // 7. Addons
        $addonTelur  = Addon::create(['name' => 'Extra Telur Dadar', 'price' => 5000, 'sort_order' => 1]);
        $addonKeju   = Addon::create(['name' => 'Extra Keju Parut', 'price' => 4000, 'sort_order' => 2]);
        $addonSambal = Addon::create(['name' => 'Extra Sambal Ijo', 'price' => 3000, 'sort_order' => 3]);

        // Attach Addons to Menus
        $mAyamBakar->addons()->attach([$addonTelur->id, $addonKeju->id, $addonSambal->id]);
        $mNasiGoreng->addons()->attach([$addonTelur->id, $addonSambal->id]);
        $mBebekGoreng->addons()->attach([$addonTelur->id, $addonSambal->id]);

        // 8. Branch Menus Setup
        foreach ([$b1, $b2, $b3] as $b) {
            // Ayam Bakar
            $priceAyam = ($b->code === 'C02') ? 27000 : 25000;
            BranchMenu::create([
                'branch_id' => $b->id,
                'menu_id' => $mAyamBakar->id,
                'price' => $priceAyam,
                'availability' => 'available',
                'status' => 'active',
                'is_featured' => true,
                'sort_order' => 1,
            ]);

            // Nasi Goreng
            $priceNasgor = ($b->code === 'C02') ? 22000 : 20000;
            BranchMenu::create([
                'branch_id' => $b->id,
                'menu_id' => $mNasiGoreng->id,
                'price' => $priceNasgor,
                'availability' => 'available',
                'status' => 'active',
                'is_featured' => true,
                'sort_order' => 2,
            ]);

            // Bebek Goreng
            BranchMenu::create([
                'branch_id' => $b->id,
                'menu_id' => $mBebekGoreng->id,
                'price' => 30000,
                'availability' => 'available',
                'status' => 'active',
                'sort_order' => 3,
            ]);

            // Es Teh
            $priceTeh = ($b->code === 'C03') ? 6000 : 5000;
            BranchMenu::create([
                'branch_id' => $b->id,
                'menu_id' => $mEsTeh->id,
                'price' => $priceTeh,
                'availability' => 'available',
                'status' => 'active',
                'is_featured' => true,
                'sort_order' => 4,
            ]);

            // Es Jeruk
            BranchMenu::create([
                'branch_id' => $b->id,
                'menu_id' => $mEsJeruk->id,
                'price' => 7000,
                'availability' => 'available',
                'status' => 'active',
                'sort_order' => 5,
            ]);

            // Tahu Tempe
            BranchMenu::create([
                'branch_id' => $b->id,
                'menu_id' => $mTahuTempe->id,
                'price' => 8000,
                'availability' => 'available',
                'status' => 'active',
                'sort_order' => 6,
            ]);
        }

        // 9. Promos per branch
        foreach ([$b1, $b2, $b3] as $b) {
            $p1 = Promo::create([
                'branch_id' => $b->id,
                'name' => 'Diskon 20% Ayam Bakar',
                'code' => "PROMO20-{$b->code}",
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'min_purchase' => 25000,
                'max_discount' => 10000,
                'is_stackable' => true,
                'priority' => 1,
                'start_date' => now()->subDays(5),
                'end_date' => now()->addDays(30),
                'status' => 'ACTIVE',
            ]);
            $p1->menus()->attach($mAyamBakar->id);

            $p2 = Promo::create([
                'branch_id' => $b->id,
                'name' => 'Potongan Lesehan Rp 5.000',
                'code' => "HEMAT5K-{$b->code}",
                'discount_type' => 'fixed_amount',
                'discount_value' => 5000,
                'min_purchase' => 40000,
                'is_stackable' => true,
                'priority' => 2,
                'start_date' => now()->subDays(5),
                'end_date' => now()->addDays(30),
                'status' => 'ACTIVE',
            ]);
        }

        // 10. Inventories & Single-Target Recipes per branch
        foreach ([$b1, $b2, $b3] as $b) {
            $invAyam = Inventory::create(['branch_id' => $b->id, 'item_name' => 'Daging Ayam Fresh', 'unit' => 'potong', 'stock' => 100, 'min_stock' => 10, 'cost_per_unit' => 8000]);
            $invBeras = Inventory::create(['branch_id' => $b->id, 'item_name' => 'Beras Pandan Wangi', 'unit' => 'gram', 'stock' => 50000, 'min_stock' => 5000, 'cost_per_unit' => 12]);
            $invTelur = Inventory::create(['branch_id' => $b->id, 'item_name' => 'Telur Ayam', 'unit' => 'butir', 'stock' => 200, 'min_stock' => 20, 'cost_per_unit' => 2000]);
            $invTeh   = Inventory::create(['branch_id' => $b->id, 'item_name' => 'Daun Teh Tubruk', 'unit' => 'gram', 'stock' => 10000, 'min_stock' => 1000, 'cost_per_unit' => 15]);
            $invMinyak = Inventory::create(['branch_id' => $b->id, 'item_name' => 'Minyak Goreng', 'unit' => 'ml', 'stock' => 20000, 'min_stock' => 2000, 'cost_per_unit' => 18]);

            // Base Menu Recipes (Single Target: menu_id)
            Recipe::create(['branch_id' => $b->id, 'menu_id' => $mAyamBakar->id, 'inventory_id' => $invAyam->id, 'quantity' => 1, 'unit' => 'potong']);
            Recipe::create(['branch_id' => $b->id, 'menu_id' => $mAyamBakar->id, 'inventory_id' => $invMinyak->id, 'quantity' => 20, 'unit' => 'ml']);

            Recipe::create(['branch_id' => $b->id, 'menu_id' => $mNasiGoreng->id, 'inventory_id' => $invBeras->id, 'quantity' => 150, 'unit' => 'gram']);
            Recipe::create(['branch_id' => $b->id, 'menu_id' => $mNasiGoreng->id, 'inventory_id' => $invMinyak->id, 'quantity' => 15, 'unit' => 'ml']);

            Recipe::create(['branch_id' => $b->id, 'menu_id' => $mEsTeh->id, 'inventory_id' => $invTeh->id, 'quantity' => 10, 'unit' => 'gram']);

            // Variant Recipe (Single Target: variant_id)
            Recipe::create(['branch_id' => $b->id, 'variant_id' => $vJumbo->id, 'inventory_id' => $invAyam->id, 'quantity' => 0.5, 'unit' => 'potong']);

            // Addon Recipe (Single Target: addon_id)
            Recipe::create(['branch_id' => $b->id, 'addon_id' => $addonTelur->id, 'inventory_id' => $invTelur->id, 'quantity' => 1, 'unit' => 'butir']);
        }

        // 11. Printers per branch
        foreach ([$b1, $b2, $b3] as $b) {
            Printer::create([
                'branch_id' => $b->id,
                'name' => "Printer Kasir {$b->code}",
                'type' => 'RECEIPT',
                'connection_type' => 'BROWSER',
                'paper_width' => '58mm',
                'status' => 'ACTIVE',
            ]);

            Printer::create([
                'branch_id' => $b->id,
                'name' => "Printer Dapur {$b->code}",
                'type' => 'KITCHEN',
                'connection_type' => 'BROWSER',
                'paper_width' => '80mm',
                'status' => 'ACTIVE',
            ]);
        }
    }
}
