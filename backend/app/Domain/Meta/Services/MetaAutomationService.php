<?php

namespace App\Domain\Meta\Services;

use App\Domain\AI\Services\RecommendationGenerationService;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Rules\Services\RulesEngineService;
use App\Models\AIGeneration;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\SyncRun;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class MetaAutomationService
{
    public function __construct(
        private readonly MetaSyncService $syncService,
        private readonly RulesEngineService $rulesEngineService,
        private readonly RecommendationGenerationService $recommendationGenerationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<int, string> $steps
     * @return array<string, mixed>
     */
    public function run(?string $workspaceId = null, ?string $connectionId = null, array $steps = [], bool $force = false): array
    {
        $steps = $this->normalizeSteps($steps);
        $connections = $this->queryConnections($workspaceId, $connectionId);

        $summary = [
            'connections_considered' => $connections->count(),
            'connections_processed' => 0,
            'connections_failed' => 0,
            'asset_sync_runs' => 0,
            'insights_sync_runs' => 0,
            'rules_evaluations' => 0,
            'recommendation_generations' => 0,
            'results' => [],
        ];

        foreach ($connections as $connection) {
            $workspace = $connection->workspace()->with('organization')->first();

            if (! $workspace) {
                continue;
            }

            try {
                $summary['results'][] = $this->runForConnection($workspace, $connection, $steps, $force);
                $summary['connections_processed']++;
            } catch (Throwable $throwable) {
                $summary['connections_failed']++;
                $summary['results'][] = [
                    'connection_id' => $connection->id,
                    'workspace_id' => $workspace->id,
                    'workspace_slug' => $workspace->slug,
                    'status' => 'failed',
                    'error' => $throwable->getMessage(),
                ];

                $this->auditLogService->log(
                    actor: null,
                    action: 'sync_failed',
                    targetType: 'meta_connection',
                    targetId: $connection->id,
                    organizationId: $workspace->organization_id,
                    workspaceId: $workspace->id,
                    metadata: [
                        'source' => 'scheduler',
                        'steps' => $steps,
                        'error' => $throwable->getMessage(),
                    ],
                );
            }
        }

        foreach ($summary['results'] as $result) {
            $summary['asset_sync_runs'] += (int) ($result['asset_sync_runs'] ?? 0);
            $summary['insights_sync_runs'] += (int) ($result['insights_sync_runs'] ?? 0);
            $summary['rules_evaluations'] += (int) ($result['rules_evaluations'] ?? 0);
            $summary['recommendation_generations'] += (int) ($result['recommendation_generations'] ?? 0);
        }

        return $summary;
    }

    /**
     * @param array<int, string> $steps
     * @return array<string, mixed>
     */
    private function runForConnection(Workspace $workspace, MetaConnection $connection, array $steps, bool $force): array
    {
        $result = [
            'connection_id' => $connection->id,
            'workspace_id' => $workspace->id,
            'workspace_slug' => $workspace->slug,
            'status' => 'completed',
            'asset_sync_runs' => 0,
            'insights_sync_runs' => 0,
            'rules_evaluations' => 0,
            'recommendation_generations' => 0,
            'skipped_steps' => [],
            'accounts_synced' => [],
        ];

        $this->auditLogService->log(
            actor: null,
            action: 'sync_started',
            targetType: 'meta_connection',
            targetId: $connection->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'source' => 'scheduler',
                'steps' => $steps,
                'force' => $force,
            ],
        );

        if (in_array('assets', $steps, true)) {
            if ($force || $this->isConnectionStepDue($connection, 'asset_sync', config('services.meta.schedule.asset_sync_interval_hours', 6))) {
                $syncRun = $this->syncService->runAssetSync($connection);
                $result['asset_sync_runs']++;
                $result['asset_sync_run_id'] = $syncRun->id;
            } else {
                $result['skipped_steps'][] = 'assets';
            }
        }

        $accounts = MetaAdAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('meta_connection_id', $connection->id)
            ->where('is_active', true)
            ->orderBy('account_id')
            ->get();

        if (in_array('insights', $steps, true)) {
            if ($accounts->isEmpty()) {
                $result['skipped_steps'][] = 'insights:no_accounts';
            } elseif ($force || $this->isConnectionStepDue($connection, 'insights_daily_sync', config('services.meta.schedule.insights_sync_interval_hours', 24))) {
                $lookbackDays = max(1, (int) config('services.meta.schedule.insights_lookback_days', 7));
                $endDate = Carbon::now();
                $startDate = Carbon::now()->subDays($lookbackDays - 1);

                foreach ($accounts as $account) {
                    $syncRun = $this->syncService->runInsightsSync(
                        $connection,
                        $account->account_id,
                        $startDate,
                        $endDate,
                    );

                    $result['insights_sync_runs']++;
                    $result['accounts_synced'][] = [
                        'account_id' => $account->account_id,
                        'sync_run_id' => $syncRun->id,
                    ];
                }
            } else {
                $result['skipped_steps'][] = 'insights';
            }
        }

        if (in_array('rules', $steps, true)) {
            $windowDays = max(1, (int) config('services.meta.schedule.rules_window_days', 30));
            $rules = $this->rulesEngineService->evaluateWorkspace(
                $workspace->id,
                Carbon::now()->subDays($windowDays - 1),
                Carbon::now(),
            );

            $result['rules_evaluations'] = count($rules);
        }

        if (in_array('recommendations', $steps, true)) {
            if ($force || $this->isWorkspaceRecommendationDue($workspace, config('services.meta.schedule.recommendation_interval_hours', 24))) {
                $recommendation = $this->recommendationGenerationService->generateForWorkspace($workspace);
                $result['recommendation_generations'] = 1;
                $result['recommendation_id'] = $recommendation['recommendation']->id;
                $result['ai_generation_id'] = $recommendation['generation']->id;
            } else {
                $result['skipped_steps'][] = 'recommendations';
            }
        }

        $this->auditLogService->log(
            actor: null,
            action: 'sync_completed',
            targetType: 'meta_connection',
            targetId: $connection->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'source' => 'scheduler',
                'summary' => $result,
            ],
        );

        return $result;
    }

    /**
     * @param array<int, string> $steps
     * @return array<int, string>
     */
    private function normalizeSteps(array $steps): array
    {
        $normalized = collect($steps)
            ->map(static fn (string $step): string => trim(strtolower($step)))
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            return ['assets', 'insights', 'rules', 'recommendations'];
        }

        return $normalized
            ->intersect(['assets', 'insights', 'rules', 'recommendations'])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, MetaConnection>
     */
    private function queryConnections(?string $workspaceId, ?string $connectionId): Collection
    {
        return MetaConnection::query()
            ->where('status', 'active')
            ->when($workspaceId, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->when($connectionId, fn ($query) => $query->whereKey($connectionId))
            ->with('workspace')
            ->orderBy('workspace_id')
            ->get();
    }

    private function isConnectionStepDue(MetaConnection $connection, string $type, int $intervalHours): bool
    {
        $latestRun = SyncRun::query()
            ->where('meta_connection_id', $connection->id)
            ->where('type', $type)
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first();

        if (! $latestRun?->finished_at) {
            return true;
        }

        return $latestRun->finished_at->lte(Carbon::now()->subHours(max(1, $intervalHours)));
    }

    private function isWorkspaceRecommendationDue(Workspace $workspace, int $intervalHours): bool
    {
        $latestGeneration = AIGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('entity_type', 'workspace')
            ->latest('generated_at')
            ->first();

        if (! $latestGeneration?->generated_at) {
            return true;
        }

        return $latestGeneration->generated_at->lte(Carbon::now()->subHours(max(1, $intervalHours)));
    }
}
