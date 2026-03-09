<?php

namespace App\Domain\Meta\Http\Controllers;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Meta\Http\Requests\SyncInsightsRequest;
use App\Domain\Meta\Services\MetaSyncService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\MetaConnection;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaSyncController
{
    public function __construct(
        private readonly MetaSyncService $syncService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function syncAssets(Request $request, string $connectionId): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $user = $request->user();

        $connection = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($connectionId);

        $syncRun = $this->syncService->runAssetSync($connection);

        $this->auditLogService->log(
            actor: $user,
            action: 'sync_completed',
            targetType: 'sync_run',
            targetId: $syncRun->id,
            organizationId: $workspace?->organization_id,
            workspaceId: $workspace?->id,
            request: $request,
            metadata: [
                'type' => 'asset_sync',
                'status' => $syncRun->status,
                'summary' => $syncRun->summary,
            ],
        );

        return new JsonResponse([
            'message' => 'Asset senkronizasyonu tamamlandi.',
            'data' => $syncRun,
        ]);
    }

    public function syncInsights(
        SyncInsightsRequest $request,
        string $connectionId,
    ): JsonResponse {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $user = $request->user();

        $connection = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($connectionId);

        $syncRun = $this->syncService->runInsightsSync(
            $connection,
            $request->string('account_id')->toString(),
            Carbon::parse($request->string('start_date')->toString()),
            Carbon::parse($request->string('end_date')->toString()),
        );

        $this->auditLogService->log(
            actor: $user,
            action: 'sync_completed',
            targetType: 'sync_run',
            targetId: $syncRun->id,
            organizationId: $workspace?->organization_id,
            workspaceId: $workspace?->id,
            request: $request,
            metadata: [
                'type' => 'insights_daily_sync',
                'status' => $syncRun->status,
                'summary' => $syncRun->summary,
            ],
        );

        return new JsonResponse([
            'message' => 'Insights senkronizasyonu tamamlandi.',
            'data' => $syncRun,
        ]);
    }
}
