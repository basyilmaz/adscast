<?php

namespace App\Domain\Reporting\Services;

use App\Models\ReportDeliverySchedule;
use App\Support\Operations\EntityContextResolver;

class ReportRecipientGroupAlignmentAnalyticsService
{
    public function __construct(
        private readonly ReportRecipientGroupSelectionService $selectionService,
        private readonly EntityContextResolver $entityContextResolver,
    ) {
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function index(string $workspaceId): array
    {
        $schedules = ReportDeliverySchedule::query()
            ->where('workspace_id', $workspaceId)
            ->with('template:id,name,entity_type,entity_id')
            ->latest()
            ->get([
                'id',
                'workspace_id',
                'report_template_id',
                'cadence',
                'weekday',
                'month_day',
                'send_time',
                'timezone',
                'configuration',
                'is_active',
                'next_run_at',
                'last_status',
                'created_at',
            ]);

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $schedules->map(function (ReportDeliverySchedule $schedule): ?array {
                $template = $schedule->template;

                if (! $template || ! $template->entity_type || ! $template->entity_id) {
                    return null;
                }

                return [
                    'type' => $template->entity_type,
                    'id' => $template->entity_id,
                ];
            })->filter()->values()->all(),
        );

        $recommendedOverrideCounts = [];
        $selectedOverrideCounts = [];

        $items = $schedules
            ->map(function (ReportDeliverySchedule $schedule) use ($contexts, &$recommendedOverrideCounts, &$selectedOverrideCounts): array {
                $configuration = is_array($schedule->configuration ?? null) ? $schedule->configuration : [];
                $selected = $this->selectionService->fromArray(
                    is_array($configuration['recipient_group_selection'] ?? null) ? $configuration['recipient_group_selection'] : null,
                );
                $recommended = $this->selectionService->fromArray(
                    is_array($configuration['recommended_recipient_group'] ?? null) ? $configuration['recommended_recipient_group'] : null,
                );
                $alignment = $this->selectionService->alignment($selected, $recommended);
                $template = $schedule->template;
                $context = $template
                    ? ($contexts[$this->entityContextResolver->key($template->entity_type, $template->entity_id)] ?? [
                        'entity_label' => 'Bilinmeyen varlik',
                        'context_label' => null,
                    ])
                    : [
                        'entity_label' => 'Silinmis sablon',
                        'context_label' => null,
                    ];

                if (($alignment['status'] ?? null) === 'override') {
                    if ($recommended !== null) {
                        $recommendedOverrideCounts[$recommended['name']] = ($recommendedOverrideCounts[$recommended['name']] ?? 0) + 1;
                    }

                    if ($selected !== null) {
                        $selectedOverrideCounts[$selected['name']] = ($selectedOverrideCounts[$selected['name']] ?? 0) + 1;
                    }
                }

                return [
                    'schedule_id' => $schedule->id,
                    'template_name' => $template?->name,
                    'entity_type' => $template?->entity_type,
                    'entity_id' => $template?->entity_id,
                    'entity_label' => $context['entity_label'],
                    'context_label' => $context['context_label'],
                    'cadence' => $schedule->cadence,
                    'cadence_label' => $this->cadenceLabel($schedule),
                    'is_active' => $schedule->is_active,
                    'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
                    'last_status' => $schedule->last_status,
                    'created_at' => $schedule->created_at?->toDateTimeString(),
                    'selected_group' => $selected,
                    'recommended_group' => $recommended,
                    'alignment' => $alignment,
                ];
            })
            ->filter(fn (array $item): bool => $item['selected_group'] !== null || $item['recommended_group'] !== null)
            ->sort(function (array $left, array $right): int {
                $priority = $this->alignmentPriority($left['alignment']['status']) <=> $this->alignmentPriority($right['alignment']['status']);

                if ($priority !== 0) {
                    return $priority;
                }

                return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
            })
            ->values();

        $alignedCount = $items->where('alignment.status', 'aligned')->count();
        $overrideCount = $items->where('alignment.status', 'override')->count();
        $noRecommendationCount = $items->where('alignment.status', 'no_recommendation')->count();
        $unknownCount = $items->whereIn('alignment.status', ['unknown', 'missing_selection'])->count();
        $tracked = $items->count();

        return [
            'summary' => [
                'tracked_decisions' => $tracked,
                'aligned_decisions' => $alignedCount,
                'overridden_decisions' => $overrideCount,
                'no_recommendation_decisions' => $noRecommendationCount,
                'unknown_decisions' => $unknownCount,
                'override_rate' => $tracked > 0 ? round(($overrideCount / $tracked) * 100, 1) : null,
                'top_overridden_recommended_group_label' => $this->topLabel($recommendedOverrideCounts),
                'top_selected_override_group_label' => $this->topLabel($selectedOverrideCounts),
            ],
            'items' => $items->all(),
        ];
    }

    private function cadenceLabel(ReportDeliverySchedule $schedule): string
    {
        return match ($schedule->cadence) {
            'daily' => sprintf('Her gun %s', $schedule->send_time),
            'weekly' => sprintf('Her %d. gun %s', (int) ($schedule->weekday ?? 1), $schedule->send_time),
            'monthly' => sprintf('Her ay %d. gun %s', (int) ($schedule->month_day ?? 1), $schedule->send_time),
            default => $schedule->cadence,
        };
    }

    private function alignmentPriority(?string $status): int
    {
        return match ($status) {
            'override' => 0,
            'missing_selection' => 1,
            'no_recommendation' => 2,
            'aligned' => 3,
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
}
