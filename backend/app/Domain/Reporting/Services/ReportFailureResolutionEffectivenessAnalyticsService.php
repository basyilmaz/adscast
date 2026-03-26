<?php

namespace App\Domain\Reporting\Services;

use App\Models\AuditLog;
use Illuminate\Support\Collection;

class ReportFailureResolutionEffectivenessAnalyticsService
{
    public function __construct(
        private readonly ReportRecipientGroupFailureReasonAnalyticsService $reportRecipientGroupFailureReasonAnalyticsService,
        private readonly ReportDeliveryRetryRecommendationService $reportDeliveryRetryRecommendationService,
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
        $failureReasons = $this->reportRecipientGroupFailureReasonAnalyticsService
            ->index($workspaceId, $windowDays, $entityType, $entityId);

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

        $items = collect($failureReasons['items'] ?? [])
            ->map(function (array $reasonItem) use ($trackedLogs, $executedLogs): array {
                $recommendation = $this->reportDeliveryRetryRecommendationService
                    ->recommendationForFailureReason($reasonItem);
                $reasonCode = (string) $reasonItem['reason_code'];
                $actionMetrics = $this->actionMetricsForReason(
                    reasonCode: $reasonCode,
                    trackedLogs: $trackedLogs,
                    executedLogs: $executedLogs,
                );

                return $this->buildItem($reasonItem, $recommendation, $actionMetrics);
            })
            ->sort(fn (array $left, array $right): int => $this->compareItems($left, $right))
            ->values();

        return [
            'summary' => $this->summaryPayload($items, $windowDays),
            'items' => $items->all(),
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
     * @param  Collection<int, AuditLog>  $trackedLogs
     * @param  Collection<int, AuditLog>  $executedLogs
     * @return array<int, array<string, mixed>>
     */
    private function actionMetricsForReason(
        string $reasonCode,
        Collection $trackedLogs,
        Collection $executedLogs,
    ): array {
        $metrics = [];

        foreach ($trackedLogs as $log) {
            $metadata = is_array($log->metadata ?? null) ? $log->metadata : [];
            $actionCode = data_get($metadata, 'action_code');

            if (! is_string($actionCode) || $actionCode === '' || ! $this->metadataContainsReasonCode($metadata, $reasonCode)) {
                continue;
            }

            $record = $metrics[$actionCode] ?? $this->seedActionMetric($actionCode, $metadata);
            $record['tracked_interactions']++;
            $record['last_seen_at'] = $this->maxDateString($record['last_seen_at'], $log->occurred_at?->toDateTimeString());

            match ($record['action_kind']) {
                'api' => $record['api_interactions']++,
                'focus_tab' => $record['focus_interactions']++,
                'route' => $record['route_interactions']++,
                default => null,
            };

            $metrics[$actionCode] = $record;
        }

        foreach ($executedLogs as $log) {
            $metadata = is_array($log->metadata ?? null) ? $log->metadata : [];
            $actionCode = data_get($metadata, 'action_code');

            if (! is_string($actionCode) || $actionCode === '' || ! $this->metadataContainsReasonCode($metadata, $reasonCode)) {
                continue;
            }

            $record = $metrics[$actionCode] ?? $this->seedActionMetric($actionCode, $metadata);
            $record['executions_count']++;
            $record['last_seen_at'] = $this->maxDateString($record['last_seen_at'], $log->occurred_at?->toDateTimeString());

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

            $metrics[$actionCode] = $record;
        }

        return collect($metrics)
            ->map(fn (array $record): array => $this->finalizeActionMetric($record))
            ->sort(fn (array $left, array $right): int => $this->compareActionMetrics($left, $right))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function seedActionMetric(string $actionCode, array $metadata): array
    {
        return [
            'action_code' => $actionCode,
            'label' => data_get($metadata, 'action_label') ?? $actionCode,
            'action_kind' => data_get($metadata, 'action_kind') ?? 'route',
            'severity' => data_get($metadata, 'severity') ?? 'warning',
            'tracked_interactions' => 0,
            'api_interactions' => 0,
            'route_interactions' => 0,
            'focus_interactions' => 0,
            'executions_count' => 0,
            'successful_executions' => 0,
            'partial_executions' => 0,
            'logged_failed_executions' => 0,
            'last_seen_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function finalizeActionMetric(array $record): array
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

        return array_merge($record, [
            'observed_uses' => $observedUses,
            'api_attempts' => $apiAttempts,
            'failed_executions' => $failedExecutions,
            'success_rate' => $successRate,
        ]);
    }

    /**
     * @param  array<string, mixed>  $reasonItem
     * @param  array<string, mixed>  $recommendation
     * @param  array<int, array<string, mixed>>  $actionMetrics
     * @return array<string, mixed>
     */
    private function buildItem(array $reasonItem, array $recommendation, array $actionMetrics): array
    {
        $recommendedActionCode = (string) ($recommendation['primary_action_code'] ?? '');
        $recommendedMetrics = collect($actionMetrics)
            ->first(fn (array $item): bool => ($item['action_code'] ?? null) === $recommendedActionCode);
        $topObservedAction = collect($actionMetrics)->first();
        [$effectivenessStatus, $effectivenessLabel, $effectivenessSummary] = $this->effectivenessPayload(
            recommendation: $recommendation,
            recommendedMetrics: is_array($recommendedMetrics) ? $recommendedMetrics : null,
            topObservedAction: is_array($topObservedAction) ? $topObservedAction : null,
        );

        return [
            'reason_code' => $reasonItem['reason_code'],
            'label' => $reasonItem['label'],
            'provider' => $reasonItem['provider'],
            'provider_label' => $reasonItem['provider_label'],
            'delivery_stage' => $reasonItem['delivery_stage'],
            'delivery_stage_label' => $reasonItem['delivery_stage_label'],
            'failed_runs' => (int) ($reasonItem['failed_runs'] ?? 0),
            'recommended_action' => [
                'code' => $recommendedActionCode,
                'label' => $this->actionLabelFromRecommendation($recommendation),
                'retry_policy' => $recommendation['retry_policy'],
                'retry_policy_label' => $recommendation['retry_policy_label'],
                'operator_note' => $recommendation['operator_note'],
                'recommended_wait_minutes' => $recommendation['recommended_wait_minutes'],
                'recommended_max_attempts' => $recommendation['recommended_max_attempts'],
            ],
            'observed_actions' => count($actionMetrics),
            'top_observed_action' => $topObservedAction ? [
                'code' => $topObservedAction['action_code'],
                'label' => $topObservedAction['label'],
                'action_kind' => $topObservedAction['action_kind'],
                'observed_uses' => $topObservedAction['observed_uses'],
                'success_rate' => $topObservedAction['success_rate'],
            ] : null,
            'recommended_action_metrics' => $recommendedMetrics ? [
                'observed_uses' => $recommendedMetrics['observed_uses'],
                'api_attempts' => $recommendedMetrics['api_attempts'],
                'successful_executions' => $recommendedMetrics['successful_executions'],
                'partial_executions' => $recommendedMetrics['partial_executions'],
                'failed_executions' => $recommendedMetrics['failed_executions'],
                'success_rate' => $recommendedMetrics['success_rate'],
            ] : null,
            'effectiveness_status' => $effectivenessStatus,
            'effectiveness_label' => $effectivenessLabel,
            'effectiveness_summary' => $effectivenessSummary,
            'actions' => array_map(function (array $item): array {
                return [
                    'action_code' => $item['action_code'],
                    'label' => $item['label'],
                    'action_kind' => $item['action_kind'],
                    'observed_uses' => $item['observed_uses'],
                    'api_attempts' => $item['api_attempts'],
                    'successful_executions' => $item['successful_executions'],
                    'partial_executions' => $item['partial_executions'],
                    'failed_executions' => $item['failed_executions'],
                    'success_rate' => $item['success_rate'],
                    'last_seen_at' => $item['last_seen_at'],
                ];
            }, array_slice($actionMetrics, 0, 3)),
        ];
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @param  array<string, mixed>|null  $recommendedMetrics
     * @param  array<string, mixed>|null  $topObservedAction
     * @return array{0: string, 1: string, 2: string}
     */
    private function effectivenessPayload(
        array $recommendation,
        ?array $recommendedMetrics,
        ?array $topObservedAction,
    ): array {
        if ($recommendedMetrics === null) {
            return [
                'not_applied',
                'Henuz Uygulanmadi',
                'Onerilen duzeltme aksiyonu icin henuz izlenen bir operator davranisi yok.',
            ];
        }

        if (($recommendation['primary_action_code'] ?? null) !== ($topObservedAction['action_code'] ?? null)) {
            return [
                'alternate_action_dominant',
                'Alternatif Aksiyon One Cikiyor',
                sprintf(
                    'Operatorlar en cok %s aksiyonunu kullaniyor; bu, sistemin onerdigi ana duzeltmeden farkli.',
                    (string) ($topObservedAction['label'] ?? 'farkli bir aksiyon'),
                ),
            ];
        }

        if (($recommendedMetrics['action_kind'] ?? null) !== 'api') {
            return [
                'manual_followup_active',
                'Manuel Takip Aktif',
                sprintf(
                    'Onerilen manuel duzeltme aksiyonu %d kez kullanildi. Sonraki adim contact/group tarafinda operator duzeltmesidir.',
                    (int) ($recommendedMetrics['observed_uses'] ?? 0),
                ),
            ];
        }

        $successRate = $recommendedMetrics['success_rate'] ?? null;

        if ($successRate !== null && $successRate >= 80 && (int) ($recommendedMetrics['failed_executions'] ?? 0) === 0) {
            return [
                'working_well',
                'Duzeltme Ise Yariyor',
                sprintf(
                    'Onerilen retry aksiyonu %d denemede %% %s basari uretti.',
                    (int) ($recommendedMetrics['api_attempts'] ?? 0),
                    number_format((float) $successRate, 1, '.', ''),
                ),
            ];
        }

        if ((int) ($recommendedMetrics['successful_executions'] ?? 0) > 0 || (int) ($recommendedMetrics['partial_executions'] ?? 0) > 0) {
            return [
                'partially_working',
                'Kismen Ise Yariyor',
                sprintf(
                    'Onerilen aksiyon calisiyor ancak %d basarisiz execution hala var.',
                    (int) ($recommendedMetrics['failed_executions'] ?? 0),
                ),
            ];
        }

        return [
            'needs_attention',
            'Duzeltme Sonuc Vermiyor',
            'Onerilen aksiyon izleniyor ancak execution sonuclari hala zayif. Daha derin konfigurasyon veya alici duzeltmesi gerekebilir.',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summaryPayload(Collection $items, int $windowDays): array
    {
        return [
            'total_reasons' => $items->count(),
            'reasons_with_observed_fix' => $items->filter(fn (array $item): bool => ($item['observed_actions'] ?? 0) > 0)->count(),
            'working_recommended_fixes' => $items->where('effectiveness_status', 'working_well')->count(),
            'manual_followup_reasons' => $items->where('effectiveness_status', 'manual_followup_active')->count(),
            'stalled_recommended_fixes' => $items->where('effectiveness_status', 'needs_attention')->count(),
            'top_working_fix_label' => data_get(
                $items->first(fn (array $item): bool => ($item['effectiveness_status'] ?? null) === 'working_well'),
                'recommended_action.label',
            ),
            'window_days' => $windowDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function metadataContainsReasonCode(array $metadata, string $reasonCode): bool
    {
        $affectedReasonCodes = data_get($metadata, 'affected_reason_codes');

        if (! is_array($affectedReasonCodes)) {
            return false;
        }

        return collect($affectedReasonCodes)->contains(
            fn ($value): bool => is_string($value) && $value === $reasonCode,
        );
    }

    /**
     * @param  array<string, mixed>  $recommendation
     */
    private function actionLabelFromRecommendation(array $recommendation): string
    {
        return match ($recommendation['primary_action_code'] ?? null) {
            'retry_failed_runs' => 'Basarisiz teslimleri tekrar dene',
            'focus_delivery_profile' => 'Teslim profilini duzelt',
            'review_contact_book' => 'Alici kisilerini kontrol et',
            'review_recipient_groups' => 'Alici grubunu duzelt',
            default => (string) ($recommendation['primary_action_code'] ?? 'Aksiyon'),
        };
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
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareItems(array $left, array $right): int
    {
        $failedComparison = ($right['failed_runs'] ?? 0) <=> ($left['failed_runs'] ?? 0);

        if ($failedComparison !== 0) {
            return $failedComparison;
        }

        $statusOrder = ['working_well' => 0, 'partially_working' => 1, 'manual_followup_active' => 2, 'alternate_action_dominant' => 3, 'needs_attention' => 4, 'not_applied' => 5];
        $statusComparison = ($statusOrder[$left['effectiveness_status']] ?? 99) <=> ($statusOrder[$right['effectiveness_status']] ?? 99);

        if ($statusComparison !== 0) {
            return $statusComparison;
        }

        return strcmp((string) $left['label'], (string) $right['label']);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareActionMetrics(array $left, array $right): int
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
