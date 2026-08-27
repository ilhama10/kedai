<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PrintJob;
use App\Models\Order;
use App\Services\PrintService;
use App\Services\AuditService;

class PrintJobController extends Controller
{
    protected $printService;
    protected $auditService;

    public function __construct(PrintService $printService, AuditService $auditService)
    {
        $this->printService = $printService;
        $this->auditService = $auditService;
    }

    private function checkBranchAccess(Request $request, int $branchId)
    {
        $user = $request->user();
        if ($user->isOwner()) {
            return;
        }
        if ((string)$user->branch_id !== (string)$branchId) {
            abort(response()->json(['message' => 'Forbidden: Cannot access print jobs for another branch.'], 403));
        }
    }

    public function getPrintJobs(Request $request)
    {
        $user = $request->user();
        $query = PrintJob::with('order');

        if (!$user->isOwner()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate(30));
    }

    public function retryPrintJob(Request $request, $id)
    {
        $job = PrintJob::findOrFail($id);
        $this->checkBranchAccess($request, $job->branch_id);

        $retriedJob = $this->printService->retryPrintJob($job);
        $this->auditService->log('PRINT_RETRY', 'PrintJob', $job->id, null, ['attempts' => $job->attempts], $job->branch_id);

        return response()->json([
            'message' => 'Job pencetakan berhasil di-queue ulang.',
            'print_job' => $retriedJob,
        ]);
    }

    public function reprintReceipt(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        $this->checkBranchAccess($request, $order->branch_id);

        if ($order->payment_status !== 'PAID') {
            return response()->json(['message' => 'Nota hanya dapat dicetak untuk pesanan LUNAS (PAID).'], 422);
        }

        $paperWidth = $request->paper_width ?? '58mm';
        $job = $this->printService->createReceiptPrint($order, $paperWidth);
        $this->auditService->log('REPRINT_RECEIPT', 'Order', $order->id, null, ['paper_width' => $paperWidth], $order->branch_id);

        return response()->json([
            'message' => 'Nota pencetakan berhasil dibuat.',
            'print_job' => $job,
        ]);
    }

    public function reprintKitchen(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        $this->checkBranchAccess($request, $order->branch_id);

        if ($order->payment_status !== 'PAID') {
            return response()->json(['message' => 'Tiket dapur hanya dapat dicetak untuk pesanan LUNAS (PAID).'], 422);
        }

        $job = $this->printService->createKitchenReprint($order);
        $this->auditService->log('REPRINT_KITCHEN_TICKET', 'Order', $order->id, null, ['is_reprint' => true], $order->branch_id);

        return response()->json([
            'message' => 'Cetak ulang tiket dapur berhasil dibuat dengan marker REPRINT.',
            'print_job' => $job,
        ]);
    }
}
