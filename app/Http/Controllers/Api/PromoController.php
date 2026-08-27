<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Promo;
use App\Services\AuditService;

class PromoController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    private function checkBranchAccess(Request $request, ?int $branchId)
    {
        $user = $request->user();
        if ($user->isOwner()) {
            return;
        }
        if ($branchId && (string)$user->branch_id !== (string)$branchId) {
            abort(response()->json(['message' => 'Forbidden: You cannot modify promo for another branch.'], 403));
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Promo::query();

        if (!$user->isOwner()) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('branch_id')->orWhere('branch_id', $user->branch_id);
            });
        } elseif ($request->has('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('branch_id')->orWhere('branch_id', $request->branch_id);
            });
        }

        return response()->json($query->orderBy('priority', 'asc')->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $branchId = $user->isOwner() ? $request->branch_id : $user->branch_id;

        $request->validate([
            'code' => 'required|string|max:50|unique:promos,code',
            'name' => 'required|string|max:150',
            'discount_type' => 'nullable|in:percentage,fixed_amount,PERCENTAGE,FIXED_AMOUNT',
            'type' => 'nullable|in:PERCENTAGE,FIXED_AMOUNT,percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'is_stackable' => 'nullable|boolean',
            'priority' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $rawType = strtolower($request->discount_type ?? $request->type ?? 'percentage');
        $discountType = $rawType === 'fixed_amount' ? 'fixed_amount' : 'percentage';

        $minPurchase = $request->min_purchase ?? $request->min_order_amount ?? 0;
        $maxDiscount = $request->max_discount ?? $request->max_discount_amount;
        $status = strtoupper($request->status ?? 'ACTIVE');

        $promo = Promo::create([
            'branch_id' => $branchId,
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'discount_type' => $discountType,
            'discount_value' => $request->discount_value,
            'min_purchase' => $minPurchase,
            'max_discount' => $maxDiscount,
            'is_stackable' => $request->is_stackable ?? true,
            'priority' => $request->priority ?? 10,
            'start_date' => $request->start_date ?? now(),
            'end_date' => $request->end_date ?? now()->addMonths(6),
            'status' => $status,
        ]);

        $this->auditService->log('CREATE_PROMO', 'Promo', $promo->id, null, $promo->toArray(), $branchId);

        return response()->json($promo, 201);
    }

    public function update(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);
        $this->checkBranchAccess($request, $promo->branch_id);

        $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'discount_type' => 'nullable|in:percentage,fixed_amount,PERCENTAGE,FIXED_AMOUNT',
            'type' => 'nullable|in:PERCENTAGE,FIXED_AMOUNT,percentage,fixed_amount',
            'discount_value' => 'sometimes|required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'is_stackable' => 'nullable|boolean',
            'priority' => 'nullable|integer',
            'status' => 'sometimes|required|string',
        ]);

        $oldValues = $promo->toArray();

        $updateData = [];
        if ($request->has('name')) $updateData['name'] = $request->name;
        if ($request->has('discount_type') || $request->has('type')) {
            $rawType = strtolower($request->discount_type ?? $request->type);
            $updateData['discount_type'] = $rawType === 'fixed_amount' ? 'fixed_amount' : 'percentage';
        }
        if ($request->has('discount_value')) $updateData['discount_value'] = $request->discount_value;
        if ($request->has('min_purchase') || $request->has('min_order_amount')) {
            $updateData['min_purchase'] = $request->min_purchase ?? $request->min_order_amount;
        }
        if ($request->has('max_discount') || $request->has('max_discount_amount')) {
            $updateData['max_discount'] = $request->max_discount ?? $request->max_discount_amount;
        }
        if ($request->has('is_stackable')) $updateData['is_stackable'] = $request->is_stackable;
        if ($request->has('priority')) $updateData['priority'] = $request->priority;
        if ($request->has('status')) $updateData['status'] = strtoupper($request->status);

        $promo->update($updateData);

        $this->auditService->log('UPDATE_PROMO', 'Promo', $promo->id, $oldValues, $promo->toArray(), $promo->branch_id);

        return response()->json($promo);
    }

    public function destroy(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);
        $this->checkBranchAccess($request, $promo->branch_id);

        $oldValues = $promo->toArray();
        $promo->delete();

        $this->auditService->log('DELETE_PROMO', 'Promo', $id, $oldValues, null, $promo->branch_id);

        return response()->json(['message' => 'Promo berhasil dihapus.']);
    }
}
