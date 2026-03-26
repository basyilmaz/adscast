<?php

namespace App\Domain\Reporting\Services;

use App\Models\AuditLog;
use App\Support\Operations\EntityContextResolver;
use Illuminate\Support\Collection;

class ReportFailureResolutionActionAnalyticsService
{
    public function __construct(
        private readonly EntityContextResolver $entityContextResolver,
    ) {
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function index(
        string $workspaceId,
        int $windowDays = 90,
        ?string $entityType = null,
        ?string $entityId = null,
    ): array {
        $windowStart = now()->subDays($windowDays);

        $trackedLogs = $this->actionQuery(
            workspaceId: $workspaceId,
            action: 'report_failure_resolution_action_tracked',
            windowStart: $windowStart,
            entityType: $entityType,
            entityId: $entityId,
        )->get([
            'id',
            'workspace_id',
            'action',
            'target_type',
            'target_id',
            'metadata',
            'occurred_at',
        ]);

        $executedLogs = $this->actionQuery(
            workspaceId: $workspaceId,
            action: 'report_failure_resolution_action_executed',
            windowStart: $windowStart,
            entityType: $entityType,
            entityId: $entityId,
        )->get([
            'id',
            'workspace_id',
            'action',
            'target_type',
            'target_id',
            'metadata',
            'occurred_at',
        ]);

        $contexts = $this->resolveContexts($workspaceId, $trackedLogs, $executedLogs);
        $items = [];

        foreach ($trackedLogs as $log) {
            $metadata = is_array($log->metadata ?? null) ? $log->metadata : [];
            $actionCode = data_get($metadata, 'action_code');

            if (! is_string($actionCode) || $actionCode === '') {
                continue;
            }

            $record = $items[$actionCode] ?? $this->seedRecord($actionCode, $metadata);
            $record['tracked_interactions']++;
            $record['last_tracked_at'] = $this->maxDateString(
                $record['last_tracked_at'],
                $log->occurred_at?->toDateTimeString(),
            );

            match ($record['action_kind']) {
                'api' => $record['api_interactions']++,
                'focus_tab' => $record['focus_interactions']++,
                'route' => $record['route_interactions']++,
                default => null,
            };

            $this->mergeReasonCodes(
                $record,
                is_array(data_get($metadata, 'affected_reason_codes')) ? data_get($metadata, 'affected_reason_codes') : [],
            );
            $this->attachEntity(
                $record,
                $this->entityPayload($log->target_type, $log->target_id, $contexts),
                1,
            );

            $items[$actionCode] = $record;
        }

        foreach ($executedLogs as $log) {
            $metadata = is_array($log->metadata ?? null) ? $log->metadata : [];
            $actionCode = data_get($metadata, 'action_code');

            if (! is_string($actionCode) || $actionCode === '') {
                continue;
            }

            $record = $items[$actionCode] ?? $this->seedRecord($actionCode, $metadata);
            $record['executions_count']++;
            $record['last_executed_at'] = $this->maxDateString(
                $record['last_executed_at'],
                $log->occurred_at?->toDateTimeString(),
            );
            $this->mergeReasonCodes(
                $record,
                is_array(data_get($metadata, 'affected_reason_codes')) ? data_get($metadata, 'affected_reason_codes') : [],
            );
            $this->attachEntity(
                $record,
                $this->entityPayload($log->target_type, $log->target_id, $contexts),
                1,
            );

            $outcomeStatus = data_get($metadata, 'outcome_status');
            $retriedRuns = (int) data_get($metadata, 'retried_runs', 0);
            $failedRetries = (int) data_get($metadata, 'failed_retries', 0);

            if ($outcomeStatus === 'success' || ($retriedRuns > 0 && $failedRetries === 0)) {
                $record['successful_executions']++;
            } elseif ($outcomeStatus === 'partial' || ($retriedRuns > 0 && $failedRetries > 0)) {
                $record['partial_executions']++;
            } else {
                $record['logged_failed_executions']++;
            }

            $items[$actionCode] = $record;
        }

        $finalizedItems = collect($items)
            ->map(fn (array $record): array => $this->finalizeRecord($record))
            ->sort(fn (array $left, array $right): int => $this->compareItems($left, $right))
            ->values();

        return [
            'summary' => $this->summaryPayload($finalizedItems, $windowDays),
            'items' => $finalizedItems->all(),
        ];
    }

    private function actionQuery(
        string $workspaceId,
        string $action,
        \DateTimeInterface $windowStart,
        ?string $entityType = null,
        ?string $entityId = null,
    ) {
        return AuditLog::query()
            ->where('workspace_id', $workspaceId)
            ->where('action', $action)
            ->where('occurred_at', '>=', $windowStart)
            ->when(
                filled($entityType) && filled($entityId),
                fn ($query) => $query
                    ->where('target_type', $entityType)
                    ->where('target_id', $entityId),
            );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function seedRecord(string $actionCode, array $metadata): array
    {
        return [
            'action_code' => $actionCode,
            'label' => data_get($metadata, 'action_label') ?? $this->defaultActionLabel($actionCode),
            'action_kind' => data_get($metadata, 'action_kind') ?? $this->defaultActionKind($actionCode),
            'severity' => data_get($metadata, 'severity') ?? 'warning',
            'tracked_interactions' => 0,
            'api_interactions' => 0,
            'route_interactions' => 0,
            'focus_interactions' => 0,
            'executions_count' => 0,
            'successful_executions' => 0,
            'partial_executions' => 0,
            'logged_failed_executions' => 0,
            'last_tracked_at' => null,
            'last_executed_at' => null,
            'reason_index' => [],
            'entity_index' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function finalizeRecord(array $record): array
    {
        $apiAttempts = max((int) $record['api_interactions'], (int) $record['executions_count']);
        $observedUses = $apiAttempts + (int) $record['route_interactions'] + (int) $record['focus_interactions'];
        $failedExecutions = max(
            $apiAttempts - (int) $record['successful_executions'] - (int) $record['partial_executions'],
            (int) $record['logged_failed_executions'],
        );
        $successRate = $apiAttempts > 0
            ? round((((int) $record['successful_executions']) / $apiAttempts) * 100, 1)
            : null;

        $topReason = collect($record['reason_index'])
            ->sortByDesc(fn (int $count): int => $count)
            ->keys()
            ->first();
        $topReasonCount = $topReason !== null
            ? (int) ($record['reason_index'][$topReason] ?? 0)
            : 0;
        $uniqueEntitiesCount = count($record['entity_index']);
        $entities = collect($record['entity_index'])
            ->sort(function (array $left, array $right): int {
                $usesComparison = $right['uses_count'] <=> $left['uses_count'];

                if ($usesComparison !== 0) {
                    return $usesComparison;
                }

                return strcmp((string) $left['label'], (string) $right['label']);
            })
            ->take(3)
            ->values()
            ->all();
        [$healthStatus, $healthSummary] = $this->healthPayload(
            actionKind: (string) $record['action_kind'],
            observedUses: $observedUses,
            apiAttempts: $apiAttempts,
            successfulExecutions: (int) $record['successful_executions'],
            partialExecutions: (int) $record['partial_executions'],
            failedExecutions: $failedExecutions,
        );

        unset($record['reason_index'], $record['entity_index']);

        return array_merge($record, [
            'observed_uses' => $observedUses,
            'api_attempts' => $apiAttempts,
            'failed_executions' => $failedExecutions,
            'success_rate' => $successRate,
            'top_reason_code' => $topReason,
            'top_reason_count' => $topReasonCount,
            'unique_entities_count' => $uniqueEntitiesCount,
            'entities' => $entities,
            'health_status' => $healthStatus,
            'health_summary' => $healthSummary,
            'outcome_summary' => $this->outcomeSummary(
                actionKind: (string) $record['action_kind'],
                observedUses: $observedUses,
                apiAttempts: $apiAttempts,
                successfulExecutions: (int) $record['successful_executions'],
                partialExecutions: (int) $record['partial_executions'],
                failedExecutions: $failedExecutions,
            ),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summaryPayload(Collection $items, int $windowDays): array
    {
        $bestSuccessItem = $items
            ->filter(fn (array $item): bool => ($item['api_attempts'] ?? 0) > 0 && $item['success_rate'] !== null)
            ->sort(function (array $left, array $right): int {
                $rateComparison = ($right['success_rate'] ?? -1) <=> ($left['success_rate'] ?? -1);

                if ($rateComparison !== 0) {
                    return $rateComparison;
                }

                return ($right['api_attempts'] ?? 0) <=> ($left['api_attempts'] ?? 0);
            })
            ->first();

        return [
            'observed_actions' => $items->sum('observed_uses'),
            'tracked_interactions' => $items->sum('tracked_interactions'),
            'api_attempts' => $items->sum('api_attempts'),
            'route_interactions' => $items->sum('route_interactions'),
            'focus_interactions' => $items->sum('focus_interactions'),
            'successful_executions' => $items->sum('successful_executions'),
            'partial_executions' => $items->sum('partial_executions'),
            'failed_executions' => $items->sum('failed_executions'),
            'most_used_action_label' => data_get($items->sortByDesc('observed_uses')->first(), 'label'),
            'best_success_action_label' => data_get($bestSuccessItem, 'label'),
            'window_days' => $windowDays,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function healthPayload(
        string $actionKind,
        int $observedUses,
        int $apiAttempts,
        int $successfulExecutions,
        int $partialExecutions,
        int $failedExecutions,
    ): array {
        if ($actionKind === 'api' && $apiAttempts > 0 && $failedExecutions > 0 && $successfulExecutions === 0 && $partialExecutions === 0) {
            return [
                'critical',
                sprintf('%d API denemesinin hicbiri basarili tamamlannamadi.', $apiAttempts),
            ];
        }

        if ($actionKind === 'api' && $failedExecutions > 0) {
            return [
                'warning',
                sprintf(
                    '%d API denemesinde %d basarili, %d kismi ve %d basarisiz sonuc goruldu.',
                    $apiAttempts,
                    $successfulExecutions,
                    $partialExecutions,
                    $failedExecutions,
                ),
            ];
        }

        if ($actionKind === 'api' && $apiAttempts > 0) {
            return [
                'healthy',
                sprintf(
                    '%d API denemesinde %d basarili sonuc kaydedildi.',
                    $apiAttempts,
                    $successfulExecutions,
                ),
            ];
        }

        if ($observedUses > 0) {
            return [
                'neutral',
                sprintf('%d operator etkilessimi kaydedildi.', $observedUses),
            ];
        }

        return [
            'idle',
            'Bu aksiyon icin henuz analytics verisi yok.',
        ];
    }

    private function outcomeSummary(
        string $actionKind,
        int $observedUses,
        int $apiAttempts,
        int $successfulExecutions,
        int $partialExecutions,
        int $failedExecutions,
    ): string {
        if ($actionKind === 'api') {
            return sprintf(
                '%d API denemesi / %d basarili / %d kismi / %d basarisiz.',
                $apiAttempts,
                $successfulExecutions,
                $partialExecutions,
                $failedExecutions,
            );
        }

        return sprintf('%d operator tiklamasi analytics olarak izlendi.', $observedUses);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, mixed>  $reasonCodes
     */
    private function mergeReasonCodes(array &$record, array $reasonCodes): void
    {
        foreach ($reasonCodes as $reasonCode) {
            if (! is_string($reasonCode) || $reasonCode === '') {
                continue;
            }

            $record['reason_index'][$reasonCode] = (int) ($record['reason_index'][$reasonCode] ?? 0) + 1;
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $entity
     */
    private function attachEntity(array &$record, ?array $entity, int $weight): void
    {
        if ($entity === null || $entity['entity_type'] === null || $entity['entity_id'] === null) {
            return;
        }

        $key = sprintf('%s:%s', $entity['entity_type'], $entity['entity_id']);
        $current = $record['entity_index'][$key] ?? [
            'entity_type' => $entity['entity_type'],
            'entity_id' => $entity['entity_id'],
            'label' => $entity['label'],
            'context_label' => $entity['context_label'],
            'uses_count' => 0,
        ];
        $current['uses_count'] += $weight;
        $record['entity_index'][$key] = $current;
    }

    /**
     * @param  Collection<int, AuditLog>  $trackedLogs
     * @param  Collection<int, AuditLog>  $executedLogs
     * @return array<string, array{entity_type?: string|null, entity_label?: string|null, context_label?: string|null, route?: string|null}>
     */
    private function resolveContexts(string $workspaceId, Collection $trackedLogs, Collection $executedLogs): array
    {
        return $this->entityContextResolver->resolveMany(
            $workspaceId,
            $trackedLogs
                ->merge($executedLogs)
                ->map(fn (AuditLog $log): array => [
                    'type' => $log->target_type,
                    'id' => $log->target_id,
                ])
                ->filter(fn (array $reference): bool => filled($reference['type']) && filled($reference['id']))
                ->values()
                ->all(),
        );
    }

    /**
     * @param  array<string, array{entity_type?: string|null, entity_label?: string|null, context_label?: string|null, route?: string|null}>  $contexts
     * @return array<string, string|null>|null
     */
    private function entityPayload(?string $entityType, ?string $entityId, array $contexts): ?array
    {
        if (! filled($entityType) || ! filled($entityId)) {
            return null;
        }

        $context = $contexts[$this->entityContextResolver->key($entityType, $entityId)] ?? null;

        if (! is_array($context)) {
            return null;
        }

        return [
            'entity_type' => $context['entity_type'] ?? $entityType,
            'entity_id' => $entityId,
            'label' => $context['entity_label'] ?? null,
            'context_label' => $context['context_label'] ?? null,
        ];
    }

    private function maxDateString(?string $left, ?string $right): ?string
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        return strcmp($left, $right) >= 0 ? $left : $right;
    }

    private function defaultActionLabel(string $actionCode): string
    {
        return match ($actionCode) {
            'retry_failed_runs' => 'Basarisiz teslimleri tekrar dene',
            'focus_delivery_profile' => 'Teslim profilini duzelt',
            'review_contact_book' => 'Alici kisilerini kontrol et',
            'review_recipient_groups' => 'Alici grubunu duzelt',
            default => $actionCode,
        };
    }

    private function defaultActionKind(string $actionCode): string
    {
        return match ($actionCode) {
            'retry_failed_runs' => 'api',
            'focus_delivery_profile' => 'focus_tab',
            'review_contact_book', 'review_recipient_groups' => 'route',
            default => 'route',
        };
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareItems(array $left, array $right): int
    {
        $usageComparison = ($right['observed_uses'] ?? 0) <=> ($left['observed_uses'] ?? 0);

        if ($usageComparison !== 0) {
            return $usageComparison;
        }

        $successComparison = ($right['successful_executions'] ?? 0) <=> ($left['successful_executions'] ?? 0);

        if ($successComparison !== 0) {
            return $successComparison;
        }

        return strcmp((string) $left['label'], (string) $right['label']);
    }
}
