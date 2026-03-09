<?php

namespace App\Domain\Tenants\Http\Controllers;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Tenants\Http\Requests\SwitchWorkspaceRequest;
use App\Domain\Tenants\Http\Resources\WorkspaceResource;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\UserWorkspaceRole;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $workspaces = $user->workspaces()->with('organization')->get();

        return new JsonResponse([
            'data' => WorkspaceResource::collection($workspaces),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        return new JsonResponse([
            'data' => WorkspaceResource::make($workspace),
        ]);
    }

    public function switch(SwitchWorkspaceRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $workspaceId = $request->string('workspace_id')->toString();

        $membership = UserWorkspaceRole::query()
            ->with('workspace.organization')
            ->with('role')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return new JsonResponse([
                'message' => 'Bu workspace icin yetkiniz yok.',
                'error_code' => 'workspace_membership_missing',
            ], 403);
        }

        /** @var Workspace $workspace */
        $workspace = $membership->workspace;

        $this->auditLogService->log(
            actor: $user,
            action: 'workspace_switched',
            targetType: 'workspace',
            targetId: $workspace->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'role' => $membership->role?->code,
            ],
        );

        return new JsonResponse([
            'message' => 'Workspace secildi.',
            'workspace' => WorkspaceResource::make($workspace),
            'role' => $membership->role?->code,
            'workspace_header' => $workspace->id,
        ]);
    }
}
