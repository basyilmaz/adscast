<?php

namespace App\Domain\Reporting\Services;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\InsightDaily;
use App\Models\MetaAdAccount;
use App\Models\Recommendation;
use App\Support\Operations\ActionFeedService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdAccountQueryService
{
    public function __construct(
        private readonly ActionFeedService $actionFeedService,
        private readonly ReportDeliveryProfileService $reportDeliveryProfileService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function list(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $accounts = MetaAdAccount::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get([
                'id',
                'account_id',
                'name',
                'currency',
                'timezone_name',
                'status',
                'is_active',
                'last_synced_at',
            ]);

        $campaigns = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->get([
                'id',
                'meta_ad_account_id',
                'meta_campaign_id',
                'name',
                'objective',
                'status',
                'effective_status',
                'is_active',
            ]);

        $performanceMap = $this->campaignPerformanceMap($workspaceId, $startDate, $endDate);

        $openAlerts = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'open')
            ->where('entity_type', 'campaign')
            ->get([
                'id',
                'entity_id',
                'severity',
            ])
            ->groupBy('entity_id');

        $openRecommendations = Recommendation::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'open')
            ->where('target_type', 'campaign')
            ->get([
                'id',
                'target_id',
            ])
            ->groupBy('target_id');

        $campaignsByAccount = $campaigns->groupBy('meta_ad_account_id');

        $items = $accounts->map(function (MetaAdAccount $account) use ($campaignsByAccount, $performanceMap, $openAlerts, $openRecommendations): array {
            /** @var Collection<int, Campaign> $accountCampaigns */
            $accountCampaigns = $campaignsByAccount->get($account->id, collect());

            $aggregate = $this->aggregateAccountMetrics(
                $account,
                $accountCampaigns,
                $performanceMap,
                $openAlerts,
                $openRecommendations,
            );

            return [
                'id' => $account->id,
                'account_id' => $account->account_id,
                'name' => $account->name,
                'currency' => $account->currency,
                'timezone_name' => $account->timezone_name,
                'status' => $account->status,
                'is_active' => $account->is_active,
                'last_synced_at' => $account->last_synced_at,
                'sync_status' => $this->syncStatus($account->last_synced_at),
                'active_campaigns' => $aggregate['active_campaigns'],
                'total_campaigns' => $accountCampaigns->count(),
                'open_alerts' => $aggregate['open_alerts'],
                'open_recommendations' => $aggregate['open_recommendations'],
                'spend' => round($aggregate['spend'], 2),
                'results' => round($aggregate['results'], 2),
                'ctr' => round($aggregate['ctr'], 4),
                'cpm' => round($aggregate['cpm'], 4),
                'frequency' => round($aggregate['frequency'], 4),
                'health_status' => $aggregate['health_status'],
                'health_summary' => $aggregate['health_summary'],
            ];
        })->values();

        return [
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_accounts' => $items->count(),
                'active_accounts' => $items->where('is_active', true)->where('status', 'active')->count(),
                'restricted_accounts' => $items->where('status', '!=', 'active')->count(),
                'accounts_requiring_attention' => $items->whereIn('health_status', ['warning', 'critical'])->count(),
                'total_spend' => round((float) $items->sum('spend'), 2),
                'total_results' => round((float) $items->sum('results'), 2),
                'open_alerts' => (int) $items->sum('open_alerts'),
            ],
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(MetaAdAccount $account, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $campaigns = Campaign::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('meta_ad_account_id', $account->id)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'meta_campaign_id',
                'name',
                'objective',
                'status',
                'effective_status',
                'is_active',
                'updated_at',
            ]);

        $performanceMap = $this->campaignPerformanceMap($account->workspace_id, $startDate, $endDate);
        $campaignIds = $campaigns->pluck('id');
        $campaignMetaIds = $campaigns->pluck('meta_campaign_id')->filter()->values();

        $alerts = Alert::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('entity_type', 'campaign')
            ->whereIn('entity_id', $campaignIds->all())
            ->where('status', 'open')
            ->latest('date_detected')
            ->get([
                'id',
                'entity_type',
                'entity_id',
                'code',
                'severity',
                'summary',
                'explanation',
                'recommended_action',
                'confidence',
                'status',
                'date_detected',
            ]);

        $alertsByCampaign = $alerts->groupBy('entity_id');

        $recommendations = Recommendation::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('target_type', 'campaign')
            ->whereIn('target_id', $campaignIds->all())
            ->latest('generated_at')
            ->get([
                'id',
                'target_type',
                'target_id',
                'summary',
                'details',
                'action_type',
                'priority',
                'status',
                'source',
                'generated_at',
                'metadata',
            ]);

        $recommendationsByCampaign = $recommendations->groupBy('target_id');

        $campaignRows = $campaigns->map(function (Campaign $campaign) use ($performanceMap, $alertsByCampaign, $recommendationsByCampaign): array {
            $metric = $performanceMap->get($campaign->meta_campaign_id);
            $spend = (float) ($metric->spend ?? 0);
            $results = (float) ($metric->results ?? 0);
            $alertCount = $alertsByCampaign->get($campaign->id, collect())->count();
            $recommendationCount = $recommendationsByCampaign->get($campaign->id, collect())->count();
            $healthStatus = $this->campaignHealthStatus($campaign, $spend, $results, $alertCount);

            return [
                'id' => $campaign->id,
                'meta_campaign_id' => $campaign->meta_campaign_id,
                'name' => $campaign->name,
                'objective' => $campaign->objective,
                'status' => $campaign->status,
                'effective_status' => $campaign->effective_status,
                'spend' => round($spend, 2),
                'results' => round($results, 2),
                'cpa_cpl' => $results > 0 ? round($spend / $results, 4) : null,
                'ctr' => round((float) ($metric->ctr ?? 0), 4),
                'cpm' => round((float) ($metric->cpm ?? 0), 4),
                'frequency' => round((float) ($metric->frequency ?? 0), 4),
                'open_alerts' => $alertCount,
                'open_recommendations' => $recommendationCount,
                'health_status' => $healthStatus,
                'health_summary' => $this->campaignHealthSummary($campaign, $healthStatus, $alertCount, $spend, $results),
                'updated_at' => $campaign->updated_at,
            ];
        })
            ->sortByDesc(fn (array $campaign): array => [$campaign['open_alerts'], $campaign['spend'], $campaign['results']])
            ->values();

        $trend = $this->accountTrend($account->workspace_id, $campaignMetaIds, $startDate, $endDate);
        $summary = [
            'spend' => round((float) collect($trend)->sum('spend'), 2),
            'results' => round((float) collect($trend)->sum('results'), 2),
            'ctr' => round($campaignRows->avg('ctr') ?? 0, 4),
            'cpm' => round($campaignRows->avg('cpm') ?? 0, 4),
            'frequency' => round($campaignRows->avg('frequency') ?? 0, 4),
            'active_campaigns' => $campaignRows->where('status', 'active')->count(),
            'total_campaigns' => $campaignRows->count(),
            'active_ad_sets' => AdSet::query()
                ->where('workspace_id', $account->workspace_id)
                ->whereIn('campaign_id', $campaignIds->all())
                ->where('status', 'active')
                ->count(),
            'active_ads' => Ad::query()
                ->where('workspace_id', $account->workspace_id)
                ->whereIn('campaign_id', $campaignIds->all())
                ->where('status', 'active')
                ->count(),
            'open_alerts' => $alerts->count(),
            'open_recommendations' => $recommendations->where('status', 'open')->count(),
        ];
        $summary['cpa_cpl'] = $summary['results'] > 0
            ? round($summary['spend'] / $summary['results'], 4)
            : null;

        $healthStatus = $this->accountHealthStatus(
            $account,
            (int) $summary['active_campaigns'],
            (int) $summary['open_alerts'],
            (float) $summary['spend'],
            (float) $summary['results'],
        );
        $alertPayload = $this->actionFeedService->presentAlerts($account->workspace_id, $alerts);
        $recommendationPayload = $this->actionFeedService->presentRecommendations($account->workspace_id, $recommendations);

        return [
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'ad_account' => [
                'id' => $account->id,
                'account_id' => $account->account_id,
                'name' => $account->name,
                'currency' => $account->currency,
                'timezone_name' => $account->timezone_name,
                'status' => $account->status,
                'is_active' => $account->is_active,
                'last_synced_at' => $account->last_synced_at,
            ],
            'summary' => $summary,
            'health' => [
                'status' => $healthStatus,
                'summary' => $this->accountHealthSummary(
                    $account,
                    $healthStatus,
                    (int) $summary['active_campaigns'],
                    (int) $summary['open_alerts'],
                    (float) $summary['spend'],
                    (float) $summary['results'],
                ),
                'sync_status' => $this->syncStatus($account->last_synced_at),
            ],
            'trend' => $trend,
            'campaigns' => $campaignRows->all(),
            'alerts' => $alertPayload['items'],
            'recommendations' => $recommendationPayload['items'],
            'delivery_profile' => $this->reportDeliveryProfileService->findByEntity(
                $account->workspace_id,
                'account',
                $account->id,
            ),
            'next_best_actions' => $this->actionFeedService->nextBestActions(
                $account->workspace_id,
                $alerts,
                $recommendations,
                5,
            ),
            'report_preview' => $this->reportPreview($account, $summary, $campaignRows, $alerts),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<string, object>
     */
    private function campaignPerformanceMap(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        return InsightDaily::query()
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
                DB::raw('AVG(frequency) as frequency'),
            ])
            ->get()
            ->keyBy('entity_external_id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Campaign>  $accountCampaigns
     * @param  \Illuminate\Support\Collection<string, object>  $performanceMap
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Alert>>  $openAlerts
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Recommendation>>  $openRecommendations
     * @return array<string, float|int|string>
     */
    private function aggregateAccountMetrics(
        MetaAdAccount $account,
        Collection $accountCampaigns,
        Collection $performanceMap,
        Collection $openAlerts,
        Collection $openRecommendations,
    ): array {
        $aggregate = $accountCampaigns->reduce(
            function (array $carry, Campaign $campaign) use ($performanceMap, $openAlerts, $openRecommendations): array {
                $metric = $performanceMap->get($campaign->meta_campaign_id);
                $carry['spend'] += (float) ($metric->spend ?? 0);
                $carry['results'] += (float) ($metric->results ?? 0);
                $carry['ctr_total'] += (float) ($metric->ctr ?? 0);
                $carry['cpm_total'] += (float) ($metric->cpm ?? 0);
                $carry['frequency_total'] += (float) ($metric->frequency ?? 0);
                $carry['metric_rows'] += $metric ? 1 : 0;
                $carry['open_alerts'] += $openAlerts->get($campaign->id, collect())->count();
                $carry['open_recommendations'] += $openRecommendations->get($campaign->id, collect())->count();
                $carry['active_campaigns'] += $campaign->is_active && $campaign->status === 'active' ? 1 : 0;

                return $carry;
            },
            [
                'spend' => 0.0,
                'results' => 0.0,
                'ctr_total' => 0.0,
                'cpm_total' => 0.0,
                'frequency_total' => 0.0,
                'metric_rows' => 0,
                'open_alerts' => 0,
                'open_recommendations' => 0,
                'active_campaigns' => 0,
            ]
        );

        $aggregate['ctr'] = $aggregate['metric_rows'] > 0
            ? $aggregate['ctr_total'] / $aggregate['metric_rows']
            : 0.0;
        $aggregate['cpm'] = $aggregate['metric_rows'] > 0
            ? $aggregate['cpm_total'] / $aggregate['metric_rows']
            : 0.0;
        $aggregate['frequency'] = $aggregate['metric_rows'] > 0
            ? $aggregate['frequency_total'] / $aggregate['metric_rows']
            : 0.0;

        $aggregate['health_status'] = $this->accountHealthStatus(
            $account,
            (int) $aggregate['active_campaigns'],
            (int) $aggregate['open_alerts'],
            (float) $aggregate['spend'],
            (float) $aggregate['results'],
        );

        $aggregate['health_summary'] = $this->accountHealthSummary(
            $account,
            (string) $aggregate['health_status'],
            (int) $aggregate['active_campaigns'],
            (int) $aggregate['open_alerts'],
            (float) $aggregate['spend'],
            (float) $aggregate['results'],
        );

        return $aggregate;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $campaignMetaIds
     * @return array<int, array<string, mixed>>
     */
    private function accountTrend(
        string $workspaceId,
        Collection $campaignMetaIds,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): array {
        if ($campaignMetaIds->isEmpty()) {
            return $this->emptyTrend($startDate, $endDate);
        }

        $rows = InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', 'campaign')
            ->whereIn('entity_external_id', $campaignMetaIds->all())
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date, COALESCE(SUM(spend), 0) as spend, COALESCE(SUM(results), 0) as results')
            ->get()
            ->keyBy(fn ($row): string => Carbon::parse((string) $row->date)->toDateString());

        $trend = [];
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);

            $trend[] = [
                'date' => $date,
                'spend' => round((float) ($row->spend ?? 0), 2),
                'results' => round((float) ($row->results ?? 0), 2),
            ];

            $cursor->addDay();
        }

        return $trend;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emptyTrend(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $trend = [];
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $trend[] = [
                'date' => $cursor->toDateString(),
                'spend' => 0.0,
                'results' => 0.0,
            ];

            $cursor->addDay();
        }

        return $trend;
    }

    private function accountHealthStatus(
        MetaAdAccount $account,
        int $activeCampaigns,
        int $openAlerts,
        float $spend,
        float $results,
    ): string {
        return match (true) {
            ! $account->is_active || $account->status !== 'active' => 'critical',
            $openAlerts > 0 => 'warning',
            $spend > 0 && $results <= 0 => 'warning',
            $activeCampaigns === 0 => 'idle',
            default => 'healthy',
        };
    }

    private function accountHealthSummary(
        MetaAdAccount $account,
        string $healthStatus,
        int $activeCampaigns,
        int $openAlerts,
        float $spend,
        float $results,
    ): string {
        return match ($healthStatus) {
            'critical' => 'Hesap aktif degil veya Meta tarafinda kisitli gorunuyor.',
            'warning' => $openAlerts > 0
                ? sprintf('%d acik kampanya uyarisi var. Hesap bazinda inceleme gerekli.', $openAlerts)
                : ($spend > 0 && $results <= 0
                    ? 'Harcama var ancak sonuc gorunmuyor. Kampanya ve kreatifleri kontrol edin.'
                    : 'Performans dalgalanmasi var. Yakindan takip edin.'),
            'idle' => $activeCampaigns > 0
                ? 'Veri akisi sinirli. Son senkronu ve kampanya durumlarini kontrol edin.'
                : 'Bu hesapta aktif kampanya gorunmuyor.',
            default => $results > 0
                ? 'Hesap sonuc uretiyor ve kritik uyari gorunmuyor.'
                : 'Hesap genel olarak stabil gorunuyor.',
        };
    }

    private function campaignHealthStatus(Campaign $campaign, float $spend, float $results, int $alertCount): string
    {
        return match (true) {
            $campaign->status !== 'active' || ! $campaign->is_active => 'idle',
            $alertCount > 0 => 'warning',
            $spend > 0 && $results <= 0 => 'warning',
            $results > 0 => 'healthy',
            default => 'idle',
        };
    }

    private function campaignHealthSummary(
        Campaign $campaign,
        string $healthStatus,
        int $alertCount,
        float $spend,
        float $results,
    ): string {
        return match ($healthStatus) {
            'warning' => $alertCount > 0
                ? 'Bu kampanyada acik uyarilar var. Once uyari detaylarini kontrol edin.'
                : 'Harcama var ancak sonuc gorunmuyor. Teklif, kreatif ve hedeflemeyi gozden gecirin.',
            'healthy' => 'Kampanya secili aralikta sonuc uretiyor.',
            'idle' => $campaign->status === 'active' && $spend <= 0 && $results <= 0
                ? 'Kampanya aktif ancak secili aralikta anlamli veri yok.'
                : 'Kampanya aktif degil veya teslim sinirli.',
            default => 'Kampanya gorunumu stabil.',
        };
    }

    private function syncStatus(mixed $lastSyncedAt): string
    {
        if (! $lastSyncedAt) {
            return 'unknown';
        }

        $hours = now()->diffInHours($lastSyncedAt);

        return match (true) {
            $hours <= 24 => 'fresh',
            $hours <= 72 => 'stale',
            default => 'lagging',
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $campaignRows
     * @return array<string, string>
     */
    private function reportPreview(
        MetaAdAccount $account,
        array $summary,
        Collection $campaignRows,
        Collection $alerts,
    ): array {
        $topCampaign = $campaignRows
            ->sortByDesc(fn (array $campaign): float => ((float) $campaign['results'] * 1000000) + (float) $campaign['spend'])
            ->first();

        $headline = $summary['results'] > 0
            ? sprintf(
                '%s hesabi secili aralikta %.2f harcama ile %.0f sonuc uretti.',
                $account->name,
                (float) $summary['spend'],
                (float) $summary['results']
            )
            : sprintf(
                '%s hesabi secili aralikta %.2f harcama yapti ancak sonuc sinirli kaldi.',
                $account->name,
                (float) $summary['spend']
            );

        $clientSummary = match (true) {
            $summary['results'] > 0 && $alerts->isEmpty() => 'Hesap teslimati stabil. Mevcut kazanan kampanyalari kontrollu sekilde buyutebilirsiniz.',
            $summary['results'] > 0 => 'Hesap sonuc uretiyor ancak dikkat isteyen kampanyalar bulunuyor. Kontrollu optimizasyon gerekli.',
            $summary['spend'] > 0 => 'Hesap harcama yapiyor fakat secili aralikta yeterli sonuc gorunmuyor. Yeni test ve optimizasyon gerekli.',
            default => 'Secili aralikta bu hesap icin anlamli teslim verisi bulunmuyor.',
        };

        $operatorSummary = $alerts->isNotEmpty()
            ? sprintf('%d acik uyari mevcut. Once riskli kampanyalari kapatmadan once kok neden analizi yapin.', $alerts->count())
            : 'Acik uyari gorunmuyor. Kampanya bazli buyutme veya yeni test alanlarina odaklanabilirsiniz.';

        $nextStep = $alerts->isNotEmpty()
            ? 'En yuksek siddetteki uyaridan baslayarak kampanya, kreatif ve hedefleme kombinasyonlarini inceleyin.'
            : ($topCampaign
                ? sprintf('Ilk odak noktasi olarak "%s" kampanyasinin yapisini referans alin.', $topCampaign['name'])
                : 'Ilk odak noktasi olarak veri akisini ve son senkron durumunu dogrulayin.');

        return [
            'headline' => $headline,
            'client_summary' => $clientSummary,
            'operator_summary' => $operatorSummary,
            'next_step' => $nextStep,
        ];
    }
}
