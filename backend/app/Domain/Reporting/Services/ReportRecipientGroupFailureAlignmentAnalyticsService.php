<?php

namespace App\Domain\Reporting\Services;

use App\Models\ReportDeliveryRun;
use Illuminate\Support\Collection;

class ReportRecipientGroupFailureAlignmentAnalyticsService
{
    public function __construct(
        private readonly ReportRecipientGroupSelectionService $selectionService,
        private readonly ReportDeliveryFailureReasonClassifier $failureReasonClassifier,
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
        $runs = ReportDeliveryRun::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'failed')
            ->where('prepared_at', '>=', $windowStart)
            ->when(
                filled($entityType) && filled($entityId),
                fn ($query) => $query->whereHas('schedule.template', function ($templateQuery) use ($entityType, $entityId): void {
                    $templateQuery
                        ->where('entity_type', $entityType)
                        ->where('entity_id', $entityId);
                }),
            )
            ->with([
                'schedule:id,workspace_id,report_template_id,configuration',
                'schedule.template:id,name,entity_type,entity_id',
            ])
            ->get([
                'id',
                'workspace_id',
                'report_delivery_schedule_id',
                'delivery_channel',
                'status',
                'prepared_at',
                'error_message',
                'metadata',
            ]);

        $records = [];

        foreach ($runs as $run) {
            $selected = $this->selectionService->fromRun($run, $run->schedule);
            $recommended = $this->recommendedGroupForRun($run);
            $alignment = $this->selectionService->alignment($selected, $recommended);
            $reason = $this->failureReasonClassifier->classify(
                $run->error_message,
                is_array($run->metadata ?? null) ? $run->metadata : [],
                $run->delivery_channel,
            );
            $key = (string) $reason['code'];

            $records[$key] = $this->mergeRecord(
                current: $records[$key] ?? null,
                reason: $reason,
                selected: $selected,
                recommended: $recommended,
                alignment: $alignment,
                run: $run,
            );
        }

        $items = collect($records)
            ->map(fn (array $record): array => $this->finalizeRecord($record))
            ->sort(fn (array $left, array $right): int => $this->compareItems($left, $right))
            ->values();

