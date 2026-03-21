<?php

namespace App\Domain\Reporting\Services;

use App\Models\ReportDeliveryRun;
use App\Support\Operations\EntityContextResolver;
use Illuminate\Support\Collection;

class ReportRecipientGroupFailureReasonAnalyticsService
{
    public function __construct(
        private readonly ReportRecipientGroupSelectionService $selectionService,
        private readonly ReportDeliveryFailureReasonClassifier $failureReasonClassifier,
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
                'schedule:id,workspace_id,report_template_id,delivery_channel,cadence,weekday,month_day,send_time,timezone,recipients,configuration,is_active,next_run_at',
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

        $reasons = [];

        foreach ($runs as $run) {
            $selection = $this->selectionService->fromRun($run, $run->schedule);

            if ($selection === null) {
                continue;
            }

            $reason = $this->failureReasonClassifier->classify(
                $run->error_message,
                is_array($run->metadata ?? null) ? $run->metadata : [],
                $run->delivery_channel,
            );
            $entity = $this->entityPayloadForTemplate($run->schedule?->template, $contexts);
            $key = (string) $reason['code'];

            $reasons[$key] = $this->mergeRunRecord(
                current: $reasons[$key] ?? null,
                reason: $reason,
                selection: $selection,
                run: $run,
                entity: $entity,
            );
        }

        $items = collect($reasons)
            ->map(fn (array $item): array => $this->finalizeRecord($item))
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
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>|null  $entity
     * @return array<string, mixed>
     */
    private function mergeRunRecord(?array $current, array $reason, array $selection, ReportDeliveryRun $run, ?array $entity): array
    {
        $record = $current ?? $this->seedRecord($reason);
        $record['failed_runs']++;
        $record['last_seen_at'] = $this->maxDateString($record['last_seen_at'], $run->prepared_at?->toDateTimeString());

        if ($record['sample_error_message'] === null && filled($reason['sample_error_message'] ?? null)) {
            $record['sample_error_message'] = $reason['sample_error_message'];
        }

        $this->attachGroup($record, $selection, $run->prepared_at?->toDateTimeString());
        $this->attachEntity($record, $entity);

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
            'provider' => (string) $reason['provider'],
            'provider_label' => (string) $reason['provider_label'],
            'delivery_stage' => (string) $reason['delivery_stage'],
            'delivery_stage_label' => (string) $reason['delivery_stage_label'],
            'severity' => (string) $reason['severity'],
            'summary' => (string) $reason['summary'],
            'suggested_action' => (string) $reason['suggested_action'],
            'failed_runs' => 0,
            'last_seen_at' => null,
            'sample_error_message' => $reason['sample_error_message'] ?? null,
            'is_unknown' => (bool) ($reason['is_unknown'] ?? false),
            'group_index' => [],
            'entity_index' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function finalizeRecord(array $record): array
    {
        $groups = collect($record['group_index'])
            ->sort(function (array $left, array $right): int {
                $failedComparison = $right['failed_runs'] <=> $left['failed_runs'];

                if ($failedComparison !== 0) {
                    return $failedComparison;
                }

                return strcmp((string) $left['label'], (string) $right['label']);
            })
            ->values();
        $entities = collect($record['entity_index'])
            ->sort(function (array $left, array $right): int {
                $failedComparison = $right['failed_runs'] <=> $left['failed_runs'];

                if ($failedComparison !== 0) {
                    return $failedComparison;
                }

                return strcmp((string) $left['label'], (string) $right['label']);
            })
            ->values();

        unset($record['group_index'], $record['entity_index']);

        return array_merge($record, [
            'affected_groups_count' => $groups->count(),
            'affected_entities_count' => $entities->count(),
            'top_group_label' => data_get($groups->first(), 'label'),
            'top_entity_label' => data_get($entities->first(), 'label'),
            'groups' => $groups->take(3)->all(),
            'entities' => $entities->take(3)->all(),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summaryPayload(Collection $items, int $windowDays): array
    {
        $affectedGroups = $items
            ->flatMap(fn (array $item): array => array_map(
                fn (array $group): string => (string) $group['key'],
                $item['groups'] ?? [],
            ))
            ->unique()
            ->count();

        $topReason = $items->sortByDesc('failed_runs')->first();

        return [
            'total_reason_types' => $items->count(),
            'total_failed_runs' => (int) $items->sum('failed_runs'),
            'classified_failed_runs' => (int) $items
                ->filter(fn (array $item): bool => ! $item['is_unknown'])
                ->sum('failed_runs'),
            'unknown_failed_runs' => (int) $items
                ->filter(fn (array $item): bool => $item['is_unknown'])
                ->sum('failed_runs'),
            'affected_groups_count' => $affectedGroups,
            'top_reason_label' => data_get($topReason, 'label'),
            'top_reason_count' => (int) data_get($topReason, 'failed_runs', 0),
            'providers_count' => $items->pluck('provider')->filter()->unique()->count(),
            'stages_count' => $items->pluck('delivery_stage')->filter()->unique()->count(),
            'top_provider_label' => $this->topSummaryLabel($items, 'provider_label'),
            'top_stage_label' => $this->topSummaryLabel($items, 'delivery_stage_label'),
            'window_days' => $windowDays,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function topSummaryLabel(Collection $items, string $field): ?string
    {
        $counts = [];

        foreach ($items as $item) {
            $label = (string) ($item[$field] ?? '');

            if ($label === '') {
                continue;
            }

            $counts[$label] = ($counts[$label] ?? 0) + (int) ($item['failed_runs'] ?? 0);
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $selection
     */
    private function attachGroup(array &$record, array $selection, ?string $failedAt): void
    {
        $key = $this->selectionService->selectionKey($selection);
        $current = $record['group_index'][$key] ?? [
            'key' => $key,
            'label' => (string) ($selection['name'] ?? 'Alici grubu'),
            'source_type' => (string) ($selection['source_type'] ?? 'manual'),
            'source_subtype' => $selection['source_subtype'] ?? null,
            'failed_runs' => 0,
            'last_seen_at' => null,
        ];
        $current['failed_runs']++;
        $current['last_seen_at'] = $this->maxDateString($current['last_seen_at'], $failedAt);
        $record['group_index'][$key] = $current;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $entity
     */
    private function attachEntity(array &$record, ?array $entity): void
    {
        if ($entity === null || ! filled($entity['type'] ?? null) || ! filled($entity['id'] ?? null)) {
            return;
        }

        $key = sprintf('%s:%s', $entity['type'], $entity['id']);
        $current = $record['entity_index'][$key] ?? [
            'entity_type' => $entity['type'],
            'entity_id' => $entity['id'],
            'label' => $entity['label'],
            'context_label' => $entity['context_label'],
            'failed_runs' => 0,
        ];
        $current['failed_runs']++;
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
        $failedComparison = $right['failed_runs'] <=> $left['failed_runs'];

        if ($failedComparison !== 0) {
            return $failedComparison;
        }

        $groupComparison = $right['affected_groups_count'] <=> $left['affected_groups_count'];

        if ($groupComparison !== 0) {
            return $groupComparison;
        }

        return strcmp((string) $left['label'], (string) $right['label']);
    }
}
