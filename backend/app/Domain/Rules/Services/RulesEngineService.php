<?php

namespace App\Domain\Rules\Services;

use App\Domain\Rules\Contracts\Rule;
use App\Domain\Rules\DTO\RuleSignal;
use App\Domain\Rules\Rules\BudgetScaleOpportunityRule;
use App\Domain\Rules\Rules\FallingCtrRule;
use App\Domain\Rules\Rules\HighFrequencyRule;
use App\Domain\Rules\Rules\RisingCpaRule;
use App\Domain\Rules\Rules\RisingCpmRule;
use App\Domain\Rules\Rules\SpendWithNoResultRule;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\InsightDaily;
use App\Models\Recommendation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RulesEngineService
{
    /**
     * @var array<int, Rule>
     */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new SpendWithNoResultRule(),
            new RisingCpaRule(),
            new FallingCtrRule(),
            new RisingCpmRule(),
            new HighFrequencyRule(),
            new BudgetScaleOpportunityRule(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluateWorkspace(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $days = $startDate->diffInDays($endDate) + 1;
        $previousStart = $startDate->copy()->subDays($days);
        $previousEnd = $startDate->copy()->subDay();

        $signals = [];

        // Campaign level evaluation
        $signals = array_merge($signals, $this->evaluateLevel($workspaceId, 'campaign', $startDate, $endDate, $previousStart, $previousEnd));
        $signals = array_merge($signals, $this->evaluateSiblingPerformance($workspaceId, 'campaign', $startDate, $endDate));

        // Ad Set level evaluation
        $signals = array_merge($signals, $this->evaluateLevel($workspaceId, 'adset', $startDate, $endDate, $previousStart, $previousEnd));
        $signals = array_merge($signals, $this->evaluateSiblingPerformance($workspaceId, 'adset', $startDate, $endDate));

        // Ad level evaluation
        $signals = array_merge($signals, $this->evaluateLevel($workspaceId, 'ad', $startDate, $endDate, $previousStart, $previousEnd));
        $signals = array_merge($signals, $this->evaluateSiblingPerformance($workspaceId, 'ad', $startDate, $endDate));

        return $signals;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function evaluateLevel(
        string $workspaceId,
        string $level,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        CarbonInterface $previousStart,
        CarbonInterface $previousEnd,
    ): array {
        $current = $this->aggregateByLevel($workspaceId, $level, $startDate, $endDate);
        $previous = $this->aggregateByLevel($workspaceId, $level, $previousStart, $previousEnd)->keyBy('entity_external_id');

        $entityMap = $this->resolveEntityMap($workspaceId, $level, $current->pluck('entity_external_id')->all());
        $entityType = $this->normalizeEntityType($level);

        $signals = [];

        foreach ($current as $row) {
            $context = [
                'workspace_id' => $workspaceId,
                'entity_type' => $entityType,
                'entity_id' => $entityMap[$row['entity_external_id']] ?? null,
                'entity_external_id' => $row['entity_external_id'],
                'date_detected' => $endDate->toDateString(),
                'current' => $row,
                'previous' => $previous[$row['entity_external_id']] ?? [],
            ];

            foreach ($this->rules as $rule) {
                $signal = $rule->evaluate($context);

                if (! $signal) {
                    continue;
                }

                $signals[] = $this->persistSignal($workspaceId, $signal);
            }
        }

        return $signals;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregateByLevel(
        string $workspaceId,
        string $level,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): Collection {
        return InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', $level)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('entity_external_id')
            ->get([
                'entity_external_id',
            ])
            ->map(function (InsightDaily $insight) use ($workspaceId, $level, $startDate, $endDate): array {
                $metrics = InsightDaily::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('level', $level)
                    ->where('entity_external_id', $insight->entity_external_id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->selectRaw('
                        COALESCE(SUM(spend), 0) as spend,
                        COALESCE(SUM(results), 0) as results,
                        COALESCE(AVG(ctr), 0) as ctr,
                        COALESCE(AVG(cpm), 0) as cpm,
                        COALESCE(AVG(frequency), 0) as frequency
                    ')
                    ->first();

                $spend = (float) ($metrics?->spend ?? 0);
                $results = (float) ($metrics?->results ?? 0);

                return [
                    'entity_external_id' => $insight->entity_external_id,
                    'spend' => $spend,
                    'results' => $results,
                    'cpa_cpl' => $results > 0 ? round($spend / $results, 4) : 0,
                    'ctr' => (float) ($metrics?->ctr ?? 0),
                    'cpm' => (float) ($metrics?->cpm ?? 0),
                    'frequency' => (float) ($metrics?->frequency ?? 0),
                ];
            });
    }

    /**
     * @param array<int, string> $externalIds
     * @return array<string, string>
     */
    private function resolveEntityMap(string $workspaceId, string $level, array $externalIds): array
    {
        return match ($level) {
            'campaign' => Campaign::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('meta_campaign_id', $externalIds)
                ->pluck('id', 'meta_campaign_id')
                ->all(),
            'adset' => AdSet::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('meta_ad_set_id', $externalIds)
                ->pluck('id', 'meta_ad_set_id')
                ->all(),
            'ad' => Ad::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('meta_ad_id', $externalIds)
                ->pluck('id', 'meta_ad_id')
                ->all(),
            default => [],
        };
    }

    private function normalizeEntityType(string $level): string
    {
        return match ($level) {
            'adset' => 'ad_set',
            default => $level,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function persistSignal(string $workspaceId, RuleSignal $signal): array
    {
        $attributes = $signal->toArray();

        $alert = Alert::query()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'entity_type' => $attributes['entity_type'],
                'entity_id' => $attributes['entity_id'],
                'code' => $attributes['code'],
                'date_detected' => $attributes['date_detected'],
            ],
            [
                'severity' => $attributes['severity'],
                'summary' => $attributes['summary'],
                'explanation' => $attributes['explanation'],
                'recommended_action' => $attributes['recommended_action'],
                'confidence' => $attributes['confidence'],
                'status' => 'open',
                'source_rule_version' => 'v1',
            ]
        );

        Recommendation::query()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'alert_id' => $alert->id,
                'target_type' => $attributes['entity_type'],
                'target_id' => $attributes['entity_id'],
                'summary' => $attributes['summary'],
            ],
            [
                'details' => $attributes['recommended_action'],
                'action_type' => 'manual_review',
                'priority' => $attributes['severity'],
                'status' => 'open',
                'source' => 'rules',
                'generated_at' => now(),
            ]
        );

        return $alert->toArray();
    }

    /**
     * Evaluate sibling performance within a level - compares entities that share a parent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function evaluateSiblingPerformance(
        string $workspaceId,
        string $level,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): array {
        $current = $this->aggregateByLevel($workspaceId, $level, $startDate, $endDate);

        if ($current->count() < 2) {
            return [];
        }

        $entityType = $this->normalizeEntityType($level);
        $entityMap = $this->resolveEntityMap($workspaceId, $level, $current->pluck('entity_external_id')->all());
        $siblingScopes = $this->resolveSiblingScopes($workspaceId, $level, $current->pluck('entity_external_id')->all());
        $results = [];

        $labels = [
            'campaign' => ['Kardes kampanyalara gore zayif performans goruluyor.', 'Bu kampanyanin birim maliyeti ayni workspace icindeki benzer kampanyalara gore yuksek.', 'Dusuk verimli ad setlerini kisa listeye alip daha iyi performansli segmentlere butce kaydirin.'],
            'adset' => ['Kardes ad setlere gore zayif performans goruluyor.', 'Bu ad setin birim maliyeti ayni kampanyadaki benzer ad setlere gore yuksek.', 'Bu ad seti durdurup butceyi daha iyi performansli ad setlere kaydirin veya hedeflemeyi daraltarak test edin.'],
            'ad' => ['Kardes reklamlara gore zayif performans goruluyor.', 'Bu reklamin birim maliyeti ayni ad setteki benzer reklamlara gore yuksek.', 'Bu reklami durdurun ve daha iyi performansli kreatife butce ayin.'],
        ];

        [$summary, $explanation, $action] = $labels[$level] ?? $labels['campaign'];

        $current
            ->groupBy(fn (array $row): string => (string) ($siblingScopes[$row['entity_external_id']] ?? '__missing__'))
            ->each(function (Collection $scopeRows) use (&$results, $entityMap, $entityType, $summary, $explanation, $action, $endDate, $workspaceId): void {
                if ($scopeRows->count() < 2) {
                    return;
                }

                $efficiencies = $scopeRows
                    ->filter(fn (array $row): bool => (float) $row['results'] > 0)
                    ->map(fn (array $row): float => (float) $row['cpa_cpl']);

                if ($efficiencies->isEmpty()) {
                    return;
                }

                $median = (float) $efficiencies->sort()->values()->get((int) floor(($efficiencies->count() - 1) / 2));

                foreach ($scopeRows as $row) {
                    $cpa = (float) $row['cpa_cpl'];
                    if ($cpa <= 0 || $cpa < ($median * 1.6)) {
                        continue;
                    }

                    $signal = new RuleSignal(
                        code: 'weak_winner_loser',
                        severity: 'medium',
                        summary: $summary,
                        explanation: $explanation,
                        recommendedAction: $action,
                        confidence: 0.74,
                        entityType: $entityType,
                        entityId: $entityMap[$row['entity_external_id']] ?? null,
                        dateDetected: $endDate->toDateString(),
                    );

                    $results[] = $this->persistSignal($workspaceId, $signal);
                }
            });

        return $results;
    }

    /**
     * @param array<int, string> $externalIds
     * @return array<string, string>
     */
    private function resolveSiblingScopes(string $workspaceId, string $level, array $externalIds): array
    {
        return match ($level) {
            'campaign' => collect($externalIds)->mapWithKeys(fn (string $externalId): array => [$externalId => $workspaceId])->all(),
            'adset' => AdSet::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('meta_ad_set_id', $externalIds)
                ->pluck('campaign_id', 'meta_ad_set_id')
                ->map(fn (?string $campaignId, string $externalId): string => $campaignId ?: "__missing__::{$externalId}")
                ->all(),
            'ad' => Ad::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('meta_ad_id', $externalIds)
                ->pluck('ad_set_id', 'meta_ad_id')
                ->map(fn (?string $adSetId, string $externalId): string => $adSetId ?: "__missing__::{$externalId}")
                ->all(),
            default => [],
        };
    }
}
