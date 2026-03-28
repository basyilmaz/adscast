<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Operations\EntityContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportDecisionSurfaceQueueRecommendationAnalyticsService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly EntityContextResolver $entityContextResolver,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function track(
        Workspace $workspace,
        array $payload,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $metadata = [
            'recommendation_code' => (string) $payload['recommendation_code'],
            'recommendation_label' => (string) $payload['recommendation_label'],
            'suggested_status' => $payload['suggested_status'] ?? null,
            'suggested_status_label' => $this->statusLabel($payload['suggested_status'] ?? null),
            'execution_mode' => (string) $payload['execution_mode'],
            'guidance_variant' => $payload['guidance_variant'] ?? null,
            'guidance_message' => $payload['guidance_message'] ?? null,
            'target_count' => (int) $payload['target_count'],
            'attempted_count' => isset($payload['attempted_count']) ? (int) $payload['attempted_count'] : null,
            'successful_count' => isset($payload['successful_count']) ? (int) $payload['successful_count'] : null,
            'failed_count' => isset($payload['failed_count']) ? (int) $payload['failed_count'] : null,
            'outcome_status' => $this->outcomeStatus(
                executionMode: (string) $payload['execution_mode'],
                attemptedCount: isset($payload['attempted_count']) ? (int) $payload['attempted_count'] : null,
                successfulCount: isset($payload['successful_count']) ? (int) $payload['successful_count'] : null,
                failedCount: isset($payload['failed_count']) ? (int) $payload['failed_count'] : null,
            ),
            'reason_codes' => array_values($payload['reason_codes'] ?? []),
            'priority_group_keys' => array_values($payload['priority_group_keys'] ?? []),
            'target_entity_types' => array_values($payload['target_entity_types'] ?? []),
            'target_surface_keys' => array_values($payload['target_surface_keys'] ?? []),
            'targets' => array_values($payload['targets'] ?? []),
        ];

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_decision_surface_queue_recommendation_tracked',
            targetType: 'report_decision_surface_queue',
            targetId: null,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: $metadata,
            request: $request,
        );

        return $metadata + ['tracked' => true];
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function index(string $workspaceId, int $windowDays = 90): array
    {
        return $this->buildIndex($workspaceId, $windowDays);
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function forEntity(
        string $workspaceId,
        string $entityType,
        string $entityId,
        int $windowDays = 90,
    ): array
    {
        return $this->buildIndex($workspaceId, $windowDays, $entityType, $entityId);
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    private function buildIndex(
        string $workspaceId,
        int $windowDays = 90,
        ?string $entityType = null,
        ?string $entityId = null,
    ): array
    {
        $windowStart = now()->subDays($windowDays);
        $logs = AuditLog::query()
            ->where('workspace_id', $workspaceId)
            ->where('action', 'report_decision_surface_queue_recommendation_tracked')
            ->where('occurred_at', '>=', $windowStart)
            ->get([
                'id',
                'workspace_id',
                'metadata',
                'occurred_at',
            ]);

        $contexts = $this->resolveContexts($workspaceId, $logs);
        $items = [];

        foreach ($logs as $log) {
            $metadata = is_array($log->metadata ?? null) ? $log->metadata : [];
            $recommendationCode = data_get($metadata, 'recommendation_code');

            if (! is_string($recommendationCode) || $recommendationCode === '') {
                continue;
            }

            $targets = $this->targetsFromMetadata($metadata);
            $matchedTargets = $this->matchedTargets($targets, $entityType, $entityId);

            if (($entityType !== null || $entityId !== null) && $matchedTargets === []) {
                continue;
            }

            $record = $items[$recommendationCode] ?? $this->seedRecord($recommendationCode, $metadata);
            $allocationRatio = $this->allocationRatio(
                targets: $targets,
                matchedTargets: $matchedTargets,
                targetCount: (int) data_get($metadata, 'target_count', count($targets)),
                isEntityScoped: $entityType !== null && $entityId !== null,
            );
            $record['tracked_interactions']++;
            $record['last_tracked_at'] = $this->maxDateString(
                $record['last_tracked_at'],
                $log->occurred_at?->toDateTimeString(),
            );

            $executionMode = (string) data_get($metadata, 'execution_mode', 'selection_only');
            $record['selection_only_interactions'] += $executionMode === 'selection_only' ? 1 : 0;
            $record['applied_interactions'] += $executionMode === 'bulk_status_applied' ? 1 : 0;
            $record['total_target_items'] += $this->scaledCount((int) data_get($metadata, 'target_count', count($targets)), $allocationRatio);
            $record['total_attempted_items'] += $this->scaledCount((int) data_get($metadata, 'attempted_count', 0), $allocationRatio);
            $record['total_successful_items'] += $this->scaledCount((int) data_get($metadata, 'successful_count', 0), $allocationRatio);
            $record['total_failed_items'] += $this->scaledCount((int) data_get($metadata, 'failed_count', 0), $allocationRatio);

            if ($executionMode === 'bulk_status_applied') {
                match (data_get($metadata, 'outcome_status')) {
                    'success' => $record['successful_applications']++,
                    'partial' => $record['partial_applications']++,
                    'failed' => $record['failed_applications']++,
                    default => null,
                };
            }

            $this->mergeStringCounts($record['reason_index'], data_get($metadata, 'reason_codes', []));
            $this->mergeStringCounts($record['priority_group_index'], data_get($metadata, 'priority_group_keys', []));
            $this->mergeStringCounts(
                $record['entity_type_index'],
                collect($matchedTargets !== [] ? $matchedTargets : $targets)->pluck('entity_type')->all(),
            );
            $this->mergeStringCounts(
                $record['surface_key_index'],
                collect($matchedTargets !== [] ? $matchedTargets : $targets)->pluck('surface_key')->all(),
            );

            foreach (($matchedTargets !== [] ? $matchedTargets : $targets) as $target) {
                $context = $contexts[$this->entityContextResolver->key($target['entity_type'], $target['entity_id'])] ?? null;

                $this->attachEntity($record, [
                    'entity_type' => $target['entity_type'],
                    'entity_id' => $target['entity_id'],
                    'surface_key' => $target['surface_key'],
                    'label' => $context['entity_label'] ?? null,
                    'context_label' => $context['context_label'] ?? null,
                    'route' => $context['route'] ?? null,
                ]);
            }

            $items[$recommendationCode] = $record;
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

    private function outcomeStatus(
        string $executionMode,
        ?int $attemptedCount,
        ?int $successfulCount,
        ?int $failedCount,
    ): ?string {
        if ($executionMode !== 'bulk_status_applied' || $attemptedCount === null || $successfulCount === null || $failedCount === null) {
            return null;
        }

        if ($attemptedCount < 1) {
            return null;
        }

        if ($successfulCount > 0 && $failedCount === 0) {
            return 'success';
        }

        if ($successfulCount > 0 && $failedCount > 0) {
            return 'partial';
        }

        return 'failed';
    }

    private function statusLabel(?string $status): ?string
    {
        return match ($status) {
            'pending' => 'Beklemede',
            'reviewed' => 'Gozden Gecirildi',
            'completed' => 'Tamamlandi',
            'deferred' => 'Ertelendi',
            default => null,
        };
    }

    private function deferReasonLabel(?string $reasonCode): ?string
    {
        return match ($reasonCode) {
            'none' => 'Nedeni Girilmemis',
            'waiting_client_feedback' => 'Musteri Donusu Bekleniyor',
            'waiting_data_validation' => 'Veri Dogrulamasi Bekleniyor',
            'scheduled_followup' => 'Planli Takip Bekleniyor',
            'blocked_external_dependency' => 'Dis Bagimlilik Engeli',
            'priority_window_shifted' => 'Oncelik Penceresi Degisti',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function seedRecord(string $recommendationCode, array $metadata): array
    {
        return [
            'recommendation_code' => $recommendationCode,
            'label' => data_get($metadata, 'recommendation_label') ?? $recommendationCode,
            'suggested_status' => data_get($metadata, 'suggested_status'),
            'suggested_status_label' => data_get($metadata, 'suggested_status_label'),
            'guidance_variant' => data_get($metadata, 'guidance_variant') ?? 'neutral',
            'guidance_message' => data_get($metadata, 'guidance_message'),
            'tracked_interactions' => 0,
            'selection_only_interactions' => 0,
            'applied_interactions' => 0,
            'successful_applications' => 0,
            'partial_applications' => 0,
            'failed_applications' => 0,
            'total_target_items' => 0.0,
            'total_attempted_items' => 0.0,
            'total_successful_items' => 0.0,
            'total_failed_items' => 0.0,
            'last_tracked_at' => null,
            'reason_index' => [],
            'priority_group_index' => [],
            'entity_type_index' => [],
            'surface_key_index' => [],
            'entity_index' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function finalizeRecord(array $record): array
    {
        $record['total_target_items'] = (int) round((float) $record['total_target_items']);
        $record['total_attempted_items'] = (int) round((float) $record['total_attempted_items']);
        $record['total_successful_items'] = (int) round((float) $record['total_successful_items']);
        $record['total_failed_items'] = (int) round((float) $record['total_failed_items']);
        $applicationSuccessRate = $record['applied_interactions'] > 0
            ? round(($record['successful_applications'] / $record['applied_interactions']) * 100, 1)
            : null;
        $itemSuccessRate = $record['total_attempted_items'] > 0
            ? round(($record['total_successful_items'] / $record['total_attempted_items']) * 100, 1)
            : null;
        $dominantReasonCode = $this->topKey($record['reason_index']);
        $topPriorityGroupKey = $this->topKey($record['priority_group_index']);
        $targetEntityTypes = $this->sortedKeys($record['entity_type_index']);
        $targetSurfaceKeys = $this->sortedKeys($record['surface_key_index']);
        $uniqueEntitiesCount = count($record['entity_index']);
        $entities = collect($record['entity_index'])
            ->sort(function (array $left, array $right): int {
                $usesComparison = $right['uses_count'] <=> $left['uses_count'];

                if ($usesComparison !== 0) {
                    return $usesComparison;
                }

                return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
            })
            ->take(3)
            ->values()
            ->all();

        [$healthStatus, $healthSummary] = $this->healthPayload(
            appliedInteractions: (int) $record['applied_interactions'],
            successfulApplications: (int) $record['successful_applications'],
            partialApplications: (int) $record['partial_applications'],
            failedApplications: (int) $record['failed_applications'],
            totalAttemptedItems: (int) $record['total_attempted_items'],
            totalSuccessfulItems: (int) $record['total_successful_items'],
            totalFailedItems: (int) $record['total_failed_items'],
        );

        unset(
            $record['reason_index'],
            $record['priority_group_index'],
            $record['entity_type_index'],
            $record['surface_key_index'],
            $record['entity_index'],
        );

        return array_merge($record, [
            'application_success_rate' => $applicationSuccessRate,
            'item_success_rate' => $itemSuccessRate,
            'dominant_reason_code' => $dominantReasonCode,
            'top_priority_group_key' => $topPriorityGroupKey,
            'top_priority_group_label' => $this->deferReasonLabel($topPriorityGroupKey),
            'target_entity_types' => $targetEntityTypes,
            'target_surface_keys' => $targetSurfaceKeys,
            'unique_entities_count' => $uniqueEntitiesCount,
            'entities' => $entities,
            'health_status' => $healthStatus,
            'health_summary' => $healthSummary,
            'outcome_summary' => $this->outcomeSummary(
                trackedInteractions: (int) $record['tracked_interactions'],
                selectionOnlyInteractions: (int) $record['selection_only_interactions'],
                appliedInteractions: (int) $record['applied_interactions'],
                totalSuccessfulItems: (int) $record['total_successful_items'],
                totalFailedItems: (int) $record['total_failed_items'],
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
            ->filter(fn (array $item): bool => ($item['total_attempted_items'] ?? 0) > 0 && $item['item_success_rate'] !== null)
            ->sort(function (array $left, array $right): int {
                $rateComparison = ($right['item_success_rate'] ?? -1) <=> ($left['item_success_rate'] ?? -1);

                if ($rateComparison !== 0) {
                    return $rateComparison;
                }

                return ($right['total_attempted_items'] ?? 0) <=> ($left['total_attempted_items'] ?? 0);
            })
            ->first();

        return [
            'tracked_recommendations' => $items->sum('tracked_interactions'),
            'selection_only_recommendations' => $items->sum('selection_only_interactions'),
            'applied_recommendations' => $items->sum('applied_interactions'),
            'successful_applications' => $items->sum('successful_applications'),
            'partial_applications' => $items->sum('partial_applications'),
            'failed_applications' => $items->sum('failed_applications'),
            'top_recommendation_label' => data_get($items->sortByDesc('tracked_interactions')->first(), 'label'),
            'best_success_recommendation_label' => data_get($bestSuccessItem, 'label'),
            'window_days' => $windowDays,
        ];
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array<string, array{entity_type?: string|null, entity_label?: string|null, context_label?: string|null, route?: string|null}>
     */
    private function resolveContexts(string $workspaceId, Collection $logs): array
    {
        $references = $logs
            ->flatMap(function (AuditLog $log): array {
                $metadata = is_array($log->metadata ?? null) ? $log->metadata : [];

                return collect($this->targetsFromMetadata($metadata))
                    ->map(fn (array $target): array => [
                        'type' => $target['entity_type'],
                        'id' => $target['entity_id'],
                    ])
                    ->all();
            })
            ->values()
            ->all();

        return $this->entityContextResolver->resolveMany($workspaceId, $references);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{entity_type: string, entity_id: string, surface_key: string}>
     */
    private function targetsFromMetadata(array $metadata): array
    {
        return collect(data_get($metadata, 'targets', []))
            ->filter(fn ($target): bool => is_array($target))
            ->map(function (array $target): ?array {
                $entityType = is_string($target['entity_type'] ?? null) ? $target['entity_type'] : null;
                $entityId = is_string($target['entity_id'] ?? null) ? $target['entity_id'] : null;
                $surfaceKey = is_string($target['surface_key'] ?? null) ? $target['surface_key'] : null;

                if (! filled($entityType) || ! filled($entityId) || ! filled($surfaceKey)) {
                    return null;
                }

                return [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'surface_key' => $surfaceKey,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $bucket
     * @param  mixed  $values
     */
    private function mergeStringCounts(array &$bucket, mixed $values): void
    {
        if (! is_array($values)) {
            return;
        }

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $bucket[$value] = (int) ($bucket[$value] ?? 0) + 1;
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string|null>  $entity
     */
    private function attachEntity(array &$record, array $entity): void
    {
        if (! filled($entity['entity_type']) || ! filled($entity['entity_id'])) {
            return;
        }

        $key = sprintf('%s:%s:%s', $entity['entity_type'], $entity['entity_id'], $entity['surface_key'] ?? 'unknown');
        $current = $record['entity_index'][$key] ?? [
            'entity_type' => $entity['entity_type'],
            'entity_id' => $entity['entity_id'],
            'surface_key' => $entity['surface_key'],
            'label' => $entity['label'] ?? null,
            'context_label' => $entity['context_label'] ?? null,
            'route' => $this->focusedRoute($entity['route'] ?? null, $entity['surface_key'] ?? null),
            'uses_count' => 0,
        ];
        $current['uses_count']++;
        $record['entity_index'][$key] = $current;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function healthPayload(
        int $appliedInteractions,
        int $successfulApplications,
        int $partialApplications,
        int $failedApplications,
        int $totalAttemptedItems,
        int $totalSuccessfulItems,
        int $totalFailedItems,
    ): array {
        if ($appliedInteractions > 0 && $successfulApplications === 0 && $partialApplications === 0 && $failedApplications > 0) {
            return [
                'critical',
                sprintf('%d toplu uygulamanin hicbiri is kapatmadi.', $appliedInteractions),
            ];
        }

        if ($failedApplications > 0 || $partialApplications > 0) {
            return [
                'warning',
                sprintf(
                    '%d basarili / %d kismi / %d basarisiz toplu uygulama; %d kayit kapandi, %d kayit hata verdi.',
                    $successfulApplications,
                    $partialApplications,
                    $failedApplications,
                    $totalSuccessfulItems,
                    $totalFailedItems,
                ),
            ];
        }

        if ($successfulApplications > 0) {
            return [
                'healthy',
                sprintf(
                    '%d toplu uygulama %d kaydi basariyla guncelledi.',
                    $successfulApplications,
                    $totalSuccessfulItems,
                ),
            ];
        }

        return [
            'neutral',
            'Bu oneride henuz yalnizca secim veya hazirlik etkilesimi var.',
        ];
    }

    private function outcomeSummary(
        int $trackedInteractions,
        int $selectionOnlyInteractions,
        int $appliedInteractions,
        int $totalSuccessfulItems,
        int $totalFailedItems,
    ): string {
        if ($appliedInteractions > 0) {
            return sprintf(
                '%d izleme / %d secim / %d uygulama; %d kayit kapandi, %d kayit hata verdi.',
                $trackedInteractions,
                $selectionOnlyInteractions,
                $appliedInteractions,
                $totalSuccessfulItems,
                $totalFailedItems,
            );
        }

        return sprintf('%d oneride sadece secim veya hazirlik etkilesimi izlendi.', $trackedInteractions);
    }

    private function topKey(array $index): ?string
    {
        if ($index === []) {
            return null;
        }

        arsort($index);

        return array_key_first($index);
    }

    /**
     * @return array<int, string>
     */
    private function sortedKeys(array $index): array
    {
        arsort($index);

        return array_values(array_keys($index));
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

    private function focusedRoute(?string $baseRoute, ?string $surfaceKey): ?string
    {
        if (! $baseRoute || ! $surfaceKey) {
            return $baseRoute;
        }

        $separator = str_contains($baseRoute, '?') ? '&' : '?';

        return sprintf(
            '%s%sfocus_surface=%s#report-decision-surface-%s',
            $baseRoute,
            $separator,
            $surfaceKey,
            $surfaceKey,
        );
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareItems(array $left, array $right): int
    {
        $usageComparison = ($right['tracked_interactions'] ?? 0) <=> ($left['tracked_interactions'] ?? 0);

        if ($usageComparison !== 0) {
            return $usageComparison;
        }

        $successComparison = ($right['total_successful_items'] ?? 0) <=> ($left['total_successful_items'] ?? 0);

        if ($successComparison !== 0) {
            return $successComparison;
        }

        return strcmp((string) $left['label'], (string) $right['label']);
    }

    /**
     * @param  array<int, array{entity_type: string, entity_id: string, surface_key: string}>  $targets
     * @return array<int, array{entity_type: string, entity_id: string, surface_key: string}>
     */
    private function matchedTargets(array $targets, ?string $entityType, ?string $entityId): array
    {
        if ($entityType === null || $entityId === null) {
            return $targets;
        }

        return array_values(array_filter(
            $targets,
            fn (array $target): bool => $target['entity_type'] === $entityType && $target['entity_id'] === $entityId,
        ));
    }

    /**
     * @param  array<int, array{entity_type: string, entity_id: string, surface_key: string}>  $targets
     * @param  array<int, array{entity_type: string, entity_id: string, surface_key: string}>  $matchedTargets
     */
    private function allocationRatio(
        array $targets,
        array $matchedTargets,
        int $targetCount,
        bool $isEntityScoped,
    ): float {
        if (! $isEntityScoped) {
            return 1.0;
        }

        $effectiveTotal = max(1, $targetCount, count($targets));
        $effectiveMatched = max(1, count($matchedTargets));

        return min(1, $effectiveMatched / $effectiveTotal);
    }

    private function scaledCount(int $value, float $ratio): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        return round($value * $ratio, 4);
    }
}
