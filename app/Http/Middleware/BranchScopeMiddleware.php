<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchScopeMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Owner has global access across all branches
        if ($user->isOwner()) {
            return $next($request);
        }

        // Branch-scoped roles (admin_cabang, kasir, dapur) MUST have an active branch_id
        if (!$user->branch_id) {
            return response()->json([
                'message' => 'Unauthorized: User is not assigned to any branch.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if request contains explicit branch parameter (via route or input)
        $requestBranchId = $request->route('branch') 
            ?? $request->input('branch_id') 
            ?? $request->header('X-Branch-ID');

        if ($requestBranchId && (string)$user->branch_id !== (string)$requestBranchId) {
            return response()->json([
                'message' => 'Forbidden: You do not have permission to access or modify data for another branch.'
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
