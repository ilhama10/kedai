<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Services\AuditService;

class UserController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    private function checkOwner(Request $request)
    {
        if (!$request->user()->isOwner()) {
            abort(response()->json(['message' => 'Forbidden: Only Owner can manage users.'], 403));
        }
    }

    public function index(Request $request)
    {
        $this->checkOwner($request);
        $users = User::with('branch')->orderBy('created_at', 'desc')->get();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $this->checkOwner($request);

        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:owner,admin_cabang,kasir,dapur',
            'branch_id' => 'required_unless:role,owner|nullable|exists:branches,id',
            'status' => 'nullable|in:ACTIVE,INACTIVE',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'branch_id' => $request->role === 'owner' ? null : $request->branch_id,
            'status' => $request->status ?? 'ACTIVE',
        ]);

        $this->auditService->log('CREATE_USER', 'User', $user->id, null, $user->toArray(), $user->branch_id);

        return response()->json($user->load('branch'), 201);
    }

    public function update(Request $request, $id)
    {
        $this->checkOwner($request);
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'email' => "sometimes|required|email|unique:users,email,{$id}",
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|in:owner,admin_cabang,kasir,dapur',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'sometimes|required|in:ACTIVE,INACTIVE',
        ]);

        $oldValues = $user->toArray();

        $data = $request->only(['name', 'email', 'role', 'branch_id', 'status']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        if (isset($data['role']) && $data['role'] === 'owner') {
            $data['branch_id'] = null;
        }

        $user->update($data);
        $this->auditService->log('UPDATE_USER', 'User', $user->id, $oldValues, $user->toArray(), $user->branch_id);

        return response()->json($user->load('branch'));
    }

    public function destroy(Request $request, $id)
    {
        $this->checkOwner($request);
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak dapat menghapus akun Anda sendiri.'], 422);
        }

        $oldValues = $user->toArray();
        $user->delete();

        $this->auditService->log('DELETE_USER', 'User', $id, $oldValues, null, $user->branch_id);

        return response()->json(['message' => 'User berhasil dihapus.']);
    }
}
