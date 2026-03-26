<?php

namespace App\Domain\Reporting\Services;

use App\Support\Operations\EntityContextResolver;
use Illuminate\Support\Collection;

class ReportDecisionSurfaceQueueService
{
    public function __construct(
        private readonly ReportWorkspaceConfigStore $configStore,
        private readonly ReportDecisionSurfaceStatusService $reportDecisionSurfaceStatusService,
        private readonly EntityContextResolver $entityContextResolver,
    ) {
    }

    /**
     * @return array{summary: array<string, int|string|null>, items: array<int, array<string, mixed>>}
     */
    public function index(string $workspaceId): array
    {
        $trackedEntities = $this->configStore
            ->collection($workspaceId, 'reports.decision_surface_statuses')
            ->map(fn (array $item): array => [
                'entity_type' => (string) ($item['entity_type'] ?? ''),
                'entity_id' => (string) ($item['entity_id'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['entity_type'] !== '' && $item['entity_id'] !== '')
            ->unique(fn (array $item): string => sprintf('%s:%s', $item['entity_type'], $item['entity_id']))
            ->values();

        if ($trackedEntities->isEmpty()) {
            return [
                'summary' => [
                    'tracked_entities' => 0,
                    'total_items' => 0,
                    'open_items' => 0,
                    'pending_items' => 0,
                    'reviewed_items' => 0,
                    'completed_items' => 0,
                    'deferred_items' => 0,
                    'top_surface_label' => null,
                ],
                'items' => [],
            ];
        }

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $trackedEntities
                ->map(fn (array $item): array => [
                    'type' => $item['entity_type'],
                    'id' => $item['entity_id'],
                ])
                ->all(),
        );

        $items = $trackedEntities
            ->flatMap(function (array $reference) use ($workspaceId, $contexts): array {
                $statusBundle = $this->reportDecisionSurfaceStatusService->forEntity(
                    $workspaceId,
                    $reference['entity_type'],
                    $reference['entity_id'],
                );

                $context = $contexts[$this->entityContextResolver->key($reference['entity_type'], $reference['entity_id'])] ?? [
                    'entity_label' => 'Bilinmeyen varlik',
                    'context_label' => null,
                    'route' => null,
                ];

                return collect($statusBundle['items'])
                    ->map(fn (array $item): array => array_merge($item, [
                        'entity_label' => $context['entity_label'],
                        'context_label' => $context['context_label'],
                        'route' => $this->focusedRoute($context['route'], $item['surface_key']),
                        'is_open' => $item['status'] !== 'completed',
                    ]))
                    ->all();
            })
            ->sort(function (array $left, array $right): int {
                $statusComparison = $this->statusPriority($left['status']) <=> $this->statusPriority($right['status']);

                if ($statusComparison !== 0) {
                    return $statusComparison;
                }

                $leftUpdated = $left['updated_at'] ?? '';
                $rightUpdated = $right['updated_at'] ?? '';

                if ($leftUpdated === $rightUpdated) {
                    return strcmp(
                        sprintf('%s:%s:%s', $left['entity_type'], $left['entity_label'], $left['surface_key']),
                        sprintf('%s:%s:%s', $right['entity_type'], $right['entity_label'], $right['surface_key']),
                    );
                }

                if ($leftUpdated === null || $leftUpdated === '') {
                    return -1;
                }

                if ($rightUpdated === null || $rightUpdated === '') {
                    return 1;
                }

                return strcmp($leftUpdated, $rightUpdated);
            })
            ->values();

        return [
            'summary' => [
                'tracked_entities' => $trackedEntities->count(),
                'total_items' => $items->count(),
                'open_items' => $items->where('is_open', true)->count(),
                'pending_items' => $items->where('status', 'pending')->count(),
                'reviewed_items' => $items->where('status', 'reviewed')->count(),
                'completed_items' => $items->where('status', 'completed')->count(),
                'deferred_items' => $items->where('status', 'deferred')->count(),
                'top_surface_label' => $items->first()['surface_label'] ?? null,
            ],
            'items' => $items->all(),
        ];
    }

    private function statusPriority(string $status): int
    {
        return match ($status) {
            'pending' => 0,
            'deferred' => 1,
            'reviewed' => 2,
            'completed' => 3,
            default => 4,
        };
    }

    private function focusedRoute(?string $baseRoute, string $surfaceKey): ?string
    {
        if (! $baseRoute) {
            return null;
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
}
