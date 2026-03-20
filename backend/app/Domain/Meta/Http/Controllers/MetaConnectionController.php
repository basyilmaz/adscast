<?php

namespace App\Domain\Meta\Http\Controllers;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Meta\Http\Requests\StoreMetaConnectionRequest;
use App\Domain\Meta\Http\Resources\MetaConnectionResource;
use App\Domain\Meta\Services\MetaConnectorStatusService;
use App\Domain\Meta\Services\MetaConnectionService;
use App\Domain\Reporting\Services\AdAccountQueryService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaConnectionController
{
    public function __construct(
        private readonly MetaConnectionService $connectionService,
        private readonly MetaConnectorStatusService $connectorStatusService,
        private readonly AdAccountQueryService $adAccountQueryService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function connectorStatus(): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->connectorStatusService->describe(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $connections = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->withCount(['adAccounts', 'pages', 'pixels', 'businesses'])
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
                'connection_mode' => data_get($connection->metadata, 'connection_mode'),
            ],
        );

        return new JsonResponse([
            'message' => 'Meta baglantisi kaydedildi.',
            'data' => MetaConnectionResource::make($connection->loadCount(['adAccounts', 'pages', 'pixels', 'businesses'])),
        ], 201);
    }

    public function adAccounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now()->subDays(29);
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        $payload = $this->adAccountQueryService->list($workspaceId, $startDate, $endDate);

        return new JsonResponse([
            'data' => [
                'data' => $payload['items'],
                'summary' => $payload['summary'],
                'range' => $payload['range'],
            ],
        ]);
    }

    public function showAdAccount(Request $request, string $adAccountId): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now()->subDays(29);
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();

        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $account = MetaAdAccount::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($adAccountId);

        return new JsonResponse([
            'data' => $this->adAccountQueryService->detail($account, $startDate, $endDate),
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
