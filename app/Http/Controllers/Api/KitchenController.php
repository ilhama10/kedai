<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\AuditService;

class KitchenController extends Controller
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
            abort(response()->json(['message' => 'Forbidden: Cannot access kitchen for another branch.'], 403));
        }
    }

    public function getKitchenOrders(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['orderItems.variants', 'orderItems.addons', 'table', 'branch'])
            ->where('payment_status', 'PAID')
            ->whereIn('kitchen_status', ['WAITING', 'PREPARING', 'READY']);

        if (!$user->isOwner()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $orders = $query->orderBy('created_at', 'asc')->get();
        return response()->json($orders);
    }

    public function updateKitchenStatus(Request $request, $id)
    {
        $user = $request->user();

        // STRICT PERMISSION RULE: Only role 'dapur' may mutate kitchen status. Owner/Admin/Kasir are VIEW ONLY!
        if (!$user->isDapur()) {
            return response()->json([
                'message' => 'Forbidden: Hanya petugas Dapur yang berhak mengunggah/mengubah status dapur.'
            ], 403);
        }

        $order = Order::findOrFail($id);
        $this->checkBranchAccess($request, $order->branch_id);

        if ($order->payment_status !== 'PAID') {
            return response()->json([
                'message' => 'Pesanan belum lunas (UNPAID) tidak dapat diproses di dapur.'
            ], 422);
        }

        $request->validate([
            'kitchen_status' => 'required|in:WAITING,PREPARING,READY,COMPLETED',
        ]);

        $nextStatus = $request->kitchen_status;
        $currStatus = $order->kitchen_status;

        // Valid transition state machine: WAITING -> PREPARING -> READY -> COMPLETED
        $validTransitions = [
            'WAITING' => ['PREPARING'],
            'PREPARING' => ['READY'],
            'READY' => ['COMPLETED'],
            'COMPLETED' => [],
        ];

        if (!in_array($nextStatus, $validTransitions[$currStatus] ?? [])) {
            return response()->json([
                'message' => "Transisi status dapur tidak valid dari '{$currStatus}' ke '{$nextStatus}'."
            ], 422);
        }

        $oldValues = ['kitchen_status' => $currStatus];
        $order->kitchen_status = $nextStatus;
        $order->save();

        $this->auditService->log(
            'KITCHEN_STATUS_MUTATED',
            'Order',
            $order->id,
            $oldValues,
            ['kitchen_status' => $nextStatus],
            $order->branch_id
        );

        return response()->json([
            'message' => "Status dapur berhasil diperbarui menjadi {$nextStatus}.",
            'order' => $order->fresh(),
        ]);
    }
}
