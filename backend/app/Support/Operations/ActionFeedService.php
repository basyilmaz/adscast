<?php

namespace App\Support\Operations;

use App\Models\Alert;
use App\Models\Recommendation;
use Illuminate\Support\Collection;

class ActionFeedService
{
    public function __construct(
        private readonly EntityContextResolver $entityContextResolver,
    ) {
    }

    /**
     * @param  Collection<int, Alert>  $alerts
     * @return array{items: array<int, array<string, mixed>>, summary: array<string, int|string|null>, entity_groups: array<int, array<string, mixed>>, next_best_actions: array<int, array<string, mixed>>}
     */
    public function presentAlerts(string $workspaceId, Collection $alerts): array
    {
        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $alerts->map(fn (Alert $alert): array => [
                'type' => $alert->entity_type,
                'id' => $alert->entity_id,
            ])->all(),
        );

        $items = $alerts
            ->map(fn (Alert $alert): array => $this->presentAlert($alert, $contexts))
            ->values();

        return [
            'items' => $items->all(),
            'summary' => [
                'open_total' => $items->count(),
                'critical_total' => $items->filter(fn (array $item): bool => $this->priorityRank($item['severity']) >= 3)->count(),
                'entity_types' => $items->pluck('entity_type')->filter()->unique()->count(),
                'top_recommended_action' => $items->sortByDesc(fn (array $item): int => $this->priorityRank($item['severity']))->first()['next_step'] ?? null,
            ],
            'entity_groups' => $this->groupAlertsByEntityType($items),
            'next_best_actions' => $this->buildAlertActions($items),
        ];
    }

    /**
     * @param  Collection<int, Recommendation>  $recommendations
     * @return array{items: array<int, array<string, mixed>>, summary: array<string, int|string|null>, entity_groups: array<int, array<string, mixed>>, next_best_actions: array<int, array<string, mixed>>}
     */
    public function presentRecommendations(string $workspaceId, Collection $recommendations): array
    {
        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $recommendations->map(fn (Recommendation $recommendation): array => [
                'type' => $recommendation->target_type,
                'id' => $recommendation->target_id,
            ])->all(),
        );

        $items = $recommendations
            ->map(fn (Recommendation $recommendation): array => $this->presentRecommendation($recommendation, $contexts))
            ->values();

        return [
            'items' => $items->all(),
            'summary' => [
                'open_total' => $items->count(),
                'high_priority_total' => $items->filter(fn (array $item): bool => $this->priorityRank($item['priority']) >= 3)->count(),
                'entity_types' => $items->pluck('entity_type')->filter()->unique()->count(),
                'manual_review_total' => $items->filter(fn (array $item): bool => (bool) data_get($item, 'action_status.manual_review_required', false))->count(),
            ],
            'entity_groups' => $this->groupRecommendationsByEntityType($items),
            'next_best_actions' => $this->buildRecommendationActions($items),
        ];
    }

    /**
     * @param  Collection<int, Alert>  $alerts
     * @param  Collection<int, Recommendation>  $recommendations
     * @return array<int, array<string, mixed>>
     */
    public function nextBestActions(string $workspaceId, Collection $alerts, Collection $recommendations, int $limit = 5): array
    {
        $alertPayload = $this->presentAlerts($workspaceId, $alerts);
        $recommendationPayload = $this->presentRecommendations($workspaceId, $recommendations);

        return collect($alertPayload['next_best_actions'])
            ->concat($recommendationPayload['next_best_actions'])
            ->sortByDesc(function (array $item): array {
                return [
                    $this->priorityRank((string) $item['priority']),
                    (string) ($item['detected_at'] ?? $item['generated_at'] ?? ''),
                ];
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, string|null>>  $contexts
     * @return array<string, mixed>
     */
    public function presentAlert(Alert $alert, array $contexts = []): array
    {
        $context = $contexts[$this->entityContextResolver->key($alert->entity_type, $alert->entity_id)] ?? [
            'entity_type' => $alert->entity_type,
            'entity_label' => 'Bilinmeyen varlik',
            'context_label' => null,
            'route' => null,
        ];

        $impactSummary = $alert->explanation ?: $this->fallbackAlertImpact($alert);
        $nextStep = $alert->recommended_action ?: 'Kok nedeni inceleyip kontrollu aksiyon alin.';

        return [
            'id' => $alert->id,
            'code' => $alert->code,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'summary' => $alert->summary,
            'explanation' => $alert->explanation,
            'confidence' => $alert->confidence !== null ? (float) $alert->confidence : null,
            'date_detected' => optional($alert->date_detected)->toDateString(),
            'entity_type' => $context['entity_type'],
            'entity_label' => $context['entity_label'],
            'context_label' => $context['context_label'],
            'route' => $context['route'],
            'why_it_matters' => $impactSummary,
            'impact_summary' => $impactSummary,
            'recommended_action' => $alert->recommended_action,
            'next_step' => $nextStep,
        ];
    }

    /**
     * @param  array<string, array<string, string|null>>  $contexts
     * @return array<string, mixed>
     */
    public function presentRecommendation(Recommendation $recommendation, array $contexts = []): array
    {
        $context = $contexts[$this->entityContextResolver->key($recommendation->target_type, $recommendation->target_id)] ?? [
            'entity_type' => $recommendation->target_type,
            'entity_label' => 'Bilinmeyen varlik',
            'context_label' => null,
            'route' => null,
        ];

        $metadata = is_array($recommendation->metadata) ? $recommendation->metadata : [];
        $operatorSummary = (string) ($metadata['operator_notes'] ?? $recommendation->details ?? $recommendation->summary);
        $clientSummary = (string) ($metadata['client_friendly_summary'] ?? $recommendation->summary);

        return [
            'id' => $recommendation->id,
            'summary' => $recommendation->summary,
            'details' => $recommendation->details,
            'priority' => $recommendation->priority,
            'status' => $recommendation->status,
            'source' => $recommendation->source,
            'action_type' => $recommendation->action_type,
            'generated_at' => optional($recommendation->generated_at)->toDateTimeString(),
            'entity_type' => $context['entity_type'],
            'entity_label' => $context['entity_label'],
            'context_label' => $context['context_label'],
            'route' => $context['route'],
            'operator_view' => [
                'summary' => $operatorSummary,
                'budget_note' => $metadata['budget_note'] ?? null,
                'creative_note' => $metadata['creative_note'] ?? null,
                'targeting_note' => $metadata['targeting_note'] ?? null,
                'landing_page_note' => $metadata['landing_page_note'] ?? null,
                'next_test' => $metadata['what_to_test_next'] ?? $recommendation->summary,
            ],
            'client_view' => [
                'headline' => $recommendation->summary,
                'summary' => $clientSummary,
            ],
            'action_status' => [
                'code' => $recommendation->status,
                'label' => $this->actionStatusLabel($recommendation->status),
                'manual_review_required' => true,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function groupAlertsByEntityType(Collection $items): array
    {
        return $items
            ->groupBy('entity_type')
            ->map(function (Collection $group, string $entityType): array {
                return [
                    'entity_type' => $entityType,
                    'count' => $group->count(),
                    'critical_count' => $group->filter(fn (array $item): bool => $this->priorityRank($item['severity']) >= 3)->count(),
                    'items' => $group
                        ->sortByDesc(fn (array $item): array => [$this->priorityRank($item['severity']), (string) ($item['date_detected'] ?? '')])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('critical_count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function groupRecommendationsByEntityType(Collection $items): array
    {
        return $items
            ->groupBy('entity_type')
            ->map(function (Collection $group, string $entityType): array {
                return [
                    'entity_type' => $entityType,
                    'count' => $group->count(),
                    'high_priority_count' => $group->filter(fn (array $item): bool => $this->priorityRank($item['priority']) >= 3)->count(),
                    'items' => $group
                        ->sortByDesc(fn (array $item): array => [$this->priorityRank($item['priority']), (string) ($item['generated_at'] ?? '')])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('high_priority_count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildAlertActions(Collection $items, int $limit = 5): array
    {
        return $items
            ->sortByDesc(fn (array $item): array => [$this->priorityRank($item['severity']), (string) ($item['date_detected'] ?? '')])
            ->take($limit)
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'source' => 'alert',
                'priority' => $item['severity'],
                'title' => $item['summary'],
                'entity_type' => $item['entity_type'],
                'entity_label' => $item['entity_label'],
                'context_label' => $item['context_label'],
                'route' => $item['route'],
                'why_it_matters' => $item['impact_summary'],
                'recommended_action' => $item['next_step'],
                'detected_at' => $item['date_detected'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildRecommendationActions(Collection $items, int $limit = 5): array
    {
        return $items
            ->sortByDesc(fn (array $item): array => [$this->priorityRank($item['priority']), (string) ($item['generated_at'] ?? '')])
            ->take($limit)
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'source' => 'recommendation',
                'priority' => $item['priority'],
                'title' => $item['summary'],
                'entity_type' => $item['entity_type'],
                'entity_label' => $item['entity_label'],
                'context_label' => $item['context_label'],
                'route' => $item['route'],
                'why_it_matters' => data_get($item, 'client_view.summary'),
                'recommended_action' => data_get($item, 'operator_view.next_test'),
                'generated_at' => $item['generated_at'],
            ])
            ->values()
            ->all();
    }

    private function fallbackAlertImpact(Alert $alert): string
    {
        return match ($alert->code) {
            'spend_no_result' => 'Harcama devam ederken sonuc alinmiyor; verimsiz butce tuketimi artabilir.',
            'rising_cpa', 'rising_cpl' => 'Maliyet yukselirken ayni butceyle daha az sonuc alinabilir.',
            'falling_ctr' => 'Etkilesim zayiflarsa teslim kalitesi ve verimlilik dusur.',
            'rising_cpm' => 'Gosterim maliyeti artisi ayni hacmi daha pahaliya getirir.',
            default => $alert->summary,
        };
    }

    private function actionStatusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Bekliyor',
            'in_progress' => 'Calisiliyor',
            'approved' => 'Onaylandi',
            'rejected' => 'Reddedildi',
            'completed', 'done' => 'Tamamlandi',
            default => ucfirst($status),
        };
    }

    private function priorityRank(string $value): int
    {
        return match ($value) {
            'critical', 'high' => 3,
            'warning', 'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}