        return [
            'summary' => $this->summaryPayload($items, $windowDays),
            'items' => $items->all(),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function forEntity(string $workspaceId, string $entityType, string $entityId, int $windowDays = 90): array
    {
        return $this->index($workspaceId, $windowDays, $entityType, $entityId);
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $reason
     * @param  array<string, mixed>|null  $selected
     * @param  array<string, mixed>|null  $recommended
     * @param  array<string, mixed>  $alignment
     * @return array<string, mixed>
     */
    private function mergeRecord(
        ?array $current,
        array $reason,
        ?array $selected,
        ?array $recommended,
        array $alignment,
        ReportDeliveryRun $run,
    ): array {
        $record = $current ?? $this->seedRecord($reason);
        $record['tracked_failed_runs']++;
        $record['last_seen_at'] = $this->maxDateString($record['last_seen_at'], $run->prepared_at?->toDateTimeString());

        if ($record['sample_error_message'] === null && filled($reason['sample_error_message'] ?? null)) {
            $record['sample_error_message'] = $reason['sample_error_message'];
        }

        $status = (string) ($alignment['status'] ?? 'unknown');

        if ($recommended !== null) {
            $name = (string) ($recommended['name'] ?? 'Onerilen Grup');
            $record['recommended_group_index'][$name] = ($record['recommended_group_index'][$name] ?? 0) + 1;
        }

        if ($status === 'aligned') {
            $record['aligned_failed_runs']++;
        } elseif ($status === 'override') {
            $record['overridden_failed_runs']++;

            if ($selected !== null) {
                $name = (string) ($selected['name'] ?? 'Override Grup');
                $record['override_group_index'][$name] = ($record['override_group_index'][$name] ?? 0) + 1;
            }
        } elseif ($status === 'no_recommendation') {
            $record['no_recommendation_failed_runs']++;
        } else {
            $record['unknown_failed_runs']++;
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $reason
     * @return array<string, mixed>
     */
    private function seedRecord(array $reason): array
    {
        return [
            'reason_code' => (string) $reason['code'],
            'label' => (string) $reason['label'],
            'severity' => (string) $reason['severity'],
            'summary' => (string) $reason['summary'],
            'suggested_action' => (string) $reason['suggested_action'],
            'sample_error_message' => $reason['sample_error_message'] ?? null,
            'tracked_failed_runs' => 0,
            'aligned_failed_runs' => 0,
            'overridden_failed_runs' => 0,
            'no_recommendation_failed_runs' => 0,
            'unknown_failed_runs' => 0,
            'last_seen_at' => null,
            'recommended_group_index' => [],
            'override_group_index' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function finalizeRecord(array $record): array
    {
        [$dominantStatus, $dominantLabel] = $this->dominantAlignmentPayload(
            alignedFailedRuns: (int) $record['aligned_failed_runs'],
            overriddenFailedRuns: (int) $record['overridden_failed_runs'],
            noRecommendationFailedRuns: (int) $record['no_recommendation_failed_runs'],
            unknownFailedRuns: (int) $record['unknown_failed_runs'],
        );
        $overrideRate = $record['tracked_failed_runs'] > 0
            ? round(($record['overridden_failed_runs'] / $record['tracked_failed_runs']) * 100, 1)
            : null;
        $topRecommendedGroupLabel = $this->topLabel($record['recommended_group_index'] ?? []);
        $topSelectedOverrideGroupLabel = $this->topLabel($record['override_group_index'] ?? []);

        unset($record['recommended_group_index'], $record['override_group_index']);

        return array_merge($record, [
            'override_rate' => $overrideRate,
            'dominant_alignment_status' => $dominantStatus,
            'dominant_alignment_label' => $dominantLabel,
            'top_recommended_group_label' => $topRecommendedGroupLabel,
            'top_selected_override_group_label' => $topSelectedOverrideGroupLabel,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summaryPayload(Collection $items, int $windowDays): array
    {
        return [
            'tracked_failed_runs' => (int) $items->sum('tracked_failed_runs'),
            'aligned_failed_runs' => (int) $items->sum('aligned_failed_runs'),
            'overridden_failed_runs' => (int) $items->sum('overridden_failed_runs'),
            'no_recommendation_failed_runs' => (int) $items->sum('no_recommendation_failed_runs'),
            'unknown_failed_runs' => (int) $items->sum('unknown_failed_runs'),
            'override_dominant_reasons' => $items->where('dominant_alignment_status', 'override_driven')->count(),
            'recommendation_dominant_reasons' => $items->where('dominant_alignment_status', 'recommendation_driven')->count(),
            'top_override_reason_label' => data_get(
                $items->sortByDesc('overridden_failed_runs')->firstWhere('overridden_failed_runs', '>', 0),
                'label',
            ),
            'top_aligned_reason_label' => data_get(
                $items->sortByDesc('aligned_failed_runs')->firstWhere('aligned_failed_runs', '>', 0),
                'label',
            ),
            'window_days' => $windowDays,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dominantAlignmentPayload(
        int $alignedFailedRuns,
        int $overriddenFailedRuns,
        int $noRecommendationFailedRuns,
        int $unknownFailedRuns,
    ): array {
        $counts = [
            'recommendation_driven' => $alignedFailedRuns,
            'override_driven' => $overriddenFailedRuns,
            'no_recommendation' => $noRecommendationFailedRuns,
            'unknown' => $unknownFailedRuns,
        ];
        arsort($counts);
        $topStatus = array_key_first($counts);
        $topCount = $counts[$topStatus] ?? 0;

        if ($topCount === 0) {
            return ['mixed', 'Dagilim Olusmadi'];
        }

        $sameTopCount = count(array_filter($counts, fn (int $count): bool => $count === $topCount));

        if ($sameTopCount > 1) {
            return ['mixed', 'Karisik Dagilim'];
        }

        return match ($topStatus) {
            'recommendation_driven' => ['recommendation_driven', 'Daha Cok Oneriye Uyulurken'],
            'override_driven' => ['override_driven', 'Daha Cok Override Sonrasi'],
            'no_recommendation' => ['no_recommendation', 'Oneri Olmadan'],
            default => ['unknown', 'Secim Izlenemedi'],
        };
    }

    private function compareItems(array $left, array $right): int
    {
        $statusComparison = $this->statusPriority($left['dominant_alignment_status']) <=> $this->statusPriority($right['dominant_alignment_status']);

        if ($statusComparison !== 0) {
            return $statusComparison;
        }

        $failedComparison = $right['tracked_failed_runs'] <=> $left['tracked_failed_runs'];

        if ($failedComparison !== 0) {
            return $failedComparison;
        }

        return strcmp((string) $left['label'], (string) $right['label']);
    }

    private function statusPriority(string $status): int
    {
        return match ($status) {
            'override_driven' => 0,
            'recommendation_driven' => 1,
            'mixed' => 2,
            'no_recommendation' => 3,
            default => 4,
        };
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
     * @return array<string, mixed>|null
     */
    private function recommendedGroupForRun(ReportDeliveryRun $run): ?array
    {
        $metadataGroup = is_array(data_get($run->metadata, 'recommended_recipient_group'))
            ? $this->selectionService->fromArray(data_get($run->metadata, 'recommended_recipient_group'))
            : null;

        if ($metadataGroup !== null) {
            return $metadataGroup;
        }

        return $this->selectionService->fromArray(
            is_array(data_get($run->schedule?->configuration, 'recommended_recipient_group'))
                ? data_get($run->schedule?->configuration, 'recommended_recipient_group')
                : null,
        );
    }
}
