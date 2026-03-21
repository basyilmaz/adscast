<?php

namespace App\Domain\Reporting\Services;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\Creative;
use App\Models\InsightDaily;
use App\Models\Recommendation;
use App\Support\Operations\ActionFeedService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignQueryService
{
    public function __construct(
        private readonly ActionFeedService $actionFeedService,
        private readonly ReportDeliveryProfileService $reportDeliveryProfileService,
        private readonly ReportRecipientGroupAdvisorService $reportRecipientGroupAdvisorService,
        private readonly ReportRecipientGroupAnalyticsService $reportRecipientGroupAnalyticsService,
        private readonly ReportRecipientGroupAlignmentAnalyticsService $reportRecipientGroupAlignmentAnalyticsService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function list(
        string $workspaceId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?string $adAccountId = null,
        ?string $objective = null,
        ?string $status = null,
    ): array
    {
        $campaigns = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->when(filled($adAccountId), fn ($query) => $query->where('meta_ad_account_id', $adAccountId))
            ->when(filled($objective), fn ($query) => $query->where('objective', $objective))
            ->when(filled($status), fn ($query) => $query->where('status', $status))
            ->with('adAccount:id,account_id,name')
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'meta_campaign_id',
                'meta_ad_account_id',
                'name',
                'objective',
                'status',
                'updated_at',
            ]);

        $insights = $this->performanceMap(
            workspaceId: $workspaceId,
            level: 'campaign',
            startDate: $startDate,
            endDate: $endDate,
            externalIds: $campaigns->pluck('meta_campaign_id')->filter()->values()->all(),
        );

        return [
            'items' => $campaigns->map(function (Campaign $campaign) use ($insights): array {
                $metric = $this->metricSnapshot($insights->get($campaign->meta_campaign_id));

                return [
                    'id' => $campaign->id,
                    'meta_campaign_id' => $campaign->meta_campaign_id,
                    'name' => $campaign->name,
                    'objective' => $campaign->objective,
                    'status' => $campaign->status,
                    'ad_account_id' => $campaign->adAccount?->id,
                    'ad_account_name' => $campaign->adAccount?->name,
                    'ad_account_external_id' => $campaign->adAccount?->account_id,
                    'spend' => $metric['spend'] ?? 0,
                    'results' => $metric['results'] ?? 0,
                    'cpa_cpl' => $metric['cpa_cpl'],
                    'ctr' => $metric['ctr'] ?? 0,
                    'cpm' => $metric['cpm'] ?? 0,
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
        $campaign->loadMissing('adAccount');

        $trend = $this->trendBundleForEntity(
            workspaceId: $campaign->workspace_id,
            level: 'campaign',
            externalId: $campaign->meta_campaign_id,
            startDate: $startDate,
            endDate: $endDate,
        );
        $summary = $this->trendSummary($trend, false);

        $alerts = Alert::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('entity_type', 'campaign')
            ->where('entity_id', $campaign->id)
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

        $recommendations = Recommendation::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('target_type', 'campaign')
            ->where('target_id', $campaign->id)
            ->where('status', 'open')
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

        $adSets = AdSet::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'meta_ad_set_id',
                'name',
                'status',
                'effective_status',
                'optimization_goal',
                'billing_event',
                'bid_strategy',
                'daily_budget',
                'lifetime_budget',
                'start_time',
                'stop_time',
                'targeting',
                'last_synced_at',
                'updated_at',
            ]);

        $ads = Ad::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('campaign_id', $campaign->id)
            ->with([
                'creative:id,meta_creative_id,name,asset_type,body,headline,description,call_to_action,destination_url',
                'adSet:id,name,meta_ad_set_id',
            ])
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'campaign_id',
                'ad_set_id',
                'creative_id',
                'meta_ad_id',
                'name',
                'status',
                'effective_status',
                'preview_url',
                'last_synced_at',
                'updated_at',
            ]);

        $adSetMetrics = $this->performanceMap(
            workspaceId: $campaign->workspace_id,
            level: 'adset',
            startDate: $startDate,
            endDate: $endDate,
            externalIds: $adSets->pluck('meta_ad_set_id')->filter()->values()->all(),
        );

        $adMetrics = $this->performanceMap(
            workspaceId: $campaign->workspace_id,
            level: 'ad',
            startDate: $startDate,
            endDate: $endDate,
            externalIds: $ads->pluck('meta_ad_id')->filter()->values()->all(),
        );

        $adsByAdSet = $ads->groupBy('ad_set_id');

        $adSetRows = $adSets->map(function (AdSet $adSet) use ($adSetMetrics, $adsByAdSet): array {
            $metric = $this->metricSnapshot($adSetMetrics->get($adSet->meta_ad_set_id), true);
            $adsCount = $adsByAdSet->get($adSet->id, collect())->count();
            $activeAds = $adsByAdSet->get($adSet->id, collect())->where('status', 'active')->count();
            $targetingSummary = $this->targetingSummary($adSet->targeting);
            $healthStatus = $this->adSetHealthStatus($adSet, $metric, $adsCount);

            return [
                'id' => $adSet->id,
                'meta_ad_set_id' => $adSet->meta_ad_set_id,
                'name' => $adSet->name,
                'status' => $adSet->status,
                'effective_status' => $adSet->effective_status,
                'optimization_goal' => $adSet->optimization_goal,
                'billing_event' => $adSet->billing_event,
                'bid_strategy' => $adSet->bid_strategy,
                'daily_budget' => $adSet->daily_budget !== null ? (float) $adSet->daily_budget : null,
                'lifetime_budget' => $adSet->lifetime_budget !== null ? (float) $adSet->lifetime_budget : null,
                'start_time' => $adSet->start_time?->toISOString(),
                'stop_time' => $adSet->stop_time?->toISOString(),
                'ads_count' => $adsCount,
                'active_ads' => $activeAds,
                'spend' => $metric['spend'],
                'results' => $metric['results'],
                'cpa_cpl' => $metric['cpa_cpl'],
                'ctr' => $metric['ctr'],
                'cpm' => $metric['cpm'],
                'frequency' => $metric['frequency'],
                'has_performance_data' => $metric['has_data'],
                'performance_scope' => $metric['has_data'] ? 'adset' : 'campaign_only',
                'targeting_summary' => $targetingSummary,
                'health_status' => $healthStatus,
                'health_summary' => $this->adSetHealthSummary($adSet, $healthStatus, $metric, $adsCount, $targetingSummary),
                'last_synced_at' => $adSet->last_synced_at,
            ];
        })->values();

        $adRows = $ads->map(function (Ad $ad) use ($adMetrics): array {
            $metric = $this->metricSnapshot($adMetrics->get($ad->meta_ad_id), true);
            $creative = $ad->creative;
            $healthStatus = $this->adHealthStatus($ad, $creative, $metric);

            return [
                'id' => $ad->id,
                'meta_ad_id' => $ad->meta_ad_id,
                'name' => $ad->name,
                'status' => $ad->status,
                'effective_status' => $ad->effective_status,
                'preview_url' => $ad->preview_url,
                'spend' => $metric['spend'],
                'results' => $metric['results'],
                'cpa_cpl' => $metric['cpa_cpl'],
                'ctr' => $metric['ctr'],
                'cpm' => $metric['cpm'],
                'frequency' => $metric['frequency'],
                'has_performance_data' => $metric['has_data'],
                'performance_scope' => $metric['has_data'] ? 'ad' : 'campaign_only',
                'ad_set' => [
                    'id' => $ad->adSet?->id,
                    'name' => $ad->adSet?->name,
                ],
                'creative' => [
                    'id' => $creative?->id,
                    'name' => $creative?->name,
                    'asset_type' => $creative?->asset_type,
                    'headline' => $creative?->headline,
                    'body' => $creative?->body,
                    'description' => $creative?->description,
                    'call_to_action' => $creative?->call_to_action,
                    'destination_url' => $creative?->destination_url,
                ],
                'health_status' => $healthStatus,
                'health_summary' => $this->adHealthSummary($ad, $creative, $healthStatus, $metric),
                'last_synced_at' => $ad->last_synced_at,
            ];
        })->values();

        $summary['active_ad_sets'] = $adSets->where('status', 'active')->count();
        $summary['active_ads'] = $ads->where('status', 'active')->count();
        $summary['open_alerts'] = $alerts->count();
        $summary['open_recommendations'] = $recommendations->count();

        $healthStatus = $this->campaignHealthStatus($campaign, $summary, $alerts->count());
        $alertPayload = $this->actionFeedService->presentAlerts($campaign->workspace_id, $alerts);
        $recommendationPayload = $this->actionFeedService->presentRecommendations($campaign->workspace_id, $recommendations);
        $deliveryProfile = $this->reportDeliveryProfileService->findByEntity(
            $campaign->workspace_id,
            'campaign',
            $campaign->id,
        );
        $recipientGroupAnalytics = $this->reportRecipientGroupAnalyticsService->forEntity(
            $campaign->workspace_id,
            'campaign',
            $campaign->id,
        );
        $recipientGroupAlignment = $this->reportRecipientGroupAlignmentAnalyticsService->forEntity(
            $campaign->workspace_id,
            'campaign',
            $campaign->id,
        );

        return [
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'meta_campaign_id' => $campaign->meta_campaign_id,
                'objective' => $campaign->objective,
                'status' => $campaign->status,
                'effective_status' => $campaign->effective_status,
                'buying_type' => $campaign->buying_type,
                'daily_budget' => $campaign->daily_budget !== null ? (float) $campaign->daily_budget : null,
                'lifetime_budget' => $campaign->lifetime_budget !== null ? (float) $campaign->lifetime_budget : null,
                'start_time' => $campaign->start_time?->toISOString(),
                'stop_time' => $campaign->stop_time?->toISOString(),
                'last_synced_at' => $campaign->last_synced_at,
                'ad_account' => [
                    'id' => $campaign->adAccount?->id,
                    'name' => $campaign->adAccount?->name,
                    'account_id' => $campaign->adAccount?->account_id,
                ],
            ],
            'health' => [
                'status' => $healthStatus,
                'summary' => $this->campaignHealthSummary($campaign, $healthStatus, $alerts->count(), (float) $summary['spend'], (float) $summary['results']),
            ],
            'summary' => $summary,
            'trend' => $trend['trend'],
            'ad_sets' => $adSetRows->all(),
            'ads' => $adRows->all(),
            'alerts' => $alertPayload['items'],
            'recommendations' => $recommendationPayload['items'],
            'delivery_profile' => $deliveryProfile,
            'recipient_group_analytics_summary' => $recipientGroupAnalytics['summary'],
            'recipient_group_analytics' => $recipientGroupAnalytics['items'],
            'recipient_group_alignment_summary' => $recipientGroupAlignment['summary'],
            'recipient_group_alignment' => $recipientGroupAlignment['items'],
            'suggested_recipient_groups' => $this->reportRecipientGroupAdvisorService->suggestForEntity(
                $campaign->workspace_id,
                'campaign',
                $campaign->id,
                $deliveryProfile,
            ),
            'next_best_actions' => $this->actionFeedService->nextBestActions(
                $campaign->workspace_id,
                $alerts,
                $recommendations,
                5,
            ),
            'analysis' => $this->campaignAnalysis($alerts, $recommendations, $summary),
            'report_preview' => $this->campaignReportPreview($campaign, $summary, $alerts, $recommendations),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adSetDetail(AdSet $adSet, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $adSet->loadMissing(['campaign.adAccount']);

        $ads = Ad::query()
            ->where('workspace_id', $adSet->workspace_id)
            ->where('ad_set_id', $adSet->id)
            ->with(['creative:id,meta_creative_id,name,asset_type,headline,body,call_to_action,destination_url'])
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'campaign_id',
                'ad_set_id',
                'creative_id',
                'meta_ad_id',
                'name',
                'status',
                'effective_status',
                'preview_url',
                'last_synced_at',
                'updated_at',
            ]);

        $trend = $this->trendBundleForEntity(
            workspaceId: $adSet->workspace_id,
            level: 'adset',
            externalId: $adSet->meta_ad_set_id,
            startDate: $startDate,
            endDate: $endDate,
        );

        $summary = $this->trendSummary($trend, true);
        $summary['active_ads'] = $ads->where('status', 'active')->count();
        $summary['total_ads'] = $ads->count();
        $summary['has_performance_data'] = $trend['has_data'];
        $summary['performance_scope'] = $trend['has_data'] ? 'adset' : 'campaign_only';

        $campaignAlerts = Alert::query()
            ->where('workspace_id', $adSet->workspace_id)
            ->where('entity_type', 'campaign')
            ->where('entity_id', $adSet->campaign_id)
            ->where('status', 'open')
            ->latest('date_detected')
            ->get([
                'id', 'code', 'severity', 'summary', 'recommended_action', 'date_detected',
            ]);

        $campaignRecommendations = Recommendation::query()
            ->where('workspace_id', $adSet->workspace_id)
            ->where('target_type', 'campaign')
            ->where('target_id', $adSet->campaign_id)
            ->where('status', 'open')
            ->latest('generated_at')
            ->get([
                'id', 'summary', 'details', 'priority', 'status', 'generated_at',
            ]);

        $siblingAdSets = AdSet::query()
            ->where('workspace_id', $adSet->workspace_id)
            ->where('campaign_id', $adSet->campaign_id)
            ->whereKeyNot($adSet->id)
            ->orderBy('name')
            ->get([
                'id', 'meta_ad_set_id', 'name', 'status', 'optimization_goal', 'daily_budget', 'targeting',
            ]);

        $siblingMetrics = $this->performanceMap(
            workspaceId: $adSet->workspace_id,
            level: 'adset',
            startDate: $startDate,
            endDate: $endDate,
            externalIds: $siblingAdSets->pluck('meta_ad_set_id')->filter()->values()->all(),
        );

        $adsMetrics = $this->performanceMap(
            workspaceId: $adSet->workspace_id,
            level: 'ad',
            startDate: $startDate,
            endDate: $endDate,
            externalIds: $ads->pluck('meta_ad_id')->filter()->values()->all(),
        );

        $targetingSummary = $this->targetingSummary($adSet->targeting);

        return [
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'ad_set' => [
                'id' => $adSet->id,
                'meta_ad_set_id' => $adSet->meta_ad_set_id,
                'name' => $adSet->name,
                'status' => $adSet->status,
                'effective_status' => $adSet->effective_status,
                'optimization_goal' => $adSet->optimization_goal,
                'billing_event' => $adSet->billing_event,
                'bid_strategy' => $adSet->bid_strategy,
                'daily_budget' => $adSet->daily_budget !== null ? (float) $adSet->daily_budget : null,
                'lifetime_budget' => $adSet->lifetime_budget !== null ? (float) $adSet->lifetime_budget : null,
                'start_time' => $adSet->start_time?->toISOString(),
                'stop_time' => $adSet->stop_time?->toISOString(),
                'last_synced_at' => $adSet->last_synced_at,
                'campaign' => [
                    'id' => $adSet->campaign?->id,
                    'name' => $adSet->campaign?->name,
                    'objective' => $adSet->campaign?->objective,
                ],
                'ad_account' => [
                    'id' => $adSet->campaign?->adAccount?->id,
                    'name' => $adSet->campaign?->adAccount?->name,
                    'account_id' => $adSet->campaign?->adAccount?->account_id,
                ],
            ],
            'summary' => $summary,
            'trend' => $trend['trend'],
            'targeting_summary' => $targetingSummary,
            'sibling_ad_sets' => $siblingAdSets->map(function (AdSet $sibling) use ($siblingMetrics): array {
                $metric = $this->metricSnapshot($siblingMetrics->get($sibling->meta_ad_set_id), true);

                return [
                    'id' => $sibling->id,
                    'name' => $sibling->name,
                    'status' => $sibling->status,
                    'optimization_goal' => $sibling->optimization_goal,
                    'daily_budget' => $sibling->daily_budget !== null ? (float) $sibling->daily_budget : null,
                    'spend' => $metric['spend'],
                    'results' => $metric['results'],
                    'has_performance_data' => $metric['has_data'],
                    'targeting_summary' => $this->targetingSummary($sibling->targeting),
                ];
            })->values()->all(),
            'ads' => $ads->map(function (Ad $ad) use ($adsMetrics): array {
                $metric = $this->metricSnapshot($adsMetrics->get($ad->meta_ad_id), true);

                return [
                    'id' => $ad->id,
                    'name' => $ad->name,
                    'status' => $ad->status,
                    'effective_status' => $ad->effective_status,
                    'preview_url' => $ad->preview_url,
                    'spend' => $metric['spend'],
                    'results' => $metric['results'],
                    'has_performance_data' => $metric['has_data'],
                    'creative' => [
                        'asset_type' => $ad->creative?->asset_type,
                        'headline' => $ad->creative?->headline,
                        'call_to_action' => $ad->creative?->call_to_action,
                    ],
                ];
            })->values()->all(),
            'inherited_alerts' => $campaignAlerts->map(fn (Alert $alert): array => [
                'id' => $alert->id,
                'severity' => $alert->severity,
                'summary' => $alert->summary,
                'recommended_action' => $alert->recommended_action,
                'date_detected' => optional($alert->date_detected)->toDateString(),
            ])->values()->all(),
            'inherited_recommendations' => $campaignRecommendations->map(fn (Recommendation $recommendation): array => [
                'id' => $recommendation->id,
                'priority' => $recommendation->priority,
                'summary' => $recommendation->summary,
                'details' => $recommendation->details,
                'generated_at' => optional($recommendation->generated_at)->toDateTimeString(),
            ])->values()->all(),
            'guidance' => $this->adSetGuidance($adSet, $summary, $targetingSummary, $campaignAlerts, $campaignRecommendations),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adDetail(Ad $ad, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $ad->loadMissing([
            'creative',
            'adSet.campaign.adAccount',
            'campaign.adAccount',
        ]);

        $trend = $this->trendBundleForEntity(
            workspaceId: $ad->workspace_id,
            level: 'ad',
            externalId: $ad->meta_ad_id,
            startDate: $startDate,
            endDate: $endDate,
        );

        $summary = $this->trendSummary($trend, true);
        $summary['has_preview'] = filled($ad->preview_url);
        $summary['performance_scope'] = $trend['has_data'] ? 'ad' : 'campaign_only';

        $campaignAlerts = Alert::query()
            ->where('workspace_id', $ad->workspace_id)
            ->where('entity_type', 'campaign')
            ->where('entity_id', $ad->campaign_id)
            ->where('status', 'open')
            ->latest('date_detected')
            ->get([
                'id', 'code', 'severity', 'summary', 'recommended_action', 'date_detected',
            ]);

        $campaignRecommendations = Recommendation::query()
            ->where('workspace_id', $ad->workspace_id)
            ->where('target_type', 'campaign')
            ->where('target_id', $ad->campaign_id)
            ->where('status', 'open')
            ->latest('generated_at')
            ->get([
                'id', 'summary', 'details', 'priority', 'status', 'generated_at',
            ]);

        $siblingAds = Ad::query()
            ->where('workspace_id', $ad->workspace_id)
            ->where('ad_set_id', $ad->ad_set_id)
            ->whereKeyNot($ad->id)
            ->with(['creative:id,headline,asset_type'])
            ->orderBy('name')
            ->get([
                'id', 'ad_set_id', 'creative_id', 'meta_ad_id', 'name', 'status', 'effective_status', 'preview_url',
            ]);

        $siblingMetrics = $this->performanceMap(
            workspaceId: $ad->workspace_id,
            level: 'ad',
            startDate: $startDate,
            endDate: $endDate,
            externalIds: $siblingAds->pluck('meta_ad_id')->filter()->values()->all(),
        );

        $creative = $ad->creative;

        return [
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'ad' => [
                'id' => $ad->id,
                'meta_ad_id' => $ad->meta_ad_id,
                'name' => $ad->name,
                'status' => $ad->status,
                'effective_status' => $ad->effective_status,
                'preview_url' => $ad->preview_url,
                'last_synced_at' => $ad->last_synced_at,
                'campaign' => [
                    'id' => $ad->campaign?->id,
                    'name' => $ad->campaign?->name,
                    'objective' => $ad->campaign?->objective,
                ],
                'ad_set' => [
                    'id' => $ad->adSet?->id,
                    'name' => $ad->adSet?->name,
                ],
                'ad_account' => [
                    'id' => $ad->campaign?->adAccount?->id,
                    'name' => $ad->campaign?->adAccount?->name,
                    'account_id' => $ad->campaign?->adAccount?->account_id,
                ],
            ],
            'summary' => $summary,
            'trend' => $trend['trend'],
            'creative' => [
                'id' => $creative?->id,
                'name' => $creative?->name,
                'asset_type' => $creative?->asset_type,
                'headline' => $creative?->headline,
                'body' => $creative?->body,
                'description' => $creative?->description,
                'call_to_action' => $creative?->call_to_action,
                'destination_url' => $creative?->destination_url,
            ],
            'sibling_ads' => $siblingAds->map(function (Ad $sibling) use ($siblingMetrics): array {
                $metric = $this->metricSnapshot($siblingMetrics->get($sibling->meta_ad_id), true);

                return [
                    'id' => $sibling->id,
                    'name' => $sibling->name,
                    'status' => $sibling->status,
                    'preview_url' => $sibling->preview_url,
                    'spend' => $metric['spend'],
                    'results' => $metric['results'],
                    'has_performance_data' => $metric['has_data'],
                    'creative' => [
                        'headline' => $sibling->creative?->headline,
                        'asset_type' => $sibling->creative?->asset_type,
                    ],
                ];
            })->values()->all(),
            'inherited_alerts' => $campaignAlerts->map(fn (Alert $alert): array => [
                'id' => $alert->id,
                'severity' => $alert->severity,
                'summary' => $alert->summary,
                'recommended_action' => $alert->recommended_action,
                'date_detected' => optional($alert->date_detected)->toDateString(),
            ])->values()->all(),
            'inherited_recommendations' => $campaignRecommendations->map(fn (Recommendation $recommendation): array => [
                'id' => $recommendation->id,
                'priority' => $recommendation->priority,
                'summary' => $recommendation->summary,
                'details' => $recommendation->details,
                'generated_at' => optional($recommendation->generated_at)->toDateTimeString(),
            ])->values()->all(),
            'guidance' => $this->adGuidance($ad, $creative, $summary, $campaignAlerts, $campaignRecommendations),
        ];
    }

    /**
     * @param array<int, string> $externalIds
     * @return Collection<string, object>
     */
    private function performanceMap(
        string $workspaceId,
        string $level,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        array $externalIds = [],
    ): Collection {
        $query = InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', $level)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($externalIds !== []) {
            $query->whereIn('entity_external_id', $externalIds);
        }

        return $query
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
     * @return array{trend: array<int, array<string, mixed>>, has_data: bool}
     */
    private function trendBundleForEntity(
        string $workspaceId,
        string $level,
        ?string $externalId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): array {
        $empty = [
            'trend' => $this->emptyTrend($startDate, $endDate, true),
            'has_data' => false,
        ];

        if (! filled($externalId)) {
            return $empty;
        }

        $rows = InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', $level)
            ->where('entity_external_id', $externalId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date, COALESCE(SUM(spend), 0) as spend, COALESCE(SUM(results), 0) as results, COALESCE(AVG(ctr), 0) as ctr, COALESCE(AVG(cpm), 0) as cpm, COALESCE(AVG(frequency), 0) as frequency')
            ->get()
            ->keyBy(fn ($row): string => Carbon::parse((string) $row->date)->toDateString());

        if ($rows->isEmpty()) {
            return $empty;
        }

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
                'ctr' => round((float) ($row->ctr ?? 0), 4),
                'cpm' => round((float) ($row->cpm ?? 0), 4),
                'frequency' => round((float) ($row->frequency ?? 0), 4),
            ];
            $cursor->addDay();
        }

        return [
            'trend' => $trend,
            'has_data' => true,
        ];
    }

    /**
     * @param array{trend: array<int, array<string, mixed>>, has_data: bool} $bundle
     * @return array<string, mixed>
     */
    private function trendSummary(array $bundle, bool $nullableWhenMissing): array
    {
        if (! $bundle['has_data'] && $nullableWhenMissing) {
            return [
                'spend' => null,
                'results' => null,
                'cpa_cpl' => null,
                'ctr' => null,
                'cpm' => null,
                'frequency' => null,
            ];
        }

        $trend = collect($bundle['trend']);
        $spend = (float) $trend->sum('spend');
        $results = (float) $trend->sum('results');

        return [
            'spend' => round($spend, 2),
            'results' => round($results, 2),
            'cpa_cpl' => $results > 0 ? round($spend / $results, 4) : null,
            'ctr' => round((float) ($trend->avg('ctr') ?? 0), 4),
            'cpm' => round((float) ($trend->avg('cpm') ?? 0), 4),
            'frequency' => round((float) ($trend->avg('frequency') ?? 0), 4),
        ];
    }

    /**
     * @param object|null $metric
     * @return array<string, mixed>
     */
    private function metricSnapshot(?object $metric, bool $nullableWhenMissing = false): array
    {
        if (! $metric) {
            return [
                'spend' => $nullableWhenMissing ? null : 0.0,
                'results' => $nullableWhenMissing ? null : 0.0,
                'cpa_cpl' => null,
                'ctr' => $nullableWhenMissing ? null : 0.0,
                'cpm' => $nullableWhenMissing ? null : 0.0,
                'frequency' => $nullableWhenMissing ? null : 0.0,
                'has_data' => false,
            ];
        }

        $spend = (float) ($metric->spend ?? 0);
        $results = (float) ($metric->results ?? 0);

        return [
            'spend' => round($spend, 2),
            'results' => round($results, 2),
            'cpa_cpl' => $results > 0 ? round($spend / $results, 4) : null,
            'ctr' => round((float) ($metric->ctr ?? 0), 4),
            'cpm' => round((float) ($metric->cpm ?? 0), 4),
            'frequency' => round((float) ($metric->frequency ?? 0), 4),
            'has_data' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emptyTrend(CarbonInterface $startDate, CarbonInterface $endDate, bool $includeMetrics): array
    {
        $trend = [];
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $row = [
                'date' => $cursor->toDateString(),
                'spend' => 0.0,
                'results' => 0.0,
            ];

            if ($includeMetrics) {
                $row['ctr'] = 0.0;
                $row['cpm'] = 0.0;
                $row['frequency'] = 0.0;
            }

            $trend[] = $row;
            $cursor->addDay();
        }

        return $trend;
    }

    /**
     * @param array<string, mixed>|null $targeting
     * @return array<string, mixed>
     */
    private function targetingSummary(?array $targeting): array
    {
        $targeting = $targeting ?? [];

        $interests = collect(data_get($targeting, 'flexible_spec', []))
            ->flatMap(function ($group) {
                if (! is_array($group)) {
                    return [];
                }

                return collect($group)
                    ->filter(fn ($item): bool => is_array($item) && isset($item['name']))
                    ->pluck('name');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'countries' => array_values(array_filter(data_get($targeting, 'geo_locations.countries', []))),
            'cities' => collect(data_get($targeting, 'geo_locations.cities', []))->pluck('name')->filter()->values()->all(),
            'age_range' => [
                'min' => data_get($targeting, 'age_min'),
                'max' => data_get($targeting, 'age_max'),
            ],
            'platforms' => array_values(array_filter(data_get($targeting, 'publisher_platforms', []))),
            'interests' => $interests,
            'custom_audiences' => collect(data_get($targeting, 'custom_audiences', []))->pluck('name')->filter()->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function campaignHealthStatus(Campaign $campaign, array $summary, int $alertCount): string
    {
        return match (true) {
            $campaign->status !== 'active' || ! $campaign->is_active => 'idle',
            $alertCount > 0 => 'warning',
            (float) $summary['spend'] > 0 && (float) $summary['results'] <= 0 => 'warning',
            (float) $summary['results'] > 0 => 'healthy',
            default => 'idle',
        };
    }

    private function campaignHealthSummary(Campaign $campaign, string $healthStatus, int $alertCount, float $spend, float $results): string
    {
        return match ($healthStatus) {
            'warning' => $alertCount > 0
                ? 'Bu kampanyada acik uyarilar var. Once riskleri temizleyin.'
                : 'Harcama var ancak sonuc yok. Kampanya yapisini ve landing sayfasini inceleyin.',
            'healthy' => 'Kampanya secili tarih araliginda sonuc uretiyor ve aktif teslim aliyor.',
            'idle' => $campaign->status === 'active' && $spend <= 0 && $results <= 0
                ? 'Kampanya aktif ancak secili aralikta veri uretmemis.'
                : 'Kampanya aktif degil veya teslim durmus durumda.',
            default => 'Kampanya genel olarak stabil.',
        };
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, string|null>
     */
    private function campaignAnalysis(Collection $alerts, Collection $recommendations, array $summary): array
    {
        /** @var Alert|null $topAlert */
        $topAlert = $alerts->sortByDesc(fn (Alert $alert): int => $this->priorityRank($alert->severity))->first();
        /** @var Recommendation|null $topRecommendation */
        $topRecommendation = $recommendations->sortByDesc(fn (Recommendation $recommendation): int => $this->priorityRank($recommendation->priority))->first();

        $clientNote = match (true) {
            (float) $summary['results'] > 0 && $alerts->isEmpty() => 'Kampanya sonuc uretiyor. Yeni testleri kontrollu sekilde acabilirsiniz.',
            (float) $summary['spend'] > 0 && (float) $summary['results'] <= 0 => 'Kampanya teslim aliyor ancak is sonucuna donusmuyor. Mesaj ve hedefleme yeniden ele alinmali.',
            default => 'Kampanya verisi sinirli. Yeni karar almadan once son teslim durumu kontrol edilmeli.',
        };

        return [
            'biggest_risk' => $topAlert?->summary,
            'biggest_opportunity' => $topRecommendation?->summary,
            'operator_note' => $topAlert?->recommended_action ?? $topRecommendation?->details,
            'client_note' => $clientNote,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, string>
     */
    private function campaignReportPreview(Campaign $campaign, array $summary, Collection $alerts, Collection $recommendations): array
    {
        $headline = (float) $summary['results'] > 0
            ? sprintf('%s kampanyasi secili aralikta %.2f harcama ile %.0f sonuc uretti.', $campaign->name, (float) $summary['spend'], (float) $summary['results'])
            : sprintf('%s kampanyasi secili aralikta %.2f harcama yapti ancak sonuc sinirli kaldi.', $campaign->name, (float) $summary['spend']);

        $clientSummary = $alerts->isEmpty()
            ? 'Kampanya sonuc uretiyor veya en azindan kritik risk gostermiyor. Bir sonraki test kontrollu sekilde acilabilir.'
            : 'Kampanya halen calisiyor ancak dikkat isteyen noktalar mevcut. Hedefleme, kreatif ve landing uyumu tekrar gozden gecirilmeli.';

        $operatorSummary = $alerts->isNotEmpty()
            ? sprintf('%d acik kampanya uyarisi var. Ilk odak riskli kreatif ve hedefleme kombinasyonlari olmali.', $alerts->count())
            : 'Acik uyari gorunmuyor. Buyutme ve yeni test adaylari degerlendirilebilir.';

        $nextTest = $recommendations->isNotEmpty()
            ? (string) ($recommendations->first()->summary ?? 'Yeni kreatif veya hedefleme varyasyonu test edin.')
            : 'Kazanani bozmadan yeni kreatif veya aci varyasyonu test edin.';

        return [
            'headline' => $headline,
            'client_summary' => $clientSummary,
            'operator_summary' => $operatorSummary,
            'next_test' => $nextTest,
            'next_step' => $alerts->isNotEmpty()
                ? 'En yuksek siddetli kampanya uyarisi ile baslayip sonra ad set ve reklam varyasyonlarini inceleyin.'
                : 'Ad set ve reklam seviyesinde yeni test planini hazirlayin.',
        ];
    }

    /**
     * @param array<string, mixed> $metric
     */
    private function adSetHealthStatus(AdSet $adSet, array $metric, int $adsCount): string
    {
        return match (true) {
            $adSet->status !== 'active' => 'idle',
            ! $metric['has_data'] => 'idle',
            (float) ($metric['spend'] ?? 0) > 0 && (float) ($metric['results'] ?? 0) <= 0 => 'warning',
            $adsCount === 0 => 'warning',
            default => 'healthy',
        };
    }

    /**
     * @param array<string, mixed> $metric
     * @param array<string, mixed> $targetingSummary
     */
    private function adSetHealthSummary(AdSet $adSet, string $healthStatus, array $metric, int $adsCount, array $targetingSummary): string
    {
        return match ($healthStatus) {
            'warning' => ! $metric['has_data']
                ? 'Bu ad set icin ayrik performans metrikleri henuz senkronize edilmemis olabilir.'
                : ((float) ($metric['results'] ?? 0) <= 0
                    ? 'Harcama var ancak sonuc yok. Bu ad sette hedefleme ve teklif stratejisini kontrol edin.'
                    : 'Teslim var ancak performans riskli gorunuyor.'),
            'healthy' => sprintf('%d aktif reklam ve tanimli hedefleme ile teslim aliyor.', $adsCount),
            'idle' => count($targetingSummary['countries']) > 0 || count($targetingSummary['cities']) > 0
                ? 'Hedefleme tanimli ancak secili aralikta belirgin veri yok.'
                : 'Bu ad sette aktif teslim veya yeterli hedefleme detayi gorunmuyor.',
            default => 'Ad set genel gorunumu stabil.',
        };
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $targetingSummary
     * @return array<string, string>
     */
    private function adSetGuidance(AdSet $adSet, array $summary, array $targetingSummary, Collection $alerts, Collection $recommendations): array
    {
        $locationSummary = count($targetingSummary['countries']) > 0
            ? implode(', ', $targetingSummary['countries'])
            : (count($targetingSummary['cities']) > 0 ? implode(', ', $targetingSummary['cities']) : 'belirgin lokasyon yok');

        return [
            'operator_summary' => $summary['has_performance_data']
                ? 'Bu ad seti sibling ad setlerle karsilastirip butce ve hedefleme farklarini inceleyin.'
                : 'Bu ad set icin ayrik performans metric synci yoksa kampanya baglamiyla karar alin.',
            'next_test' => $recommendations->isNotEmpty()
                ? (string) ($recommendations->first()->summary ?? 'Yeni hedefleme acisi test edin.')
                : 'Bu ad sette hedefleme acisi veya mesaj varyasyonu test edin.',
            'data_scope_note' => $summary['has_performance_data']
                ? 'Bu gorunum ad set seviyesinde normalized performans kullaniyor.'
                : 'Bu gorunumde ad set seviyesinde ayrik performans verisi yoksa kampanya baglami referans alinir.',
            'targeting_note' => sprintf('Bu ad setin temel lokasyon hedeflemesi: %s.', $locationSummary),
        ];
    }

    /**
     * @param array<string, mixed> $metric
     */
    private function adHealthStatus(Ad $ad, ?Creative $creative, array $metric): string
    {
        return match (true) {
            $ad->status !== 'active' => 'idle',
            ! $creative || ! filled($creative->headline) => 'warning',
            $metric['has_data'] && (float) ($metric['spend'] ?? 0) > 0 && (float) ($metric['results'] ?? 0) <= 0 => 'warning',
            $metric['has_data'] => 'healthy',
            default => 'idle',
        };
    }

    /**
     * @param array<string, mixed> $metric
     */
    private function adHealthSummary(Ad $ad, ?Creative $creative, string $healthStatus, array $metric): string
    {
        return match ($healthStatus) {
            'warning' => ! $creative || ! filled($creative->headline)
                ? 'Kreatif metni eksik veya okunabilir degil. Reklam icerigini kontrol edin.'
                : 'Reklam teslim aliyor ancak sonuc uretmiyor. Mesaj ve CTA yeniden gozden gecirilmeli.',
            'healthy' => 'Reklam ayrik performans verisiyle izlenebiliyor ve teslim aliyor.',
            'idle' => filled($ad->preview_url)
                ? 'Reklam hazir ancak secili aralikta ayrik teslim verisi sinirli.'
                : 'Reklam aktif degil veya onizleme baglantisi eksik.',
            default => 'Reklam genel gorunumu stabil.',
        };
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, string>
     */
    private function adGuidance(Ad $ad, ?Creative $creative, array $summary, Collection $alerts, Collection $recommendations): array
    {
        return [
            'creative_note' => $creative?->headline
                ? sprintf('Mevcut headline: "%s". Farkli bir aci ile ikinci varyasyon test edin.', $creative->headline)
                : 'Headline eksik veya belirsiz. Kreatif mesajini netlestirin.',
            'operator_summary' => $summary['has_preview']
                ? 'Bu reklam icin kreatif, CTA ve preview baglantisini birlikte degerlendirin.'
                : 'Preview baglantisi olmadigi icin yaratilanin Meta tarafindaki gorunurlugunu kontrol edin.',
            'data_scope_note' => $summary['performance_scope'] === 'ad'
                ? 'Bu reklam ayrik ad seviyesi performansla goruntuleniyor.'
                : 'Bu reklam icin ayrik ad seviyesi performans yoksa kampanya baglami referans alinir.',
            'risk_note' => $alerts->isNotEmpty()
                ? (string) ($alerts->first()->summary ?? 'Kampanya seviyesinde risk gorunuyor.')
                : ($recommendations->isNotEmpty()
                    ? (string) ($recommendations->first()->summary ?? 'Yeni kreatif testi planlayin.')
                    : 'Bu reklam icin kayitli risk veya oneri gorunmuyor.'),
        ];
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
