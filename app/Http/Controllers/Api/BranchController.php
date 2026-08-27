<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Branch;
use App\Services\AuditService;

class BranchController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    private function checkOwner(Request $request)
    {
        if (!$request->user()->isOwner()) {
            abort(response()->json(['message' => 'Forbidden: Only Owner can manage branches.'], 403));
        }
    }

    public function index(Request $request)
    {
        $branches = Branch::withCount(['users', 'tables'])->orderBy('created_at', 'asc')->get();
        return response()->json($branches);
    }

    public function store(Request $request)
    {
        $this->checkOwner($request);

        $request->validate([
            'code' => 'required|string|max:20|unique:branches,code',
            'name' => 'required|string|max:100',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
        ]);

        $branch = Branch::create([
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'status' => 'ACTIVE',
        ]);

        $this->auditService->log('CREATE_BRANCH', 'Branch', $branch->id, null, $branch->toArray(), $branch->id);

        return response()->json($branch, 201);
    }

    public function update(Request $request, $id)
    {
        $this->checkOwner($request);
        $branch = Branch::findOrFail($id);

        $request->validate([
            'code' => "sometimes|required|string|max:20|unique:branches,code,{$id}",
            'name' => 'sometimes|required|string|max:100',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'status' => 'sometimes|required|in:ACTIVE,INACTIVE',
        ]);

        $oldValues = $branch->toArray();
        $branch->update($request->only(['code', 'name', 'address', 'phone', 'status']));

        $this->auditService->log('UPDATE_BRANCH', 'Branch', $branch->id, $oldValues, $branch->toArray(), $branch->id);

        return response()->json($branch);
    }
}
