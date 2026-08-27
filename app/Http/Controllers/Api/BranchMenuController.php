<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BranchMenu;
use App\Models\Menu;
use App\Services\AuditService;

class BranchMenuController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    private function checkBranchAccess(Request $request, int $branchId)
    {
        $user = $request->user();
        if ($user->isOwner()) {
            return;
        }
        if ((string)$user->branch_id !== (string)$branchId) {
            abort(response()->json(['message' => 'Forbidden: You cannot modify another branch menu.'], 403));
        }
    }

    public function getBranchMenus(Request $request, $branchId)
    {
        $this->checkBranchAccess($request, $branchId);

        $branchMenus = BranchMenu::with(['menu.category', 'menu.variants', 'menu.addons'])
            ->where('branch_id', $branchId)
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json($branchMenus);
    }

    public function updateBranchMenu(Request $request, $branchId, $menuId)
    {
        $this->checkBranchAccess($request, $branchId);

        $bm = BranchMenu::where('branch_id', $branchId)
            ->where('menu_id', $menuId)
            ->first();

        if (!$bm) {
            // Activate menu for this branch if not existing
            $master = Menu::findOrFail($menuId);
            $bm = new BranchMenu([
                'branch_id' => $branchId,
                'menu_id' => $menuId,
                'price' => $master->base_price,
                'availability' => 'available',
                'status' => 'active',
                'sort_order' => 0,
                'is_featured' => false,
            ]);
        }

        $request->validate([
            'price' => 'required|numeric|min:0',
            'availability' => 'required|in:available,sold_out',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'nullable|integer',
            'is_featured' => 'nullable|boolean',
            'branch_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $oldValues = $bm->toArray();

        if ($request->hasFile('branch_image')) {
            $bm->branch_image = $request->file('branch_image')->store("menu/branch_{$branchId}", 'public');
        }

        $bm->price = $request->price;
        $bm->availability = $request->availability;
        $bm->status = $request->status;
        $bm->sort_order = $request->sort_order ?? $bm->sort_order;
        $bm->is_featured = $request->is_featured ?? $bm->is_featured;
        $bm->save();

        $this->auditService->log('UPDATE_BRANCH_MENU', 'BranchMenu', $bm->id, $oldValues, $bm->toArray(), $branchId);

        return response()->json($bm->load('menu'));
    }

    public function setAvailability(Request $request, $id)
    {
        $bm = BranchMenu::findOrFail($id);
        $this->checkBranchAccess($request, $bm->branch_id);

        $request->validate([
            'availability' => 'required|in:available,sold_out',
        ]);

        $oldValues = $bm->toArray();
        $bm->availability = $request->availability;
        $bm->save();

        $this->auditService->log('UPDATE_AVAILABILITY', 'BranchMenu', $bm->id, $oldValues, $bm->toArray(), $bm->branch_id);

        return response()->json($bm);
    }

    public function destroy(Request $request, $id)
    {
        $bm = BranchMenu::findOrFail($id);
        $this->checkBranchAccess($request, $bm->branch_id);

        $oldValues = $bm->toArray();
        $bm->delete();

        $this->auditService->log('DELETE_BRANCH_MENU', 'BranchMenu', $id, $oldValues, null, $bm->branch_id);

        return response()->json(['message' => 'Menu cabang berhasil dihapus.']);
    }
}
