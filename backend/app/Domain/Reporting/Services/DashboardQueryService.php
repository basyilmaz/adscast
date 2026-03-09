<?php

namespace App\Domain\Reporting\Services;

use App\Models\Alert;
use App\Models\Campaign;
use App\Models\InsightDaily;
use App\Models\MetaConnection;
use App\Models\Recommendation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DashboardQueryService
{
    /**
     * @return array<string, mixed>
     */
    public function getOverview(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $current = $this->aggregateMetrics($workspaceId, $startDate, $endDate);

        $days = $startDate->diffInDays($endDate) + 1;
        $previousStart = $startDate->copy()->subDays($days);
        $previousEnd = $startDate->copy()->subDay();
        $previous = $this->aggregateMetrics($workspaceId, $previousStart, $previousEnd);

        $campaignStats = $this->campaignStats($workspaceId, $startDate, $endDate);

        $alertsCount = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'open')
            ->count();

        $recentRecommendations = Recommendation::query()
            ->where('workspace_id', $workspaceId)
            ->latest('generated_at')
            ->limit(5)
            ->get([
                'id',
                'summary',
                'priority',
                'status',
                'generated_at',
            ]);

        $lastSyncAt = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->max('last_synced_at');

        return [
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'comparison_start_date' => $previousStart->toDateString(),
                'comparison_end_date' => $previousEnd->toDateString(),
            ],
            'metrics' => [
                'total_spend' => (float) ($current['spend'] ?? 0),
                'total_results' => (float) ($current['results'] ?? 0),
                'cpa_cpl' => (float) ($current['cpa_cpl'] ?? 0),
                'ctr' => (float) ($current['ctr'] ?? 0),
                'cpm' => (float) ($current['cpm'] ?? 0),
                'frequency' => (float) ($current['frequency'] ?? 0),
            ],
            'comparison' => [
                'total_spend' => (float) (($current['spend'] ?? 0) - ($previous['spend'] ?? 0)),
                'total_results' => (float) (($current['results'] ?? 0) - ($previous['results'] ?? 0)),
                'cpa_cpl' => (float) (($current['cpa_cpl'] ?? 0) - ($previous['cpa_cpl'] ?? 0)),
                'ctr' => (float) (($current['ctr'] ?? 0) - ($previous['ctr'] ?? 0)),
                'cpm' => (float) (($current['cpm'] ?? 0) - ($previous['cpm'] ?? 0)),
                'frequency' => (float) (($current['frequency'] ?? 0) - ($previous['frequency'] ?? 0)),
            ],
            'best_campaign' => $campaignStats['best'] ?? null,
            'worst_campaign' => $campaignStats['worst'] ?? null,
            'active_alerts' => $alertsCount,
            'recent_recommendations' => $recentRecommendations,
            'sync_freshness' => [
                'last_synced_at' => $lastSyncAt,
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    private function aggregateMetrics(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $row = InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw('
                COALESCE(SUM(spend), 0) as spend,
                COALESCE(SUM(results), 0) as results,
                COALESCE(AVG(ctr), 0) as ctr,
                COALESCE(AVG(cpm), 0) as cpm,
                COALESCE(AVG(frequency), 0) as frequency
            ')
            ->first();

        $spend = (float) ($row?->spend ?? 0);
        $results = (float) ($row?->results ?? 0);

        return [
            'spend' => $spend,
            'results' => $results,
            'ctr' => (float) ($row?->ctr ?? 0),
            'cpm' => (float) ($row?->cpm ?? 0),
            'frequency' => (float) ($row?->frequency ?? 0),
            'cpa_cpl' => $results > 0 ? round($spend / $results, 4) : 0.0,
        ];
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function campaignStats(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', 'campaign')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('entity_external_id')
            ->select([
                'entity_external_id',
                DB::raw('SUM(spend) as spend'),
                DB::raw('SUM(results) as results'),
                DB::raw('AVG(ctr) as ctr'),
                DB::raw('AVG(cpm) as cpm'),
            ])
            ->get();

        if ($rows->isEmpty()) {
            return [
                'best' => null,
                'worst' => null,
            ];
        }

        $campaignNames = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('meta_campaign_id', $rows->pluck('entity_external_id')->all())
            ->pluck('name', 'meta_campaign_id');

        $mapped = $rows->map(function ($row) use ($campaignNames): array {
            $results = (float) $row->results;
            $spend = (float) $row->spend;
            $efficiency = $results > 0 ? $spend / $results : INF;

            return [
                'external_id' => $row->entity_external_id,
                'name' => $campaignNames[$row->entity_external_id] ?? $row->entity_external_id,
                'spend' => $spend,
                'results' => $results,
                'ctr' => (float) $row->ctr,
                'cpm' => (float) $row->cpm,
                'efficiency' => is_infinite($efficiency) ? null : round($efficiency, 4),
            ];
        });

        $best = $mapped
            ->filter(fn (array $item): bool => $item['efficiency'] !== null)
            ->sortBy('efficiency')
            ->first();

        $worst = $mapped
            ->filter(fn (array $item): bool => $item['efficiency'] !== null)
            ->sortByDesc('efficiency')
            ->first();

        return [
            'best' => $best,
            'worst' => $worst,
        ];
    }
}
