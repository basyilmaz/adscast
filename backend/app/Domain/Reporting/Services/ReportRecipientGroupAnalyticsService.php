<?php

namespace App\Domain\Reporting\Services;

use App\Models\ReportDeliveryRun;
use App\Models\ReportDeliverySchedule;
use App\Support\Operations\EntityContextResolver;
use Illuminate\Support\Collection;

class ReportRecipientGroupAnalyticsService
{
    public function __construct(
        private readonly ReportRecipientGroupSelectionService $selectionService,
        private readonly EntityContextResolver $entityContextResolver,
    ) {
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function index(string $workspaceId, int $windowDays = 90): array
    {
        $windowStart = now()->subDays($windowDays);
        $schedules = ReportDeliverySchedule::query()
            ->where('workspace_id', $workspaceId)
            ->with('template:id,name,entity_type,entity_id')
            ->get([
                'id',
                'workspace_id',
                'report_template_id',
                'recipients',
                'configuration',
                'is_active',
            ]);

        $runs = ReportDeliveryRun::query()
            ->where('workspace_id', $workspaceId)
            ->where('prepared_at', '>=', $windowStart)
            ->with([
                'schedule:id,workspace_id,report_template_id,recipients,configuration,is_active',
                'schedule.template:id,name,entity_type,entity_id',
            ])
            ->get([
                'id',
                'workspace_id',
                'report_delivery_schedule_id',
                'status',
                'trigger_mode',
                'recipients',
                'prepared_at',
                'metadata',
            ]);

        $contexts = $this->resolveContexts($workspaceId, $schedules, $runs);
        $groups = [];

        foreach ($schedules as $schedule) {
            $selection = $this->selectionService->fromSchedule($schedule);

            if ($selection === null) {
                continue;
            }

            $key = $this->selectionService->selectionKey($selection);
            $entity = $this->entityPayloadForTemplate($schedule->template, $contexts);

            $groups[$key] = $this->mergeGroupRecord(
                current: $groups[$key] ?? null,
                selection: $selection,
                schedule: $schedule,
                entity: $entity,
            );
        }

        foreach ($runs as $run) {
            $selection = $this->selectionService->fromRun($run, $run->schedule);

            if ($selection === null) {
                continue;
            }

            $key = $this->selectionService->selectionKey($selection);
            $entity = $this->entityPayloadForTemplate($run->schedule?->template, $contexts);

            $groups[$key] = $this->mergeRunRecord(
                current: $groups[$key] ?? null,
                selection: $selection,
                run: $run,
                entity: $entity,
            );
        }

        $items = collect($groups)
            ->map(fn (array $item): array => $this->finalizeGroupRecord($item))
            ->sort(fn (array $left, array $right): int => $this->compareItems($left, $right))
            ->values();

        return [
            'summary' => [
                'total_groups' => $items->count(),
                'groups_with_failures' => $items->where('failed_runs', '>', 0)->count(),
                'preset_groups' => $items->where('source_type', 'preset')->count(),
                'segment_groups' => $items->where('source_type', 'segment')->count(),
                'smart_groups' => $items->where('source_type', 'smart')->count(),
                'manual_groups' => $items->where('source_type', 'manual')->count(),
                'active_schedule_groups' => $items->where('active_schedules_count', '>', 0)->count(),
                'tracked_run_groups' => $items->where('run_uses_count', '>', 0)->count(),
                'most_used_group_label' => data_get($items->sortByDesc('run_uses_count')->first(), 'label'),
                'highest_failure_group_label' => $items
                    ->filter(fn (array $item): bool => $item['failed_runs'] > 0)
                    ->sortByDesc('failed_runs')
                    ->pipe(fn (Collection $collection): ?string => data_get($collection->first(), 'label')),
                'window_days' => $windowDays,
            ],
            'items' => $items->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>|null  $entity
     * @return array<string, mixed>
     */
    private function mergeGroupRecord(?array $current, array $selection, ReportDeliverySchedule $schedule, ?array $entity): array
    {
        $record = $current ?? $this->seedRecord($selection);
        $record['configured_schedules_count']++;

        if ($schedule->is_active) {
            $record['active_schedules_count']++;
        }

        $record['sample_recipients'] = $this->mergeStringLists(
            $record['sample_recipients'],
            array_slice(is_array($schedule->recipients ?? null) ? $schedule->recipients : [], 0, 3),
        );

        $this->attachEntity($record, $entity, 0);

        return $record;
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>|null  $entity
     * @return array<string, mixed>
     */
    private function mergeRunRecord(?array $current, array $selection, ReportDeliveryRun $run, ?array $entity): array
    {
        $record = $current ?? $this->seedRecord($selection);
        $record['run_uses_count']++;

        if (in_array($run->status, ['delivered_stub', 'delivered_email'], true)) {
            $record['successful_runs']++;
        }

        if ($run->status === 'failed') {
            $record['failed_runs']++;
            $record['last_failure_at'] = $this->maxDateString(
                $record['last_failure_at'],
                $run->prepared_at?->toDateTimeString(),
            );
        }

        if ($run->trigger_mode === 'retry') {
            $record['retry_runs']++;
        }

        $record['last_used_at'] = $this->maxDateString(
            $record['last_used_at'],
            $run->prepared_at?->toDateTimeString(),
        );
        $record['sample_recipients'] = $this->mergeStringLists(
            $record['sample_recipients'],
            array_slice(is_array($run->recipients ?? null) ? $run->recipients : [], 0, 3),
        );

        $this->attachEntity($record, $entity, 1);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    private function seedRecord(array $selection): array
    {
        return [
            'key' => $this->selectionService->selectionKey($selection),
            'label' => (string) $selection['name'],
            'source_type' => (string) $selection['source_type'],
            'source_subtype' => $selection['source_subtype'] ?? null,
            'source_id' => $selection['source_id'] ?? null,
            'configured_schedules_count' => 0,
            'active_schedules_count' => 0,
            'run_uses_count' => 0,
            'successful_runs' => 0,
            'failed_runs' => 0,
            'retry_runs' => 0,
            'last_used_at' => null,
            'last_failure_at' => null,
            'sample_recipients' => array_values(array_slice(
                is_array($selection['sample_recipients'] ?? null) ? $selection['sample_recipients'] : [],
                0,
                3,
            )),
            'entity_index' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function finalizeGroupRecord(array $record): array
    {
        $successRate = $record['run_uses_count'] > 0
            ? round(($record['successful_runs'] / $record['run_uses_count']) * 100, 1)
            : null;
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

        [$healthStatus, $healthSummary] = $this->healthPayload($record, $successRate);

        unset($record['entity_index']);

        return array_merge($record, [
            'success_rate' => $successRate,
            'health_status' => $healthStatus,
            'health_summary' => $healthSummary,
            'unique_entities_count' => $uniqueEntitiesCount,
            'entities' => $entities,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: string, 1: string}
     */
    private function healthPayload(array $record, ?float $successRate): array
    {
        if ($record['failed_runs'] > 0 && ($successRate === null || $successRate < 70.0)) {
            return [
                'critical',
                sprintf(
                    'Son izleme penceresinde %d run icinde %d basarisiz teslim goruldu.',
                    $record['run_uses_count'],
                    $record['failed_runs'],
                ),
            ];
        }

        if ($record['failed_runs'] > 0) {
            return [
                'warning',
                sprintf(
                    'Teslimler genel olarak calisiyor ancak %d hata kaydi var.',
                    $record['failed_runs'],
                ),
            ];
        }

        if ($record['run_uses_count'] === 0 && $record['configured_schedules_count'] > 0) {
            return [
                'idle',
                'Bu grup schedule baglaminda tanimli, ancak izleme penceresinde henuz run yok.',
            ];
        }

        if ($record['run_uses_count'] > 0) {
            return [
                'healthy',
                sprintf(
                    '%d run boyunca hata gorulmedi. Basari orani %s%%.',
                    $record['run_uses_count'],
                    number_format($successRate ?? 100, 1, '.', ''),
                ),
            ];
        }

        return [
            'idle',
            'Bu grup henuz teslim trafigi uretmedi.',
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $entity
     */
    private function attachEntity(array &$record, ?array $entity, int $weight): void
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
        $current['uses_count'] += $weight;
        $record['entity_index'][$key] = $current;
    }

    /**
     * @param  array<int, string>  $existing
     * @param  array<int, string>  $incoming
     * @return array<int, string>
     */
    private function mergeStringLists(array $existing, array $incoming): array
    {
        return collect(array_merge($existing, $incoming))
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->take(3)
            ->values()
            ->all();
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
     * @param  Collection<int, ReportDeliverySchedule>  $schedules
     * @param  Collection<int, ReportDeliveryRun>  $runs
     * @return array<string, array{entity_label?: string|null, context_label?: string|null}>
     */
    private function resolveContexts(string $workspaceId, Collection $schedules, Collection $runs): array
    {
        return $this->entityContextResolver->resolveMany(
            $workspaceId,
            $schedules
                ->map(function (ReportDeliverySchedule $schedule): ?array {
                    $template = $schedule->template;

                    if (! $template || ! $template->entity_type || ! $template->entity_id) {
                        return null;
                    }

                    return [
                        'type' => $template->entity_type,
                        'id' => $template->entity_id,
                    ];
                })
                ->merge($runs->map(function (ReportDeliveryRun $run): ?array {
                    $template = $run->schedule?->template;

                    if (! $template || ! $template->entity_type || ! $template->entity_id) {
                        return null;
                    }

                    return [
                        'type' => $template->entity_type,
                        'id' => $template->entity_id,
                    ];
                }))
                ->filter()
                ->values()
                ->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareItems(array $left, array $right): int
    {
        $failedComparison = $right['failed_runs'] <=> $left['failed_runs'];

        if ($failedComparison !== 0) {
            return $failedComparison;
        }

        $runComparison = $right['run_uses_count'] <=> $left['run_uses_count'];

        if ($runComparison !== 0) {
            return $runComparison;
        }

        $scheduleComparison = $right['configured_schedules_count'] <=> $left['configured_schedules_count'];

        if ($scheduleComparison !== 0) {
            return $scheduleComparison;
        }

        return strcmp((string) $left['label'], (string) $right['label']);
    }
}
