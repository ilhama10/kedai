<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Table;
use App\Services\AuditService;

class TableController extends Controller
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
            abort(response()->json(['message' => 'Forbidden: Cannot access tables for another branch.'], 403));
        }
    }

    public function getBranchTables(Request $request, $branchId)
    {
        $this->checkBranchAccess($request, $branchId);
        $tables = Table::where('branch_id', $branchId)->orderBy('table_number', 'asc')->get();
        return response()->json($tables);
    }

    public function storeTable(Request $request, $branchId)
    {
        $this->checkBranchAccess($request, $branchId);

        $request->validate([
            'table_number' => 'required|string|max:20',
        ]);

        $table = Table::create([
            'branch_id' => $branchId,
            'table_number' => $request->table_number,
            'qr_code_token' => Str::random(40),
            'status' => 'available',
        ]);

        $this->auditService->log('CREATE_TABLE', 'Table', $table->id, null, $table->toArray(), $branchId);

        return response()->json($table, 201);
    }

    public function regenerateQrToken(Request $request, $branchId, $tableId)
    {
        $this->checkBranchAccess($request, $branchId);

        $table = Table::where('branch_id', $branchId)->findOrFail($tableId);
        $oldToken = $table->qr_code_token;

        // Security: Immediate Token Invalidation by generating new secure token
        $table->qr_code_token = Str::random(40);
        $table->save();

        $this->auditService->log('REGENERATE_TABLE_QR', 'Table', $table->id, ['token' => $oldToken], ['token' => $table->qr_code_token], $branchId);

        return response()->json([
            'message' => 'Token QR meja berhasil diperbarui. Token lama telah tidak berlaku.',
            'table' => $table,
        ]);
    }
}
