<?php

namespace App\Domain\Audit\Http\Controllers;

use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $query = AuditLog::query()
            ->where('workspace_id', $workspaceId)
            ->latest('occurred_at');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        return new JsonResponse([
            'data' => $query->paginate(30),
        ]);
    }
}
