<?php

namespace App\Domain\Audit\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogService
{
    public function log(
        ?User $actor,
        string $action,
        string $targetType,
        ?string $targetId,
        ?string $organizationId = null,
        ?string $workspaceId = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'organization_id' => $organizationId,
            'workspace_id' => $workspaceId,
            'actor_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at' => Carbon::now(),
        ]);
    }
}
