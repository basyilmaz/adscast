<?php

namespace App\Domain\Reporting\Services;

use App\Models\ReportDeliveryRun;
use App\Support\Operations\EntityContextResolver;
use Illuminate\Support\Collection;

class ReportRecipientGroupCorrelationAnalyticsService
{
    public function __construct(
        private readonly ReportRecipientGroupSelectionService $selectionService,
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
        $runs = ReportDeliveryRun::query()
            ->where('workspace_id', $workspaceId)
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
                'status',
                'recipients',
                'prepared_at',
                'metadata',
            ]);

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $runs->map(function (ReportDeliveryRun $run): ?array {
                $template = $run->schedule?->template;

                if (! $template || ! $template->entity_type || ! $template->entity_id) {
                    return null;
                }

                return [
                    'type' => $template->entity_type,
                    'id' => $template->entity_id,
                ];
            })->filter()->values()->all(),
        );

        $records = [];

        foreach ($runs as $run) {
            $selected = $this->selectionService->fromRun($run, $run->schedule);
            $recommended = $this->recommendedGroupForRun($run);

            if ($recommended === null) {
                continue;
            }

            $alignment = $this->selectionService->alignment($selected, $recommended);
            $key = $this->selectionService->selectionKey($recommended);
            $entity = $this->entityPayloadForTemplate($run->schedule?->template, $contexts);

            $records[$key] = $this->mergeRecord(
                current: $records[$key] ?? null,
                recommended: $recommended,
                selected: $selected,
                alignment: $alignment,
                run: $run,
                entity: $entity,
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
     * @param  array<string, mixed>  $recommended
     * @param  array<string, mixed>|null  $selected
     * @param  array<string, mixed>  $alignment
     * @param  array<string, string|null>|null  $entity
     * @return array<string, mixed>
     */
    private function mergeRecord(
        ?array $current,
        array $recommended,
        ?array $selected,
        array $alignment,
        ReportDeliveryRun $run,
        ?array $entity,
    ): array {
        $record = $current ?? $this->seedRecord($recommended);
        $record['tracked_runs']++;
        $record['last_run_at'] = $this->maxDateString(
            $record['last_run_at'],
            $run->prepared_at?->toDateTimeString(),
        );

        $isSuccess = in_array($run->status, ['delivered_stub', 'delivered_email'], true);
        $isFailed = $run->status === 'failed';

        if (($alignment['status'] ?? null) === 'aligned') {
            $record['aligned_runs']++;

            if ($isSuccess) {
                $record['aligned_successful_runs']++;
            }

            if ($isFailed) {
                $record['aligned_failed_runs']++;
            }
        } elseif (($alignment['status'] ?? null) === 'override') {
            $record['overridden_runs']++;

            if ($isSuccess) {
                $record['override_successful_runs']++;
            }

            if ($isFailed) {
                $record['override_failed_runs']++;
            }

            if ($selected !== null) {
                $name = (string) ($selected['name'] ?? 'Override Grup');
                $record['override_group_index'][$name] = ($record['override_group_index'][$name] ?? 0) + 1;
            }
        } else {
            $record['unclassified_runs']++;
        }

        $this->attachEntity($record, $entity);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $recommended
     * @return array<string, mixed>
     */
    private function seedRecord(array $recommended): array
    {
        return [
            'key' => $this->selectionService->selectionKey($recommended),
            'label' => (string) $recommended['name'],
            'source_type' => (string) $recommended['source_type'],
            'source_subtype' => $recommended['source_subtype'] ?? null,
            'source_id' => $recommended['source_id'] ?? null,
            'tracked_runs' => 0,
            'aligned_runs' => 0,
            'overridden_runs' => 0,
            'unclassified_runs' => 0,
            'aligned_successful_runs' => 0,
            'aligned_failed_runs' => 0,
            'override_successful_runs' => 0,
            'override_failed_runs' => 0,
            'last_run_at' => null,
            'override_group_index' => [],
            'entity_index' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function finalizeRecord(array $record): array
    {
        $alignedSuccessRate = $record['aligned_runs'] > 0
            ? round(($record['aligned_successful_runs'] / $record['aligned_runs']) * 100, 1)
            : null;
        $overrideSuccessRate = $record['overridden_runs'] > 0
            ? round(($record['override_successful_runs'] / $record['overridden_runs']) * 100, 1)
            : null;
        $successRateDelta = ($alignedSuccessRate !== null && $overrideSuccessRate !== null)
            ? round($alignedSuccessRate - $overrideSuccessRate, 1)
            : null;
        [$correlationStatus, $correlationSummary] = $this->correlationPayload(
            alignedSuccessRate: $alignedSuccessRate,
            overrideSuccessRate: $overrideSuccessRate,
            alignedRuns: $record['aligned_runs'],
            overriddenRuns: $record['overridden_runs'],
        );

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

        $topOverrideGroupLabel = $this->topLabel($record['override_group_index']);
        $topOverrideGroupCount = $topOverrideGroupLabel !== null
            ? (int) ($record['override_group_index'][$topOverrideGroupLabel] ?? 0)
            : 0;

        unset($record['override_group_index'], $record['entity_index']);

        return array_merge($record, [
            'aligned_success_rate' => $alignedSuccessRate,
            'override_success_rate' => $overrideSuccessRate,
            'success_rate_delta' => $successRateDelta,
            'top_override_group_label' => $topOverrideGroupLabel,
            'top_override_group_count' => $topOverrideGroupCount,
            'unique_entities_count' => count($entities),
            'entities' => $entities,
            'correlation_status' => $correlationStatus,
            'correlation_summary' => $correlationSummary,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summaryPayload(Collection $items, int $windowDays): array
    {
        $trackedRuns = (int) $items->sum('tracked_runs');
        $alignedRuns = (int) $items->sum('aligned_runs');
        $overriddenRuns = (int) $items->sum('overridden_runs');
        $alignedSuccessfulRuns = (int) $items->sum('aligned_successful_runs');
        $overrideSuccessfulRuns = (int) $items->sum('override_successful_runs');
        $alignedSuccessRate = $alignedRuns > 0 ? round(($alignedSuccessfulRuns / $alignedRuns) * 100, 1) : null;
        $overrideSuccessRate = $overriddenRuns > 0 ? round(($overrideSuccessfulRuns / $overriddenRuns) * 100, 1) : null;
        $successRateGap = ($alignedSuccessRate !== null && $overrideSuccessRate !== null)
            ? round($alignedSuccessRate - $overrideSuccessRate, 1)
            : null;

        return [
            'tracked_runs' => $trackedRuns,
            'aligned_runs' => $alignedRuns,
            'overridden_runs' => $overriddenRuns,
            'unclassified_runs' => (int) $items->sum('unclassified_runs'),
            'aligned_success_rate' => $alignedSuccessRate,
            'override_success_rate' => $overrideSuccessRate,
            'success_rate_gap' => $successRateGap,
            'recommendation_outperforming_groups' => $items->where('correlation_status', 'recommendation_outperforms')->count(),
            'override_outperforming_groups' => $items->where('correlation_status', 'override_outperforms')->count(),
            'top_positive_recommended_group_label' => data_get(
                $items
                    ->filter(fn (array $item): bool => ($item['success_rate_delta'] ?? null) !== null && $item['success_rate_delta'] > 0)
                    ->sortByDesc('success_rate_delta')
                    ->first(),
                'label',
            ),
            'top_negative_recommended_group_label' => data_get(
                $items
                    ->filter(fn (array $item): bool => ($item['success_rate_delta'] ?? null) !== null && $item['success_rate_delta'] < 0)
                    ->sortBy('success_rate_delta')
                    ->first(),
                'label',
            ),
            'window_days' => $windowDays,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function correlationPayload(
        ?float $alignedSuccessRate,
        ?float $overrideSuccessRate,
        int $alignedRuns,
        int $overriddenRuns,
    ): array {
        if ($alignedRuns === 0 && $overriddenRuns === 0) {
            return ['insufficient_data', 'Bu onerilen grup icin henuz karsilastirilabilir teslim verisi yok.'];
        }

        if ($alignedRuns > 0 && $overriddenRuns === 0) {
            return ['aligned_only', 'Bu grup su an sadece onerilen haliyle kullanilmis.'];
        }

        if ($overriddenRuns > 0 && $alignedRuns === 0) {
            return ['override_only', 'Bu grup su an yalnizca override edilerek kullanilmis.'];
        }

        if ($alignedSuccessRate === null || $overrideSuccessRate === null) {
            return ['insufficient_data', 'Korelasyon analizi icin yeterli run dagilimi yok.'];
        }

        $delta = round($alignedSuccessRate - $overrideSuccessRate, 1);

        if ($delta >= 15.0) {
            return [
                'recommendation_outperforms',
                sprintf('Oneriye uyulan teslimler override seciminden %s puan daha basarili.', number_format($delta, 1, '.', '')),
            ];
        }

        if ($delta <= -15.0) {
            return [
                'override_outperforms',
                sprintf('Override secimi onerilen gruptan %s puan daha basarili.', number_format(abs($delta), 1, '.', '')),
            ];
        }

        return ['neutral', 'Onerilen grup ile override secimi arasinda anlamli bir teslim farki gorulmuyor.'];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string|null>|null  $entity
     */
    private function attachEntity(array &$record, ?array $entity): void
    {
        if ($entity === null || $entity['id'] === null || $entity['type'] === null) {
            return;
        }

        $key = sprintf('%s:%s', $entity['type'], $entity['id']);
        $current = $record['entity_index'][$key] ?? [
            'entity_type' => $entity['type'],
            'entity_id' => $entity['id'],
            'label' => $entity['label'],
            'context_label' => $entity['context_label'],
            'uses_count' => 0,
        ];
        $current['uses_count']++;
        $record['entity_index'][$key] = $current;
    }

    /**
     * @param  \App\Models\ReportTemplate|null  $template
     * @param  array<string, array{entity_label?: string|null, context_label?: string|null}>  $contexts
     * @return array<string, string|null>|null
     */
    private function entityPayloadForTemplate($template, array $contexts): ?array
    {
        if (! $template || ! $template->entity_type || ! $template->entity_id) {
            return null;
        }

        $context = $contexts[$this->entityContextResolver->key($template->entity_type, $template->entity_id)] ?? [];

        return [
            'type' => $template->entity_type,
            'id' => $template->entity_id,
            'label' => $context['entity_label'] ?? $template->name,
            'context_label' => $context['context_label'] ?? null,
        ];
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

    private function compareItems(array $left, array $right): int
    {
        $statusComparison = $this->statusPriority($left['correlation_status']) <=> $this->statusPriority($right['correlation_status']);

        if ($statusComparison !== 0) {
            return $statusComparison;
        }

        $deltaComparison = abs((float) ($right['success_rate_delta'] ?? 0.0)) <=> abs((float) ($left['success_rate_delta'] ?? 0.0));

        if ($deltaComparison !== 0) {
            return $deltaComparison;
        }

        return $right['tracked_runs'] <=> $left['tracked_runs'];
    }

    private function statusPriority(string $status): int
    {
        return match ($status) {
            'recommendation_outperforms' => 0,
            'override_outperforms' => 1,
            'aligned_only' => 2,
            'override_only' => 3,
            'neutral' => 4,
            default => 5,
        };
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
