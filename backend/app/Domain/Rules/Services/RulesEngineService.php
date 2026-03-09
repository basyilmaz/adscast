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

        $current = $this->aggregateByCampaign($workspaceId, $startDate, $endDate);
        $previous = $this->aggregateByCampaign($workspaceId, $previousStart, $previousEnd)->keyBy('entity_external_id');

        $campaignMap = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('meta_campaign_id', $current->pluck('entity_external_id')->all())
            ->pluck('id', 'meta_campaign_id');

        $signals = [];

        foreach ($current as $row) {
            $context = [
                'workspace_id' => $workspaceId,
                'entity_type' => 'campaign',
                'entity_id' => $campaignMap[$row['entity_external_id']] ?? null,
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

        $signals = array_merge($signals, $this->evaluateSiblingPerformance($workspaceId, $current, $endDate));

        return $signals;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregateByCampaign(
        string $workspaceId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): Collection {
        return InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', 'campaign')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('entity_external_id')
            ->get([
                'entity_external_id',
            ])
            ->map(function (InsightDaily $insight) use ($workspaceId, $startDate, $endDate): array {
                $metrics = InsightDaily::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('level', 'campaign')
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
     * @param Collection<int, array<string, mixed>> $current
     * @return array<int, array<string, mixed>>
     */
    private function evaluateSiblingPerformance(
        string $workspaceId,
        Collection $current,
        CarbonInterface $date,
    ): array {
        if ($current->count() < 2) {
            return [];
        }

        $efficiencies = $current
            ->filter(fn (array $row): bool => (float) $row['results'] > 0)
            ->map(fn (array $row): float => (float) $row['cpa_cpl']);

        if ($efficiencies->isEmpty()) {
            return [];
        }

        $median = (float) $efficiencies->sort()->values()->get((int) floor(($efficiencies->count() - 1) / 2));
        $results = [];

        foreach ($current as $row) {
            $cpa = (float) $row['cpa_cpl'];
            if ($cpa <= 0 || $cpa < ($median * 1.6)) {
                continue;
            }

            $campaignId = Campaign::query()
                ->where('workspace_id', $workspaceId)
                ->where('meta_campaign_id', $row['entity_external_id'])
                ->value('id');

            $signal = new RuleSignal(
                code: 'weak_winner_loser',
                severity: 'medium',
                summary: 'Kardes kampanyalara gore zayif performans goruluyor.',
                explanation: 'Bu kampanyanin birim maliyeti ayni workspace icindeki benzer kampanyalara gore yuksek.',
                recommendedAction: 'Dusuk verimli ad setlerini kisa listeye alip daha iyi performansli segmentlere butce kaydirin.',
                confidence: 0.74,
                entityType: 'campaign',
                entityId: $campaignId,
                dateDetected: $date->toDateString(),
            );

            $results[] = $this->persistSignal($workspaceId, $signal);
        }

        return $results;
    }
}
