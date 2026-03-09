<?php

namespace App\Domain\Reporting\Services;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\InsightDaily;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CampaignQueryService
{
    /**
     * @return array<string, mixed>
     */
    public function list(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $campaigns = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'meta_campaign_id',
                'name',
                'objective',
                'status',
                'updated_at',
            ]);

        $insights = InsightDaily::query()
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
            ->get()
            ->keyBy('entity_external_id');

        return [
            'items' => $campaigns->map(function (Campaign $campaign) use ($insights): array {
                $metric = $insights->get($campaign->meta_campaign_id);
                $spend = (float) ($metric->spend ?? 0);
                $results = (float) ($metric->results ?? 0);

                return [
                    'id' => $campaign->id,
                    'meta_campaign_id' => $campaign->meta_campaign_id,
                    'name' => $campaign->name,
                    'objective' => $campaign->objective,
                    'status' => $campaign->status,
                    'spend' => $spend,
                    'results' => $results,
                    'cpa_cpl' => $results > 0 ? round($spend / $results, 4) : null,
                    'ctr' => (float) ($metric->ctr ?? 0),
                    'cpm' => (float) ($metric->cpm ?? 0),
                    'updated_at' => $campaign->updated_at,
                ];
            }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Campaign $campaign, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $trend = InsightDaily::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('level', 'campaign')
            ->where('entity_external_id', $campaign->meta_campaign_id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get([
                'date',
                'spend',
                'results',
                'ctr',
                'cpm',
                'frequency',
            ]);

        $summary = [
            'spend' => (float) $trend->sum('spend'),
            'results' => (float) $trend->sum('results'),
            'ctr' => (float) $trend->avg('ctr'),
            'cpm' => (float) $trend->avg('cpm'),
            'frequency' => (float) $trend->avg('frequency'),
        ];

        $summary['cpa_cpl'] = $summary['results'] > 0
            ? round($summary['spend'] / $summary['results'], 4)
            : null;

        $adSets = AdSet::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('campaign_id', $campaign->id)
            ->get([
                'id',
                'name',
                'status',
                'optimization_goal',
                'daily_budget',
            ]);

        $ads = Ad::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('campaign_id', $campaign->id)
            ->get([
                'id',
                'name',
                'status',
                'effective_status',
            ]);

        $alerts = Alert::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('entity_type', 'campaign')
            ->where('entity_id', $campaign->id)
            ->latest('date_detected')
            ->get([
                'id',
                'code',
                'severity',
                'summary',
                'recommended_action',
                'date_detected',
            ]);

        return [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'meta_campaign_id' => $campaign->meta_campaign_id,
                'objective' => $campaign->objective,
                'status' => $campaign->status,
            ],
            'summary' => $summary,
            'trend' => $trend,
            'ad_sets' => $adSets,
            'ads' => $ads,
            'alerts' => $alerts,
        ];
    }
}
