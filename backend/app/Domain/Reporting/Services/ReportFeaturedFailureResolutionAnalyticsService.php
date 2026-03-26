<?php

namespace App\Domain\Reporting\Services;

use App\Models\AuditLog;
use App\Support\Operations\EntityContextResolver;
use Illuminate\Support\Collection;

class ReportFeaturedFailureResolutionAnalyticsService
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
            'target_type',
            'target_id',
            'metadata',
            'occurred_at',
        ]);

        $contexts = $this->resolveContexts($workspaceId, $trackedLogs, $executedLogs);
        $items = [];

        foreach ($trackedLogs as $log) {
            $metadata = is_array($log->metadata ?? null) ? $log->metadata : [];
            $featured = $this->featuredPayload($metadata);

            if ($featured === null) {
                continue;
            }

            $key = $this->recordKey($featured);
            $record = $items[$key] ?? $this->seedRecord($featured);
            $record['tracked_interactions']++;
            $record['last_seen_at'] = $this->maxDateString(
                $record['last_seen_at'],
                $log->occurred_at?->toDateTimeString(),
            );

            $matchesFeatured = (bool) ($featured['matches_featured_failure_resolution'] ?? false);
            $actionKind = (string) ($metadata['action_kind'] ?? 'route');
            $actionLabel = is_string($metadata['action_label'] ?? null) ? $metadata['action_label'] : null;

            if ($matchesFeatured) {
                $record['featured_interactions']++;

                if ($actionKind === 'api') {
                    $record['featured_api_interactions']++;
                }
            } else {
                $record['override_interactions']++;

                if ($actionKind === 'api') {
                    $record['override_api_interactions']++;
                }

                if ($actionLabel !== null && $actionLabel !== '') {
                    $record['override_action_index'][$actionLabel] = (int) ($record['override_action_index'][$actionLabel] ?? 0) + 1;
                }
            }

            if ($actionKind === 'route') {
                $record['route_interactions']++;
            } elseif ($actionKind === 'focus_tab') {
                $record['focus_interactions']++;
            }

            $this->attachEntity(
                $record,
                $this->entityPayload($log->target_type, $log->target_id, $contexts),
                1,
            );

            $items[$key] = $record;
        }

        foreach ($executedLogs as $log) {
            $metadata = is_array($log->metadata ?? null) ? $log->metadata : [];
            $featured = $this->featuredPayload($metadata);

            if ($featured === null) {
                continue;
            }

            $key = $this->recordKey($featured);
            $record = $items[$key] ?? $this->seedRecord($featured);
            $record['last_seen_at'] = $this->maxDateString(
                $record['last_seen_at'],
                $log->occurred_at?->toDateTimeString(),
            );

            $matchesFeatured = (bool) ($featured['matches_featured_failure_resolution'] ?? false);
            $outcomeStatus = (string) ($metadata['outcome_status'] ?? 'failed');
            $retriedRuns = (int) ($metadata['retried_runs'] ?? 0);
            $failedRetries = (int) ($metadata['failed_retries'] ?? 0);

            if ($matchesFeatured) {
                $record['featured_executions_count']++;
            } else {
                $record['override_executions_count']++;
            }

            if ($outcomeStatus === 'success' || ($retriedRuns > 0 && $failedRetries === 0)) {
                if ($matchesFeatured) {
                    $record['successful_featured_executions']++;
                } else {
                    $record['successful_override_executions']++;
                }
            } elseif ($outcomeStatus === 'partial' || ($retriedRuns > 0 && $failedRetries > 0)) {
                if ($matchesFeatured) {
                    $record['partial_featured_executions']++;
                } else {
                    $record['partial_override_executions']++;
                }
            } else {
                if ($matchesFeatured) {
                    $record['logged_failed_featured_executions']++;
                } else {
                    $record['logged_failed_override_executions']++;
                }
            }

            $this->attachEntity(
                $record,
                $this->entityPayload($log->target_type, $log->target_id, $contexts),
                1,
            );

            $items[$key] = $record;
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
     * @return array<string, mixed>|null
     */
    private function featuredPayload(array $metadata): ?array
    {
        $available = (bool) ($metadata['featured_failure_resolution_available'] ?? false);
        $featuredActionCode = is_string($metadata['featured_failure_resolution_action_code'] ?? null)
            ? $metadata['featured_failure_resolution_action_code']
            : null;

        if (! $available || $featuredActionCode === null || $featuredActionCode === '') {
            return null;
        }

        return [
            'matches_featured_failure_resolution' => (bool) ($metadata['matches_featured_failure_resolution'] ?? false),
            'featured_action_code' => $featuredActionCode,
            'featured_action_label' => (string) ($metadata['featured_failure_resolution_action_label'] ?? $featuredActionCode),
            'featured_status' => (string) ($metadata['featured_failure_resolution_status'] ?? 'available_action'),
            'featured_status_label' => (string) ($metadata['featured_failure_resolution_status_label'] ?? 'Hazir Aksiyon'),
            'featured_source' => (string) ($metadata['featured_failure_resolution_source'] ?? 'action_inventory'),
            'reason_code' => (string) ($metadata['featured_failure_resolution_reason_code'] ?? 'unknown_failure'),
            'reason_label' => (string) ($metadata['featured_failure_resolution_reason_label'] ?? 'Bilinmeyen Hata'),
            'provider_label' => $metadata['featured_failure_resolution_provider_label'] ?? null,
            'delivery_stage_label' => $metadata['featured_failure_resolution_delivery_stage_label'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $featured
     * @return array<string, mixed>
     */
    private function seedRecord(array $featured): array
    {
        return [
            'featured_action_code' => $featured['featured_action_code'],
            'featured_action_label' => $featured['featured_action_label'],
            'featured_status' => $featured['featured_status'],
            'featured_status_label' => $featured['featured_status_label'],
            'featured_source' => $featured['featured_source'],
            'reason_code' => $featured['reason_code'],
            'reason_label' => $featured['reason_label'],
            'provider_label' => $featured['provider_label'],
            'delivery_stage_label' => $featured['delivery_stage_label'],
            'tracked_interactions' => 0,
            'featured_interactions' => 0,
            'override_interactions' => 0,
            'route_interactions' => 0,
            'focus_interactions' => 0,
            'featured_api_interactions' => 0,
            'override_api_interactions' => 0,
            'featured_executions_count' => 0,
            'override_executions_count' => 0,
            'successful_featured_executions' => 0,
            'partial_featured_executions' => 0,
            'logged_failed_featured_executions' => 0,
            'successful_override_executions' => 0,
            'partial_override_executions' => 0,
            'logged_failed_override_executions' => 0,
            'last_seen_at' => null,
            'override_action_index' => [],
            'entity_index' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function finalizeRecord(array $record): array
    {
        $featuredApiAttempts = max(
            (int) $record['featured_api_interactions'],
            (int) $record['featured_executions_count'],
        );
        $overrideApiAttempts = max(
            (int) $record['override_api_interactions'],
            (int) $record['override_executions_count'],
        );
        $failedFeaturedExecutions = max(
            $featuredApiAttempts - (int) $record['successful_featured_executions'] - (int) $record['partial_featured_executions'],
            (int) $record['logged_failed_featured_executions'],
        );
        $failedOverrideExecutions = max(
            $overrideApiAttempts - (int) $record['successful_override_executions'] - (int) $record['partial_override_executions'],
            (int) $record['logged_failed_override_executions'],
        );
        $followRate = (int) $record['tracked_interactions'] > 0
            ? round(((int) $record['featured_interactions'] / (int) $record['tracked_interactions']) * 100, 1)
            : null;
        $featuredSuccessRate = $featuredApiAttempts > 0
            ? round((((int) $record['successful_featured_executions']) / $featuredApiAttempts) * 100, 1)
            : null;
        $overrideSuccessRate = $overrideApiAttempts > 0
            ? round((((int) $record['successful_override_executions']) / $overrideApiAttempts) * 100, 1)
            : null;

        $topOverrideActionLabel = $this->topLabel($record['override_action_index']);
        $topEntities = collect($record['entity_index'])
            ->sort(function (array $left, array $right): int {
                $usesComparison = ($right['uses_count'] ?? 0) <=> ($left['uses_count'] ?? 0);

                if ($usesComparison !== 0) {
                    return $usesComparison;
                }

                return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
            })
            ->take(3)
            ->values()
            ->all();

        unset($record['override_action_index'], $record['entity_index']);

        return array_merge($record, [
            'featured_api_attempts' => $featuredApiAttempts,
            'override_api_attempts' => $overrideApiAttempts,
            'failed_featured_executions' => $failedFeaturedExecutions,
            'failed_override_executions' => $failedOverrideExecutions,
            'follow_rate' => $followRate,
            'featured_success_rate' => $featuredSuccessRate,
            'override_success_rate' => $overrideSuccessRate,
            'top_override_action_label' => $topOverrideActionLabel,
            'top_entity' => $topEntities[0] ?? null,
            'entities' => $topEntities,
            'usage_summary' => $this->usageSummary(
                featuredInteractions: (int) $record['featured_interactions'],
                overrideInteractions: (int) $record['override_interactions'],
                featuredApiAttempts: $featuredApiAttempts,
                overrideApiAttempts: $overrideApiAttempts,
                featuredSuccessRate: $featuredSuccessRate,
                overrideSuccessRate: $overrideSuccessRate,
            ),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summaryPayload(Collection $items, int $windowDays): array
    {
        $topFeaturedAction = $items
            ->sortByDesc('tracked_interactions')
            ->first();
        $topOverriddenFeaturedAction = $items
            ->sortByDesc('override_interactions')
            ->firstWhere('override_interactions', '>', 0);
        $bestFollowedFeaturedAction = $items
            ->filter(fn (array $item): bool => ($item['featured_api_attempts'] ?? 0) > 0 && $item['featured_success_rate'] !== null)
            ->sort(function (array $left, array $right): int {
                $rateComparison = ($right['featured_success_rate'] ?? -1) <=> ($left['featured_success_rate'] ?? -1);

                if ($rateComparison !== 0) {
                    return $rateComparison;
                }

                return ($right['featured_api_attempts'] ?? 0) <=> ($left['featured_api_attempts'] ?? 0);
            })
            ->first();

        return [
            'tracked_interactions' => $items->sum('tracked_interactions'),
            'featured_interactions' => $items->sum('featured_interactions'),
            'override_interactions' => $items->sum('override_interactions'),
            'featured_api_attempts' => $items->sum('featured_api_attempts'),
            'override_api_attempts' => $items->sum('override_api_attempts'),
            'successful_featured_executions' => $items->sum('successful_featured_executions'),
            'partial_featured_executions' => $items->sum('partial_featured_executions'),
            'failed_featured_executions' => $items->sum('failed_featured_executions'),
            'successful_override_executions' => $items->sum('successful_override_executions'),
            'partial_override_executions' => $items->sum('partial_override_executions'),
            'failed_override_executions' => $items->sum('failed_override_executions'),
            'top_featured_action_label' => data_get($topFeaturedAction, 'featured_action_label'),
            'top_overridden_featured_action_label' => data_get($topOverriddenFeaturedAction, 'featured_action_label'),
            'best_followed_featured_action_label' => data_get($bestFollowedFeaturedAction, 'featured_action_label'),
            'window_days' => $windowDays,
        ];
    }

    private function recordKey(array $featured): string
    {
        return implode('|', [
            (string) $featured['featured_action_code'],
            (string) $featured['reason_code'],
            (string) $featured['featured_status'],
        ]);
    }

    /**
     * @param  array<string, int>  $items
     */
    private function topLabel(array $items): ?string
    {
        if ($items === []) {
            return null;
        }

        arsort($items);

        return array_key_first($items);
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

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $entity
     */
    private function attachEntity(array &$record, ?array $entity, int $weight): void
    {
        if ($entity === null || ! filled($entity['entity_type'] ?? null) || ! filled($entity['entity_id'] ?? null)) {
            return;
        }

        $key = sprintf('%s:%s', $entity['entity_type'], $entity['entity_id']);
        $current = $record['entity_index'][$key] ?? [
            'entity_type' => $entity['entity_type'],
            'entity_id' => $entity['entity_id'],
            'label' => $entity['label'],
            'context_label' => $entity['context_label'],
            'route' => $entity['route'],
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
            'route' => $context['route'] ?? null,
        ];
    }

    private function usageSummary(
        int $featuredInteractions,
        int $overrideInteractions,
        int $featuredApiAttempts,
        int $overrideApiAttempts,
        ?float $featuredSuccessRate,
        ?float $overrideSuccessRate,
    ): string {
        $parts = [
            sprintf('%d oneriyi takip', $featuredInteractions),
            sprintf('%d override', $overrideInteractions),
        ];

        if ($featuredApiAttempts > 0 && $featuredSuccessRate !== null) {
            $parts[] = sprintf('oneri basari %%%s', number_format($featuredSuccessRate, 1, '.', ''));
        }

        if ($overrideApiAttempts > 0 && $overrideSuccessRate !== null) {
            $parts[] = sprintf('override basari %%%s', number_format($overrideSuccessRate, 1, '.', ''));
        }

        return implode(' / ', $parts);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareItems(array $left, array $right): int
    {
        $trackedComparison = ($right['tracked_interactions'] ?? 0) <=> ($left['tracked_interactions'] ?? 0);

        if ($trackedComparison !== 0) {
            return $trackedComparison;
        }

        $featuredComparison = ($right['successful_featured_executions'] ?? 0) <=> ($left['successful_featured_executions'] ?? 0);

        if ($featuredComparison !== 0) {
            return $featuredComparison;
        }

        return strcmp((string) $left['reason_label'], (string) $right['reason_label']);
    }
}
