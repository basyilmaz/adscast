<?php

namespace App\Domain\Meta\Http\Controllers;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Meta\Http\Requests\StoreMetaConnectionRequest;
use App\Domain\Meta\Http\Resources\MetaConnectionResource;
use App\Domain\Meta\Services\MetaConnectionService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaConnectionController
{
    public function __construct(
        private readonly MetaConnectionService $connectionService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $connections = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->latest()
            ->get();

        return new JsonResponse([
            'data' => MetaConnectionResource::collection($connections),
        ]);
    }

    public function store(StoreMetaConnectionRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $user = $request->user();

        $connection = $this->connectionService->upsertConnection(
            $workspace,
            $request->validated(),
        );

        $this->auditLogService->log(
            actor: $user,
            action: 'connection_created_or_refreshed',
            targetType: 'meta_connection',
            targetId: $connection->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'provider' => $connection->provider,
                'api_version' => $connection->api_version,
            ],
        );

        return new JsonResponse([
            'message' => 'Meta baglantisi kaydedildi.',
            'data' => MetaConnectionResource::make($connection),
        ], 201);
    }

    public function adAccounts(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $accounts = MetaAdAccount::query()
            ->where('workspace_id', $workspaceId)
            ->latest('updated_at')
            ->paginate(25);

        return new JsonResponse([
            'data' => $accounts,
        ]);
    }

    public function revoke(Request $request, string $connectionId): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $user = $request->user();

        $connection = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($connectionId);

        $connection = $this->connectionService->revoke($connection);

        $this->auditLogService->log(
            actor: $user,
            action: 'connection_revoked',
            targetType: 'meta_connection',
            targetId: $connection->id,
            organizationId: $workspace?->organization_id,
            workspaceId: $workspace?->id,
            request: $request,
            metadata: [
                'provider' => $connection->provider,
            ],
        );

        return new JsonResponse([
            'message' => 'Meta baglantisi iptal edildi.',
            'data' => MetaConnectionResource::make($connection),
        ]);
    }
}
