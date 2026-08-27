<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    public function log(
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $branchId = null
    ): AuditLog {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'branch_id' => $branchId ?? ($user ? $user->branch_id : null),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
