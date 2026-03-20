<?php

namespace App\Domain\Reporting\Services;

use App\Models\Alert;
use App\Models\Campaign;
use App\Models\InsightDaily;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\Recommendation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardQueryService
{
    /**
     * @return array<string, mixed>
     */
    public function getOverview(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $current = $this->aggregateMetrics($workspaceId, $startDate, $endDate);
        $campaignPerformanceRows = $this->campaignPerformanceRows($workspaceId, $startDate, $endDate);

        $days = $startDate->diffInDays($endDate) + 1;
        $previousStart = $startDate->copy()->subDays($days);
        $previousEnd = $startDate->copy()->subDay();
        $previous = $this->aggregateMetrics($workspaceId, $previousStart, $previousEnd);

        $campaigns = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->get([
                'id',
                'meta_ad_account_id',
                'meta_campaign_id',
                'name',
                'status',
                'effective_status',
                'objective',
                'is_active',
                'updated_at',
            ]);

        $accounts = MetaAdAccount::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get([
                'id',
                'account_id',
                'name',
                'status',
                'is_active',
                'currency',
                'last_synced_at',
            ]);

        $openAlerts = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'open')
            ->get([
                'id',
                'entity_type',
                'entity_id',
                'code',
                'severity',
                'summary',
                'explanation',
                'recommended_action',
                'date_detected',
                'created_at',
            ]);

        $alertsCount = $openAlerts->count();

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

        $openRecommendations = Recommendation::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'open')
            ->latest('generated_at')
            ->limit(10)
            ->get([
                'id',
                'target_type',
                'target_id',
                'summary',
                'details',
                'priority',
                'status',
                'generated_at',
            ]);

        $lastSyncAt = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->max('last_synced_at');

        $campaignStats = $this->campaignStats($workspaceId, $campaignPerformanceRows);
        $accountHealth = $this->accountHealth($accounts, $campaigns, $campaignPerformanceRows, $openAlerts);
        $activeCampaigns = $this->activeCampaigns($campaigns, $accounts, $campaignPerformanceRows, $openAlerts);
        $workspaceHealth = $this->workspaceHealth(
            $accounts,
            $campaigns,
            $activeCampaigns,
            $openAlerts,
            $openRecommendations,
            $lastSyncAt,
        );
        $urgentActions = $this->urgentActions(
            $openAlerts,
            $openRecommendations,
            $campaigns,
            $accounts,
        );

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
            'trend' => $this->workspaceTrend($workspaceId, $startDate, $endDate),
            'workspace_health' => $workspaceHealth,
            'account_health' => $accountHealth,
            'urgent_actions' => $urgentActions,
            'active_campaigns' => $activeCampaigns,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function aggregateMetrics(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $row = InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', 'campaign')
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
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function campaignPerformanceRows(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): Collection
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
            ->get();
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function campaignStats(string $workspaceId, Collection $rows): array
    {
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workspaceTrend(string $workspaceId, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $rows = InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', 'campaign')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date, COALESCE(SUM(spend), 0) as spend, COALESCE(SUM(results), 0) as results')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->date);

        $trend = [];
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);
            $trend[] = [
                'date' => $date,
                'spend' => (float) ($row->spend ?? 0),
                'results' => (float) ($row->results ?? 0),
            ];

            $cursor->addDay();
        }

        return $trend;
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceHealth(
        Collection $accounts,
        Collection $campaigns,
        array $activeCampaigns,
        Collection $openAlerts,
        Collection $openRecommendations,
        ?string $lastSyncAt,
    ): array {
        $activeAccounts = $accounts
            ->filter(fn (MetaAdAccount $account): bool => $account->is_active && $account->status === 'active')
            ->count();

        $activeCampaignCount = $campaigns
            ->filter(fn (Campaign $campaign): bool => $campaign->is_active && $campaign->status === 'active')
            ->count();

        $campaignsRequiringAttention = collect($activeCampaigns)
            ->filter(fn (array $campaign): bool => $campaign['health_status'] !== 'healthy')
            ->count();

        $summary = match (true) {
            $activeCampaignCount === 0 => 'Bu workspace icin aktif kampanya gorunmuyor.',
            $campaignsRequiringAttention > 0 => sprintf(
                '%d aktif kampanya takip veya aksiyon gerektiriyor.',
                $campaignsRequiringAttention
            ),
            $openAlerts->count() > 0 => sprintf(
                '%d acik uyari var. Kampanyalari kontrol ederek ilerleyin.',
                $openAlerts->count()
            ),
            default => 'Aktif kampanyalar stabil gorunuyor. Yeni test ve raporlama adimlarina odaklanabilirsiniz.',
        };

        return [
            'summary' => $summary,
            'active_accounts' => $activeAccounts,
            'total_accounts' => $accounts->count(),
            'active_campaigns' => $activeCampaignCount,
            'campaigns_requiring_attention' => $campaignsRequiringAttention,
            'open_alerts' => $openAlerts->count(),
            'open_recommendations' => $openRecommendations->count(),
            'last_synced_at' => $lastSyncAt,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accountHealth(
        Collection $accounts,
        Collection $campaigns,
        Collection $campaignPerformanceRows,
        Collection $openAlerts,
    ): array {
        $campaignsByAccount = $campaigns->groupBy('meta_ad_account_id');
        $performanceMap = $campaignPerformanceRows->keyBy('entity_external_id');
        $campaignAlerts = $openAlerts
            ->where('entity_type', 'campaign')
            ->groupBy('entity_id');

        return $accounts->map(function (MetaAdAccount $account) use ($campaignsByAccount, $performanceMap, $campaignAlerts): array {
            /** @var Collection<int, Campaign> $accountCampaigns */
            $accountCampaigns = $campaignsByAccount->get($account->id, collect());
            $activeCampaignCount = $accountCampaigns
                ->filter(fn (Campaign $campaign): bool => $campaign->is_active && $campaign->status === 'active')
                ->count();

            $metrics = $accountCampaigns->reduce(
                function (array $carry, Campaign $campaign) use ($performanceMap, $campaignAlerts): array {
                    $metric = $performanceMap->get($campaign->meta_campaign_id);
                    $carry['spend'] += (float) ($metric->spend ?? 0);
                    $carry['results'] += (float) ($metric->results ?? 0);
                    $carry['open_alerts'] += $campaignAlerts->get($campaign->id, collect())->count();

                    return $carry;
                },
                ['spend' => 0.0, 'results' => 0.0, 'open_alerts' => 0]
            );

            $healthStatus = match (true) {
                ! $account->is_active || $account->status !== 'active' => 'critical',
                $metrics['open_alerts'] > 0 => 'warning',
                $metrics['spend'] > 0 && $metrics['results'] <= 0 => 'warning',
                $activeCampaignCount === 0 => 'idle',
                default => 'healthy',
            };

            $healthSummary = match ($healthStatus) {
                'critical' => 'Hesap durumu aktif degil veya Meta tarafinda kisitli.',
                'warning' => $metrics['open_alerts'] > 0
                    ? sprintf('%d kampanya uyarisi acik. Yakindan takip edin.', $metrics['open_alerts'])
                    : 'Harcama var ancak sonuc sinirli. Kampanya yapisini gozden gecirin.',
                'idle' => 'Aktif kampanya yok. Yeni aksiyon gerekip gerekmedigini kontrol edin.',
                default => 'Hesap genel olarak stabil gorunuyor.',
            };

            return [
                'id' => $account->id,
                'account_id' => $account->account_id,
                'name' => $account->name,
                'status' => $account->status,
                'currency' => $account->currency,
                'active_campaigns' => $activeCampaignCount,
                'open_alerts' => $metrics['open_alerts'],
                'spend' => round($metrics['spend'], 2),
                'results' => round($metrics['results'], 2),
                'health_status' => $healthStatus,
                'health_summary' => $healthSummary,
                'last_synced_at' => $account->last_synced_at,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeCampaigns(
        Collection $campaigns,
        Collection $accounts,
        Collection $campaignPerformanceRows,
        Collection $openAlerts,
    ): array {
        $performanceMap = $campaignPerformanceRows->keyBy('entity_external_id');
        $campaignAlerts = $openAlerts
            ->where('entity_type', 'campaign')
            ->groupBy('entity_id');
        $accountNames = $accounts->pluck('name', 'id');
        $accountExternalIds = $accounts->pluck('account_id', 'id');

        return $campaigns
            ->filter(fn (Campaign $campaign): bool => $campaign->is_active && $campaign->status === 'active')
            ->map(function (Campaign $campaign) use ($performanceMap, $campaignAlerts, $accountNames, $accountExternalIds): array {
                $metric = $performanceMap->get($campaign->meta_campaign_id);
                $alerts = $campaignAlerts->get($campaign->id, collect());
                $spend = (float) ($metric->spend ?? 0);
                $results = (float) ($metric->results ?? 0);
                $healthStatus = match (true) {
                    $alerts->count() > 0 => 'warning',
                    $spend > 0 && $results <= 0 => 'warning',
                    $results > 0 => 'healthy',
                    default => 'idle',
                };

                $healthSummary = match ($healthStatus) {
                    'warning' => $alerts->count() > 0
                        ? 'Acil uyari var. Inceleme gerekli.'
                        : 'Harcama var ancak sonuc gorunmuyor.',
                    'healthy' => 'Son donemde sonuc uretiyor.',
                    default => 'Veri sinirli veya yeni.',
                };

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'account_name' => $accountNames[$campaign->meta_ad_account_id] ?? 'Bilinmeyen Hesap',
                    'account_external_id' => $accountExternalIds[$campaign->meta_ad_account_id] ?? null,
                    'objective' => $campaign->objective,
                    'status' => $campaign->status,
                    'spend' => round($spend, 2),
                    'results' => round($results, 2),
                    'ctr' => (float) ($metric->ctr ?? 0),
                    'cpm' => (float) ($metric->cpm ?? 0),
                    'frequency' => (float) ($metric->frequency ?? 0),
                    'open_alerts' => $alerts->count(),
                    'health_status' => $healthStatus,
                    'health_summary' => $healthSummary,
                ];
            })
            ->sortByDesc(function (array $item): array {
                return [
                    $item['open_alerts'],
                    $item['spend'],
                    $item['results'],
                ];
            })
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function urgentActions(
        Collection $openAlerts,
        Collection $openRecommendations,
        Collection $campaigns,
        Collection $accounts,
    ): array {
        $campaignMap = $campaigns->keyBy('id');
        $accountMap = $accounts->keyBy('id');

        $alertActions = $openAlerts
            ->map(function (Alert $alert) use ($campaignMap, $accountMap): array {
                $campaign = $campaignMap->get($alert->entity_id);
                $account = $campaign ? $accountMap->get($campaign->meta_ad_account_id) : null;

                return [
                    'id' => sprintf('alert:%s', $alert->id),
                    'source' => 'alert',
                    'priority' => $alert->severity,
                    'title' => $alert->summary,
                    'detail' => $alert->recommended_action ?? $alert->explanation,
                    'entity_type' => $alert->entity_type,
                    'entity_label' => $campaign?->name ?? 'Workspace',
                    'context_label' => $account?->name,
                    'detected_at' => optional($alert->date_detected)->toDateString(),
                    'priority_rank' => $this->priorityRank($alert->severity),
                    'timestamp' => $alert->created_at?->timestamp ?? 0,
                ];
            });

        $recommendationActions = $openRecommendations
            ->map(function (Recommendation $recommendation) use ($campaignMap, $accountMap): array {
                $campaign = $recommendation->target_type === 'campaign'
                    ? $campaignMap->get($recommendation->target_id)
                    : null;
                $account = $campaign ? $accountMap->get($campaign->meta_ad_account_id) : null;

                return [
                    'id' => sprintf('recommendation:%s', $recommendation->id),
                    'source' => 'recommendation',
                    'priority' => $recommendation->priority,
                    'title' => $recommendation->summary,
                    'detail' => $recommendation->details,
                    'entity_type' => $recommendation->target_type,
                    'entity_label' => $campaign?->name ?? 'Workspace',
                    'context_label' => $account?->name,
                    'detected_at' => optional($recommendation->generated_at)->toDateTimeString(),
                    'priority_rank' => $this->priorityRank($recommendation->priority),
                    'timestamp' => $recommendation->generated_at?->timestamp ?? 0,
                ];
            });

        return $alertActions
            ->concat($recommendationActions)
            ->sortByDesc(fn (array $item): array => [$item['priority_rank'], $item['timestamp']])
            ->take(6)
            ->values()
            ->map(function (array $item): array {
                unset($item['priority_rank'], $item['timestamp']);

                return $item;
            })
            ->all();
    }

    private function priorityRank(string $priority): int
    {
        return match ($priority) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}
