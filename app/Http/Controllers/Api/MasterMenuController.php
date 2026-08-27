<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Variant;
use App\Models\Addon;
use App\Models\Branch;
use App\Models\BranchMenu;
use App\Services\AuditService;

class MasterMenuController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    private function checkAdminOrOwnerRole(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->isOwner() && !$user->isAdminCabang())) {
            abort(response()->json(['message' => 'Forbidden: Management access required.'], 403));
        }
    }

    private function checkOwnerRole(Request $request)
    {
        if (!$request->user() || !$request->user()->isOwner()) {
            abort(response()->json(['message' => 'Forbidden: Only Owner can perform this action.'], 403));
        }
    }

    // Categories CRUD
    public function getCategories()
    {
        return response()->json(Category::orderBy('sort_order', 'asc')->get());
    }

    public function storeCategory(Request $request)
    {
        $this->checkAdminOrOwnerRole($request);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $cat = Category::create($data);

        $this->auditService->log('CREATE_CATEGORY', 'Category', $cat->id, null, $cat->toArray());

        return response()->json($cat, 201);
    }

    // Master Menus CRUD
    public function getMasterMenus()
    {
        $menus = Menu::with(['category', 'variants', 'addons'])->get();
        return response()->json($menus);
    }

    public function storeMasterMenu(Request $request)
    {
        $this->checkAdminOrOwnerRole($request);

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sku' => 'required|string|unique:menus,sku',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'master_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:addons,id',
        ]);

        $imagePath = null;
        if ($request->hasFile('master_image')) {
            $imagePath = $request->file('master_image')->store('menu/master', 'public');
        }

        $menu = Menu::create([
            'category_id' => $request->category_id,
            'sku' => strtoupper($request->sku),
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'base_price' => $request->base_price,
            'master_image' => $imagePath,
            'status' => 'ACTIVE',
            'created_by' => auth()->id(),
        ]);

        if (!empty($request->addon_ids)) {
            $menu->addons()->sync($request->addon_ids);
        }

        // Auto-create BranchMenu records for all branches (or user's current branch) so menu is immediately active
        $user = $request->user();
        $targetBranchIds = $user->isOwner()
            ? Branch::pluck('id')->toArray()
            : array_filter([$user->branch_id]);

        foreach ($targetBranchIds as $bId) {
            BranchMenu::firstOrCreate(
                [
                    'branch_id' => $bId,
                    'menu_id' => $menu->id,
                ],
                [
                    'price' => $menu->base_price,
                    'availability' => 'available',
                    'status' => 'active',
                    'sort_order' => 0,
                    'is_featured' => false,
                ]
            );
        }

        $this->auditService->log('CREATE_MASTER_MENU', 'Menu', $menu->id, null, $menu->toArray());

        return response()->json($menu->load(['category', 'variants', 'addons']), 201);
    }

    public function updateMasterMenu(Request $request, $id)
    {
        $this->checkAdminOrOwnerRole($request);
        $menu = Menu::findOrFail($id);

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'master_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:addons,id',
        ]);

        $oldValues = $menu->toArray();

        if ($request->hasFile('master_image')) {
            $menu->master_image = $request->file('master_image')->store('menu/master', 'public');
        }

        $menu->update([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'base_price' => $request->base_price,
            'updated_by' => auth()->id(),
        ]);

        if (isset($request->addon_ids)) {
            $menu->addons()->sync($request->addon_ids);
        }

        $this->auditService->log('UPDATE_MASTER_MENU', 'Menu', $menu->id, $oldValues, $menu->toArray());

        return response()->json($menu->load(['category', 'variants', 'addons']));
    }

    // Variants Management
    public function storeVariant(Request $request)
    {
        $this->checkAdminOrOwnerRole($request);
        $data = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer',
        ]);

        $variant = Variant::create($data);
        $this->auditService->log('CREATE_VARIANT', 'Variant', $variant->id, null, $variant->toArray());

        return response()->json($variant, 201);
    }

    // Addons Management
    public function getAddons()
    {
        return response()->json(\App\Models\Addon::orderBy('name', 'asc')->get());
    }

    public function storeAddon(Request $request)
    {
        $this->checkAdminOrOwnerRole($request);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'sort_order' => 'nullable|integer',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('addons', 'public');
        }

        $addon = \App\Models\Addon::create($data);
        $this->auditService->log('CREATE_ADDON', 'Addon', $addon->id, null, $addon->toArray());

        return response()->json($addon, 201);
    }
}
