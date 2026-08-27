<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\AuditService;

class AuthController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau password salah.'
            ], 401);
        }

        $user = User::with('branch')->where('email', $request->email)->first();

        if ($user->status !== 'ACTIVE') {
            Auth::logout();
            return response()->json([
                'message' => 'Akun pengguna ini tidak aktif.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->auditService->log('LOGIN', 'User', $user->id, null, ['email' => $user->email], $user->branch_id);

        return response()->json([
            'message' => 'Login berhasil.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'branch_id' => $user->branch_id,
                'branch' => $user->branch,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('branch');
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'branch_id' => $user->branch_id,
                'branch' => $user->branch,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $this->auditService->log('LOGOUT', 'User', $user->id, null, null, $user->branch_id);
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.'
        ]);
    }
}
