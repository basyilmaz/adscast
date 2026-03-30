<?php

namespace App\Domain\Approvals\Services;

use App\Models\Approval;
use App\Models\AuditLog;
use Illuminate\Support\Collection;

class ApprovalRemediationAnalyticsService
{
    public function __construct(
        private readonly ApprovalPayloadPresenter $approvalPayloadPresenter,
    ) {
    }

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     featured_recommendation: array<string, mixed>|null,
     *     route_series_spotlight: array<string, mixed>|null,
     *     route_outcome_spotlight: array<string, mixed>|null,
     *     route_outcome_window_series: array<int, array<string, mixed>>,
     *     interaction_sources: array<int, array<string, mixed>>,
     *     route_trends: array<int, array<string, mixed>>,
     *     long_term_route_trends: array<int, array<string, mixed>>,
     *     route_window_series: array<int, array<string, mixed>>,
     *     outcome_chain_summary: array<string, mixed>,
     *     approvals_native_outcome_summary: array<string, mixed>,
     *     draft_detail_outcome_summary: array<string, mixed>,
     *     long_term_approvals_native_outcome_summary: array<string, mixed>,
     *     long_term_draft_detail_outcome_summary: array<string, mixed>,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function build(string $workspaceId, int $windowDays = 30): array
    {
        $longTermWindowDays = max($windowDays, 90);
        $currentWindowStart = now()->subDays($windowDays);
        $longTermWindowStart = now()->subDays($longTermWindowDays);

        $presentedApprovals = Approval::query()
            ->where('workspace_id', $workspaceId)
            ->with('approvable')
            ->where('status', 'publish_failed')
            ->get()
            ->map(fn (Approval $approval): array => $this->approvalPayloadPresenter->present($approval));

        $auditLogs = AuditLog::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($query): void {
                $query
                    ->where('target_type', 'approval')
                    ->whereIn('action', ['approval_manual_check_completed', 'publish_attempted'])
                    ->orWhere(function ($nested): void {
                        $nested
                            ->where('target_type', 'approval_remediation')
                            ->where('action', 'approval_featured_remediation_tracked');
                    });
            })
            ->where('occurred_at', '>=', $longTermWindowStart)
            ->latest('occurred_at')
            ->get();

        $currentWindowAuditLogs = $auditLogs
            ->filter(fn (AuditLog $log): bool => $log->occurred_at >= $currentWindowStart)
            ->values();
        $longTermWindowAuditLogs = $auditLogs->values();

        $currentFeaturedInteractionLogs = $currentWindowAuditLogs->where('action', 'approval_featured_remediation_tracked');
        $longTermFeaturedInteractionLogs = $longTermWindowAuditLogs->where('action', 'approval_featured_remediation_tracked');

        $clusterDefinitions = collect($this->clusterDefinitions());
        $interactionSources = $this->sourceBreakdown($currentFeaturedInteractionLogs);
        $longTermInteractionSources = $this->sourceBreakdown($longTermFeaturedInteractionLogs);
        $routeTrends = $this->routeTrends($interactionSources);
        $longTermRouteTrends = $this->routeTrends($longTermInteractionSources);
        $outcomeChainSummary = $this->outcomeChainSummary($currentFeaturedInteractionLogs);
        $approvalsNativeOutcomeSummary = $this->approvalsNativeOutcomeSummary($currentFeaturedInteractionLogs);
        $draftDetailOutcomeSummary = $this->draftDetailOutcomeSummary($currentFeaturedInteractionLogs);
        $longTermApprovalsNativeOutcomeSummary = $this->approvalsNativeOutcomeSummary($longTermFeaturedInteractionLogs);
        $longTermDraftDetailOutcomeSummary = $this->draftDetailOutcomeSummary($longTermFeaturedInteractionLogs);
        $topInteractionSource = $interactionSources->first();
        $topSuccessSource = $interactionSources
            ->filter(fn (array $item): bool => $item['publish_success_rate'] !== null)
            ->sortByDesc(fn (array $item): float => (float) ($item['publish_success_rate'] ?? 0))
            ->first();
        $topRouteTrend = $routeTrends->first();
        $secondaryRouteTrend = $routeTrends->slice(1)->first();
        $topLongTermRouteTrend = $longTermRouteTrends->first();
        $secondaryLongTermRouteTrend = $longTermRouteTrends->slice(1)->first();
        $routeWindowSeries = $this->routeWindowSeries($longTermFeaturedInteractionLogs, $topRouteTrend);
        $clusterRouteWindowSeries = $this->clusterRouteWindowSeries($clusterDefinitions, $longTermFeaturedInteractionLogs);

        $currentRawItems = $this->buildClusterItems(
            $clusterDefinitions,
            $presentedApprovals,
            $currentWindowAuditLogs,
            $currentFeaturedInteractionLogs,
            $clusterRouteWindowSeries,
        );
        $longTermRawItems = $this->buildClusterItems(
            $clusterDefinitions,
            $presentedApprovals,
            $longTermWindowAuditLogs,
            $longTermFeaturedInteractionLogs,
            $clusterRouteWindowSeries,
        );

        $currentTopWorkingCluster = $currentRawItems
            ->filter(fn (array $item): bool => $item['publish_success_rate'] !== null)
            ->sortByDesc(fn (array $item): float => (float) $item['publish_success_rate'])
            ->first();

        $currentTopEffectiveCluster = $currentRawItems
            ->filter(fn (array $item): bool => ($item['effectiveness_score'] ?? 0) > 0)
            ->sortByDesc(fn (array $item): float => (float) ($item['effectiveness_score'] ?? 0))
            ->first();

        $longTermTopWorkingCluster = $longTermRawItems
            ->filter(fn (array $item): bool => $item['publish_success_rate'] !== null)
            ->sortByDesc(fn (array $item): float => (float) $item['publish_success_rate'])
            ->first();

        $currentItems = $currentRawItems
            ->map(fn (array $item): array => $this->applyRetryGuidance(
                $item,
                $currentTopWorkingCluster['publish_success_rate'] ?? null,
            ))
            ->values();

        $currentTopDraftDetailCluster = $currentItems
            ->filter(function (array $item): bool {
                return ($item['current_items'] ?? 0) > 0
                    && ((int) data_get($item, 'draft_detail_outcome_summary.publish_attempts', 0) > 0);
            })
            ->sortByDesc(function (array $item): float {
                $publishSuccessRate = (float) (data_get($item, 'draft_detail_outcome_summary.publish_success_rate') ?? 0);
                $publishAttempts = (int) data_get($item, 'draft_detail_outcome_summary.publish_attempts', 0);

                return $publishSuccessRate * 1000 + $publishAttempts * 10 + ((int) ($item['current_items'] ?? 0));
            })
            ->first();

        $longTermItems = $longTermRawItems
            ->map(fn (array $item): array => $this->applyRetryGuidance(
                $item,
                $longTermTopWorkingCluster['publish_success_rate'] ?? null,
            ))
            ->values();

        $topLongTermStableCluster = $this->topLongTermStableCluster($longTermItems);

        $items = $currentItems
            ->map(function (array $item) use ($longTermItems): array {
                $longTermItem = $longTermItems
                    ->first(fn (array $candidate): bool => $candidate['cluster_key'] === $item['cluster_key']);

                return [
                    ...$item,
                    ...$this->longTermClusterSignals($longTermItem),
                ];
            })
            ->values();

        $currentWindowHasSafeCluster = $currentItems
            ->contains(fn (array $item): bool => (bool) ($item['safe_bulk_retry'] ?? false));

        $featuredRecommendation = $this->buildFeaturedRecommendation(
            $items,
            $currentTopWorkingCluster,
            $currentTopEffectiveCluster,
            $currentTopDraftDetailCluster,
            $topLongTermStableCluster,
            $currentWindowHasSafeCluster,
            $approvalsNativeOutcomeSummary,
            $draftDetailOutcomeSummary,
            $windowDays,
            $longTermWindowDays,
        );
        $routeSeriesSpotlight = $this->routeSeriesSpotlight($featuredRecommendation);
        $routeOutcomeSpotlight = $this->routeOutcomeSpotlight($featuredRecommendation, $routeSeriesSpotlight);
        $routeOutcomeWindowSeries = $this->routeOutcomeWindowSeries(
            $longTermFeaturedInteractionLogs,
            $featuredRecommendation['primary_action'] ?? null,
        );
        $topRouteOutcomeWindow = $this->topRouteOutcomeWindow($routeOutcomeWindowSeries);
        $featuredSummary = $this->featuredSummary($currentFeaturedInteractionLogs);
        $featuredRecommendation = is_array($featuredRecommendation)
            ? [
                ...$featuredRecommendation,
                'route_outcome_window_series' => $routeOutcomeWindowSeries->all(),
            ]
            : null;

        return [
            'summary' => [
                'tracked_clusters' => $items->count(),
                'current_publish_failed' => $presentedApprovals->count(),
                'retry_ready_items' => $presentedApprovals
                    ->where('publish_state.recommended_action_code', 'retry_publish_after_manual_check')
                    ->count(),
                'manual_check_required_items' => $presentedApprovals
                    ->where('publish_state.recommended_action_code', 'manual_meta_check')
                    ->count(),
                'tracked_manual_checks' => $currentWindowAuditLogs->where('action', 'approval_manual_check_completed')->count(),
                'tracked_publish_attempts' => $currentWindowAuditLogs->where('action', 'publish_attempted')->count(),
                'successful_publish_attempts' => $currentWindowAuditLogs
                    ->where('action', 'publish_attempted')
                    ->filter(fn (AuditLog $log): bool => (bool) data_get($log->metadata, 'success', false))
                    ->count(),
                'top_working_cluster_label' => $currentTopWorkingCluster['label'] ?? null,
                'top_effective_cluster_label' => $currentTopEffectiveCluster['label'] ?? null,
                'top_effective_cluster_score' => $currentTopEffectiveCluster['effectiveness_score'] ?? null,
                'featured_cluster_label' => $featuredRecommendation['label'] ?? null,
                'top_draft_detail_cluster_label' => $currentTopDraftDetailCluster['label'] ?? null,
                'top_long_term_stable_cluster_label' => $topLongTermStableCluster['label'] ?? null,
                'top_long_term_stable_cluster_score' => $topLongTermStableCluster['effectiveness_score'] ?? null,
                'tracked_sources_count' => $interactionSources->count(),
                'top_interaction_source_key' => $topInteractionSource['source_key'] ?? null,
                'top_interaction_source_label' => $topInteractionSource['label'] ?? null,
                'top_success_source_key' => $topSuccessSource['source_key'] ?? null,
                'top_success_source_label' => $topSuccessSource['label'] ?? null,
                'top_route_key' => $topRouteTrend['route_key'] ?? null,
                'top_route_label' => $topRouteTrend['label'] ?? null,
                'top_route_source_key' => $topRouteTrend['top_source_key'] ?? null,
                'top_route_source_label' => $topRouteTrend['top_source_label'] ?? null,
                'top_route_publish_success_rate' => $topRouteTrend['publish_success_rate'] ?? null,
                'top_route_advantage' => $this->routeAdvantage($topRouteTrend, $secondaryRouteTrend),
                'top_long_term_route_key' => $topLongTermRouteTrend['route_key'] ?? null,
                'top_long_term_route_label' => $topLongTermRouteTrend['label'] ?? null,
                'top_long_term_route_source_key' => $topLongTermRouteTrend['top_source_key'] ?? null,
                'top_long_term_route_source_label' => $topLongTermRouteTrend['top_source_label'] ?? null,
                'top_long_term_route_publish_success_rate' => $topLongTermRouteTrend['publish_success_rate'] ?? null,
                'top_long_term_route_advantage' => $this->routeAdvantage($topLongTermRouteTrend, $secondaryLongTermRouteTrend),
                'top_route_series_status' => $routeSeriesSpotlight['status'] ?? null,
                'top_route_series_status_label' => $routeSeriesSpotlight['status_label'] ?? null,
                'top_route_series_reason' => $routeSeriesSpotlight['reason'] ?? null,
                'top_route_series_window_days' => $routeSeriesSpotlight['window_days'] ?? null,
                'top_route_series_route_key' => $routeSeriesSpotlight['route_key'] ?? null,
                'top_route_series_route_label' => $routeSeriesSpotlight['route_label'] ?? null,
                'top_route_outcome_status' => $routeOutcomeSpotlight['guidance_status'] ?? null,
                'top_route_outcome_status_label' => $routeOutcomeSpotlight['guidance_label'] ?? null,
                'top_route_outcome_reason' => $routeOutcomeSpotlight['guidance_reason'] ?? null,
                'top_route_outcome_recommended_action_mode' => $routeOutcomeSpotlight['recommended_action_mode'] ?? null,
                'top_route_outcome_recommended_action_label' => $routeOutcomeSpotlight['recommended_action_label'] ?? null,
                'top_route_outcome_window_days' => $topRouteOutcomeWindow['window_days'] ?? null,
                'top_route_outcome_window_label' => $topRouteOutcomeWindow['label'] ?? null,
                'top_route_outcome_window_status' => $topRouteOutcomeWindow['guidance_status'] ?? null,
                'top_route_outcome_window_status_label' => $topRouteOutcomeWindow['guidance_label'] ?? null,
                'top_route_outcome_window_reason' => $topRouteOutcomeWindow['guidance_reason'] ?? null,
                'top_route_outcome_window_recommended_action_mode' => $topRouteOutcomeWindow['recommended_action_mode'] ?? null,
                'top_route_outcome_window_recommended_action_label' => $topRouteOutcomeWindow['recommended_action_label'] ?? null,
                ...$featuredSummary,
                'window_days' => $windowDays,
                'long_term_window_days' => $longTermWindowDays,
            ],
            'featured_recommendation' => $featuredRecommendation,
            'route_series_spotlight' => $routeSeriesSpotlight,
            'route_outcome_spotlight' => $routeOutcomeSpotlight,
            'route_outcome_window_series' => $routeOutcomeWindowSeries->all(),
            'interaction_sources' => $interactionSources->all(),
            'route_trends' => $routeTrends->all(),
            'long_term_route_trends' => $longTermRouteTrends->all(),
            'route_window_series' => $routeWindowSeries->all(),
            'outcome_chain_summary' => $outcomeChainSummary,
            'approvals_native_outcome_summary' => $approvalsNativeOutcomeSummary,
            'draft_detail_outcome_summary' => $draftDetailOutcomeSummary,
            'long_term_approvals_native_outcome_summary' => $longTermApprovalsNativeOutcomeSummary,
            'long_term_draft_detail_outcome_summary' => $longTermDraftDetailOutcomeSummary,
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, recommended_action_code: string}>
     */
    private function clusterDefinitions(): array
    {
        return [
            [
                'key' => 'manual-check-required',
                'label' => 'Manuel Kontrol Bekleyenler',
                'description' => 'Cleanup basarisiz oldugu icin Meta tarafinda operator kontrolu gerekir.',
                'recommended_action_code' => 'manual_meta_check',
            ],
            [
                'key' => 'retry-ready',
                'label' => "Tekrar Publish'e Hazir",
                'description' => 'Manuel kontrolu tamamlanan ve yeniden publish denenebilecek kayitlar.',
                'recommended_action_code' => 'retry_publish_after_manual_check',
            ],
            [
                'key' => 'cleanup-recovered',
                'label' => 'Cleanup Ile Temizlenenler',
                'description' => 'Rollback sonrasi duzeltilip publish akisina geri alinabilecek kayitlar.',
                'recommended_action_code' => 'fix_and_retry_publish',
            ],
            [
                'key' => 'review-error',
                'label' => 'Dogrudan Hata Incelemesi',
                'description' => 'Partial publish birakmayan fakat publish hatasi uretecek kayitlar.',
                'recommended_action_code' => 'review_publish_error',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $approval
     */
    private function matchesCluster(array $approval, string $recommendedActionCode): bool
    {
        return data_get($approval, 'publish_state.recommended_action_code') === $recommendedActionCode;
    }

    private function healthStatus(string $recommendedActionCode, int $currentItems, int $publishAttempts, int $successfulPublishes): string
    {
        if ($recommendedActionCode === 'manual_meta_check') {
            return $currentItems > 0 ? 'attention' : 'stable';
        }

        if ($publishAttempts === 0) {
            return $currentItems > 0 ? 'pending_validation' : 'idle';
        }

        $rate = $successfulPublishes / max($publishAttempts, 1);

        if ($rate >= 0.7) {
            return 'working';
        }

        if ($rate >= 0.3) {
            return 'mixed';
        }

        return 'blocked';
    }

    private function healthSummary(string $recommendedActionCode, int $currentItems, int $publishAttempts, int $successfulPublishes): string
    {
        if ($recommendedActionCode === 'manual_meta_check') {
            return $currentItems > 0
                ? 'Bu cluster operator manuel kontrolunu bekliyor.'
                : 'Bu cluster icin bekleyen manuel kontrol yok.';
        }

        if ($publishAttempts === 0) {
            return $currentItems > 0
                ? 'Bu cluster icin henuz publish retry sonucu birikmedi.'
                : 'Bu cluster aktif degil.';
        }

        return sprintf(
            '%d publish denemesinin %d tanesi basarili oldu.',
            $publishAttempts,
            $successfulPublishes,
        );
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     * @param array<string, mixed>|null $topWorkingCluster
     * @param array<string, mixed>|null $topEffectiveCluster
     * @param array<string, mixed>|null $topDraftDetailCluster
     * @param array<string, mixed>|null $topLongTermStableCluster
     * @param bool $currentWindowHasSafeCluster
     * @param array<string, mixed> $approvalsNativeOutcomeSummary
     * @param array<string, mixed> $draftDetailOutcomeSummary
     * @return array<string, mixed>|null
     */
    private function buildFeaturedRecommendation(
        Collection $items,
        ?array $topWorkingCluster,
        ?array $topEffectiveCluster,
        ?array $topDraftDetailCluster,
        ?array $topLongTermStableCluster,
        bool $currentWindowHasSafeCluster,
        array $approvalsNativeOutcomeSummary,
        array $draftDetailOutcomeSummary,
        int $windowDays,
        int $longTermWindowDays,
    ): ?array
    {
        $manualCheckRequired = $items->first(
            fn (array $item): bool => $item['cluster_key'] === 'manual-check-required' && $item['current_items'] > 0
        );

        if (is_array($manualCheckRequired)) {
            $recommended = $this->withPrimaryAction($this->applyRetryGuidance(
                $manualCheckRequired,
                $manualCheckRequired['publish_success_rate'] ?? null,
            ));
            $routeSeriesSpotlight = $this->routeSeriesSpotlight($recommended);
            $routeOutcomeSpotlight = $this->routeOutcomeSpotlight($recommended, $routeSeriesSpotlight);

            return [
                ...$recommended,
                'route_series_spotlight' => $routeSeriesSpotlight,
                'route_outcome_spotlight' => $routeOutcomeSpotlight,
                'decision_context_route_series_status' => $routeSeriesSpotlight['status'] ?? null,
                'decision_context_route_series_reason' => $routeSeriesSpotlight['reason'] ?? null,
                'decision_context_route_series_window_days' => $routeSeriesSpotlight['window_days'] ?? null,
                'decision_context_route_series_success_rate' => $routeSeriesSpotlight['current_window_success_rate'] ?? null,
                'decision_context_route_outcome_status' => $routeOutcomeSpotlight['guidance_status'] ?? null,
                'decision_context_route_outcome_reason' => $routeOutcomeSpotlight['guidance_reason'] ?? null,
                'decision_context_route_outcome_recommended_action_mode' => $routeOutcomeSpotlight['recommended_action_mode'] ?? null,
                'decision_status' => 'manual_attention',
                'decision_reason' => 'Cleanup basarisiz kalan publish hatalari once manuel kontrol gerektiriyor.',
                'action_mode' => $this->featuredRecommendationActionMode($recommended, $routeSeriesSpotlight, $routeOutcomeSpotlight),
            ];
        }

        if (
            ! $currentWindowHasSafeCluster
            && is_array($topLongTermStableCluster)
            && ($topLongTermStableCluster['current_items'] ?? 0) > 0
            && (int) ($topLongTermStableCluster['publish_attempts'] ?? 0) >= 2
            && (float) ($topLongTermStableCluster['publish_success_rate'] ?? 0) >= 70.0
        ) {
            $currentReferenceSuccessRate = $topWorkingCluster['publish_success_rate']
                ?? $topEffectiveCluster['publish_success_rate']
                ?? $topDraftDetailCluster['publish_success_rate']
                ?? null;
            $recommended = $this->withPrimaryAction($this->applyRetryGuidance(
                $topLongTermStableCluster,
                $currentReferenceSuccessRate,
            ));
            $routeSeriesSpotlight = $this->routeSeriesSpotlight($recommended);
            $routeOutcomeSpotlight = $this->routeOutcomeSpotlight($recommended, $routeSeriesSpotlight);
            $longTermSuccessRate = (float) ($recommended['publish_success_rate'] ?? 0);

            return [
                ...$recommended,
                'route_series_spotlight' => $routeSeriesSpotlight,
                'route_outcome_spotlight' => $routeOutcomeSpotlight,
                'decision_context_route_series_status' => $routeSeriesSpotlight['status'] ?? null,
                'decision_context_route_series_reason' => $routeSeriesSpotlight['reason'] ?? null,
                'decision_context_route_series_window_days' => $routeSeriesSpotlight['window_days'] ?? null,
                'decision_context_route_series_success_rate' => $routeSeriesSpotlight['current_window_success_rate'] ?? null,
                'decision_context_route_outcome_status' => $routeOutcomeSpotlight['guidance_status'] ?? null,
                'decision_context_route_outcome_reason' => $routeOutcomeSpotlight['guidance_reason'] ?? null,
                'decision_context_route_outcome_recommended_action_mode' => $routeOutcomeSpotlight['recommended_action_mode'] ?? null,
                'decision_status' => 'long_term_preferred',
                'decision_reason' => sprintf(
                    'Son %d gunde uzun vade verisi bu clusterin daha stabil calistigini gosteriyor. Kisa vade sinyali yeterince guclu olmadigi icin uzun vade odagi one cikarildi.',
                    $longTermWindowDays,
                ),
                'decision_context_source' => 'long_term',
                'decision_context_window_days' => $longTermWindowDays,
                'decision_context_success_rate' => $longTermSuccessRate,
                'decision_context_baseline_success_rate' => $currentReferenceSuccessRate,
                'decision_context_advantage' => $currentReferenceSuccessRate !== null
                    ? round($longTermSuccessRate - (float) $currentReferenceSuccessRate, 1)
                    : null,
                'action_mode' => $this->featuredRecommendationActionMode($recommended, $routeSeriesSpotlight, $routeOutcomeSpotlight),
            ];
        }

        $draftDetailPublishSuccessRate = $draftDetailOutcomeSummary['publish_success_rate'] ?? null;
        $approvalsNativePublishSuccessRate = $approvalsNativeOutcomeSummary['publish_success_rate'] ?? null;
        $draftDetailLead = $draftDetailPublishSuccessRate !== null
            ? $draftDetailPublishSuccessRate - ($approvalsNativePublishSuccessRate ?? 0)
            : null;

        if (
            is_array($topDraftDetailCluster)
            && ($topDraftDetailCluster['current_items'] ?? 0) > 0
            && (int) data_get($topDraftDetailCluster, 'draft_detail_outcome_summary.publish_attempts', 0) >= 1
            && $draftDetailPublishSuccessRate !== null
            && $draftDetailLead !== null
            && $draftDetailLead >= 15
        ) {
            $recommended = $this->withPrimaryAction($this->applyRetryGuidance(
                $topDraftDetailCluster,
                $topWorkingCluster['publish_success_rate'] ?? null,
            ));
            $routeSeriesSpotlight = $this->routeSeriesSpotlight($recommended);
            $routeOutcomeSpotlight = $this->routeOutcomeSpotlight($recommended, $routeSeriesSpotlight);

            return [
                ...$recommended,
                'route_series_spotlight' => $routeSeriesSpotlight,
                'route_outcome_spotlight' => $routeOutcomeSpotlight,
                'decision_context_route_series_status' => $routeSeriesSpotlight['status'] ?? null,
                'decision_context_route_series_reason' => $routeSeriesSpotlight['reason'] ?? null,
                'decision_context_route_series_window_days' => $routeSeriesSpotlight['window_days'] ?? null,
                'decision_context_route_series_success_rate' => $routeSeriesSpotlight['current_window_success_rate'] ?? null,
                'decision_context_route_outcome_status' => $routeOutcomeSpotlight['guidance_status'] ?? null,
                'decision_context_route_outcome_reason' => $routeOutcomeSpotlight['guidance_reason'] ?? null,
                'decision_context_route_outcome_recommended_action_mode' => $routeOutcomeSpotlight['recommended_action_mode'] ?? null,
                'decision_status' => 'draft_detail_preferred',
                'decision_reason' => sprintf(
                    'Son %d gunde draft detail uzerinden takip edilen remediation aksiyonlari approvals merkezindeki dogrudan akislarin uzerinde sonuc uretti. Bu nedenle draft detail odaginda daha iyi calisan cluster one cikarildi.',
                    $windowDays,
                ),
                'decision_context_source' => 'draft_detail',
                'decision_context_success_rate' => $draftDetailPublishSuccessRate,
                'decision_context_advantage' => round($draftDetailLead, 1),
                'action_mode' => $this->featuredRecommendationActionMode($recommended, $routeSeriesSpotlight, $routeOutcomeSpotlight),
            ];
        }

        if (
            is_array($topEffectiveCluster)
            && ($topEffectiveCluster['current_items'] ?? 0) > 0
            && ($topEffectiveCluster['effectiveness_score'] ?? 0) >= 40
        ) {
            $recommended = $this->withPrimaryAction($this->applyRetryGuidance(
                $topEffectiveCluster,
                $topWorkingCluster['publish_success_rate'] ?? null,
            ));
            $routeSeriesSpotlight = $this->routeSeriesSpotlight($recommended);
            $routeOutcomeSpotlight = $this->routeOutcomeSpotlight($recommended, $routeSeriesSpotlight);

            return [
                ...$recommended,
                'route_series_spotlight' => $routeSeriesSpotlight,
                'route_outcome_spotlight' => $routeOutcomeSpotlight,
                'decision_context_route_series_status' => $routeSeriesSpotlight['status'] ?? null,
                'decision_context_route_series_reason' => $routeSeriesSpotlight['reason'] ?? null,
                'decision_context_route_series_window_days' => $routeSeriesSpotlight['window_days'] ?? null,
                'decision_context_route_series_success_rate' => $routeSeriesSpotlight['current_window_success_rate'] ?? null,
                'decision_context_route_outcome_status' => $routeOutcomeSpotlight['guidance_status'] ?? null,
                'decision_context_route_outcome_reason' => $routeOutcomeSpotlight['guidance_reason'] ?? null,
                'decision_context_route_outcome_recommended_action_mode' => $routeOutcomeSpotlight['recommended_action_mode'] ?? null,
                'decision_status' => 'effectiveness_preferred',
                'decision_reason' => sprintf(
                    'Son %d gunun effectiveness skoruna gore publish toparlama ihtimali en guclu remediation cluster one cikarildi.',
                    $windowDays,
                ),
                'action_mode' => $this->featuredRecommendationActionMode($recommended, $routeSeriesSpotlight, $routeOutcomeSpotlight),
            ];
        }

        if (is_array($topWorkingCluster) && ($topWorkingCluster['current_items'] ?? 0) > 0) {
            $recommended = $this->withPrimaryAction($this->applyRetryGuidance(
                $topWorkingCluster,
                $topWorkingCluster['publish_success_rate'] ?? null,
            ));
            $routeSeriesSpotlight = $this->routeSeriesSpotlight($recommended);
            $routeOutcomeSpotlight = $this->routeOutcomeSpotlight($recommended, $routeSeriesSpotlight);

            return [
                ...$recommended,
                'route_series_spotlight' => $routeSeriesSpotlight,
                'route_outcome_spotlight' => $routeOutcomeSpotlight,
                'decision_context_route_series_status' => $routeSeriesSpotlight['status'] ?? null,
                'decision_context_route_series_reason' => $routeSeriesSpotlight['reason'] ?? null,
                'decision_context_route_series_window_days' => $routeSeriesSpotlight['window_days'] ?? null,
                'decision_context_route_series_success_rate' => $routeSeriesSpotlight['current_window_success_rate'] ?? null,
                'decision_context_route_outcome_status' => $routeOutcomeSpotlight['guidance_status'] ?? null,
                'decision_context_route_outcome_reason' => $routeOutcomeSpotlight['guidance_reason'] ?? null,
                'decision_context_route_outcome_recommended_action_mode' => $routeOutcomeSpotlight['recommended_action_mode'] ?? null,
                'decision_status' => 'analytics_preferred',
                'decision_reason' => sprintf(
                    'Son %d gunun publish sonucuna gore su an en iyi toparlayan remediation cluster one cikarildi.',
                    $windowDays,
                ),
                'action_mode' => $this->featuredRecommendationActionMode($recommended, $routeSeriesSpotlight, $routeOutcomeSpotlight),
            ];
        }

        $fallback = $items->first(fn (array $item): bool => $item['current_items'] > 0);

        if (! is_array($fallback)) {
            return null;
        }

        $recommended = $this->withPrimaryAction($this->applyRetryGuidance(
            $fallback,
            $topWorkingCluster['publish_success_rate'] ?? null,
        ));
        $routeSeriesSpotlight = $this->routeSeriesSpotlight($recommended);
        $routeOutcomeSpotlight = $this->routeOutcomeSpotlight($recommended, $routeSeriesSpotlight);

        return [
            ...$recommended,
            'route_series_spotlight' => $routeSeriesSpotlight,
            'route_outcome_spotlight' => $routeOutcomeSpotlight,
            'decision_context_route_series_status' => $routeSeriesSpotlight['status'] ?? null,
            'decision_context_route_series_reason' => $routeSeriesSpotlight['reason'] ?? null,
            'decision_context_route_series_window_days' => $routeSeriesSpotlight['window_days'] ?? null,
            'decision_context_route_series_success_rate' => $routeSeriesSpotlight['current_window_success_rate'] ?? null,
            'decision_context_route_outcome_status' => $routeOutcomeSpotlight['guidance_status'] ?? null,
            'decision_context_route_outcome_reason' => $routeOutcomeSpotlight['guidance_reason'] ?? null,
            'decision_context_route_outcome_recommended_action_mode' => $routeOutcomeSpotlight['recommended_action_mode'] ?? null,
            'decision_status' => 'rule_based',
                'decision_reason' => sprintf(
                    'Son %d gun icinde yeterli publish sonucu olmadigi icin aktif remediation durumuna gore kurala dayali oncelik secildi.',
                    $windowDays,
                ),
            'action_mode' => $this->featuredRecommendationActionMode($recommended, $routeSeriesSpotlight, $routeOutcomeSpotlight),
        ];
    }

    /**
     * @param array<string, mixed> $cluster
     * @param float|null $baselineSuccessRate
     * @return array<string, mixed>
     */
    private function applyRetryGuidance(array $cluster, ?float $baselineSuccessRate): array
    {
        $retryableClusterKeys = ['retry-ready', 'cleanup-recovered'];
        $clusterKey = (string) ($cluster['cluster_key'] ?? '');
        $currentItems = (int) ($cluster['current_items'] ?? 0);
        $publishAttempts = (int) ($cluster['publish_attempts'] ?? 0);
        $publishSuccessRate = isset($cluster['publish_success_rate']) ? (float) $cluster['publish_success_rate'] : null;
        $featuredFollowRate = isset($cluster['featured_follow_rate']) ? (float) $cluster['featured_follow_rate'] : null;
        $baselineSuccessRate = $baselineSuccessRate ?? $publishSuccessRate;

        if ($currentItems <= 0) {
            return [
                ...$cluster,
                'retry_guidance_status' => 'blocked',
                'retry_guidance_label' => 'Aktif Kayit Yok',
                'retry_guidance_reason' => 'Bu cluster icin aktif approval kaydi bulunmuyor; toplu retry uygulanamaz.',
                'safe_bulk_retry' => false,
            ];
        }

        if (! in_array($clusterKey, $retryableClusterKeys, true)) {
            return [
                ...$cluster,
                'retry_guidance_status' => 'blocked',
                'retry_guidance_label' => 'Toplu Retry Uygun Degil',
                'retry_guidance_reason' => 'Bu cluster toplu publish retry yerine manuel inceleme odaginda tutulmali.',
                'safe_bulk_retry' => false,
            ];
        }

        if ($publishAttempts === 0 || $publishSuccessRate === null) {
            return [
                ...$cluster,
                'retry_guidance_status' => 'guarded',
                'retry_guidance_label' => 'Veri Bekleniyor',
                'retry_guidance_reason' => sprintf(
                    'Bu cluster icin yeterli publish verisi yok. Acik kayit: %d, baseline basari: %s, featured takip: %s.',
                    $currentItems,
                    $baselineSuccessRate !== null ? sprintf('%%%s', $this->formatRate($baselineSuccessRate)) : 'yok',
                    $featuredFollowRate !== null ? sprintf('%%%s', $this->formatRate($featuredFollowRate)) : 'yok',
                ),
                'safe_bulk_retry' => false,
            ];
        }

        $baselineSuccessRate = $baselineSuccessRate ?? $publishSuccessRate;
        $successDelta = round($publishSuccessRate - $baselineSuccessRate, 1);

        if ($publishSuccessRate >= $baselineSuccessRate && ($featuredFollowRate ?? 0) >= 60.0) {
            return [
                ...$cluster,
                'retry_guidance_status' => 'safe',
                'retry_guidance_label' => 'Toplu Retry Guvenli',
                'retry_guidance_reason' => sprintf(
                    'Window basari %s, baseline %s, featured takip %s ve acik kayit %d ile toplu retry guvenli gorunuyor.',
                    $this->formatPercentage($publishSuccessRate),
                    $this->formatPercentage($baselineSuccessRate),
                    $featuredFollowRate !== null ? $this->formatPercentage($featuredFollowRate) : 'yok',
                    $currentItems,
                ),
                'safe_bulk_retry' => true,
            ];
        }

        return [
            ...$cluster,
            'retry_guidance_status' => 'guarded',
            'retry_guidance_label' => 'Izlenmeli',
            'retry_guidance_reason' => sprintf(
                'Window basari %s baseline %s seviyesinin %s; featured takip %s ve acik kayit %d nedeniyle toplu retry icin ekstra dikkat gerekli.',
                $this->formatPercentage($publishSuccessRate),
                $this->formatPercentage($baselineSuccessRate),
                $successDelta >= 0 ? 'uzerinde' : 'altinda',
                $featuredFollowRate !== null ? $this->formatPercentage($featuredFollowRate) : 'yok',
                $currentItems,
            ),
            'safe_bulk_retry' => false,
        ];
    }

    /**
     * @param array<string, mixed> $cluster
     */
    private function retryGuidedActionMode(array $cluster, ?array $routeSeriesSpotlight = null): string
    {
        if (! (bool) ($cluster['safe_bulk_retry'] ?? false)) {
            return 'focus_cluster';
        }

        $routeKey = (string) data_get($cluster, 'primary_action.route_key', '');
        $trendStatus = (string) data_get($cluster, 'primary_action.trend_status', 'missing');
        $spotlightStatus = (string) ($routeSeriesSpotlight['status'] ?? $trendStatus);

        if ($routeKey === 'draft_detail' && in_array($spotlightStatus, ['softening', 'sparse'], true)) {
            return 'focus_cluster';
        }

        if (in_array($spotlightStatus, ['softening', 'sparse'], true) && $routeKey !== 'approvals') {
            return 'focus_cluster';
        }

        return 'bulk_retry_publish';
    }

    /**
     * @param array<string, mixed>|null $recommended
     * @return array<string, mixed>|null
     */
    private function routeSeriesSpotlight(?array $recommended): ?array
    {
        if (! is_array($recommended)) {
            return null;
        }

        $primaryAction = is_array($recommended['primary_action'] ?? null)
            ? $recommended['primary_action']
            : null;
        $series = collect($primaryAction['route_series'] ?? $recommended['route_window_series'] ?? []);

        if ($series->isEmpty()) {
            return null;
        }

        $series = $series->sortBy('window_days')->values();
        $currentWindow = $series->first();
        $longTermWindow = $series->last();
        $status = (string) ($primaryAction['trend_status'] ?? ($currentWindow['support_status'] ?? 'missing'));

        return [
            'route_key' => $primaryAction['route_key'] ?? ($currentWindow['route_key'] ?? null),
            'route_label' => $primaryAction['route_label'] ?? ($currentWindow['route_label'] ?? null),
            'preferred_flow' => $primaryAction['preferred_flow'] ?? ($currentWindow['preferred_flow'] ?? null),
            'status' => $status,
            'status_label' => $this->routeSupportLabel($status),
            'reason' => $primaryAction['trend_reason'] ?? ($currentWindow['reason'] ?? null),
            'window_days' => $currentWindow['window_days'] ?? null,
            'current_window_days' => $currentWindow['window_days'] ?? null,
            'current_window_support_status' => $currentWindow['support_status'] ?? null,
            'current_window_success_rate' => $currentWindow['publish_success_rate'] ?? null,
            'current_window_summary_label' => $currentWindow['summary_label'] ?? null,
            'long_term_window_days' => $longTermWindow['window_days'] ?? null,
            'long_term_window_support_status' => $longTermWindow['support_status'] ?? null,
            'long_term_window_success_rate' => $longTermWindow['publish_success_rate'] ?? null,
            'long_term_window_summary_label' => $longTermWindow['summary_label'] ?? null,
            'route_series' => $series->all(),
        ];
    }

    /**
     * @param array<string, mixed>|null $recommended
     * @param array<string, mixed>|null $routeSeriesSpotlight
     * @return array<string, mixed>|null
     */
    private function routeOutcomeSpotlight(?array $recommended, ?array $routeSeriesSpotlight = null): ?array
    {
        $routeSeriesSpotlight ??= $this->routeSeriesSpotlight($recommended);

        if (! is_array($routeSeriesSpotlight)) {
            return null;
        }

        $recommendedActionMode = $this->routeOutcomeActionMode($recommended ?? [], $routeSeriesSpotlight);
        $guidanceStatus = $this->routeOutcomeGuidanceStatus($routeSeriesSpotlight, $recommendedActionMode);

        return [
            ...$routeSeriesSpotlight,
            'guidance_status' => $guidanceStatus,
            'guidance_label' => $this->routeOutcomeGuidanceLabel($guidanceStatus),
            'guidance_reason' => $this->routeOutcomeGuidanceReason($routeSeriesSpotlight, $recommendedActionMode, $guidanceStatus),
            'recommended_action_mode' => $recommendedActionMode,
            'recommended_action_label' => $this->routeOutcomeActionLabel($recommendedActionMode),
            'decision_context_source' => $this->routeOutcomeDecisionContextSource($routeSeriesSpotlight, $recommendedActionMode),
            'decision_context_window_days' => $this->routeOutcomeDecisionContextWindowDays($routeSeriesSpotlight, $recommendedActionMode),
            'decision_context_success_rate' => $this->routeOutcomeDecisionContextSuccessRate($routeSeriesSpotlight, $recommendedActionMode),
        ];
    }

    /**
     * @param array<string, mixed> $routeWindow
     */
    private function routeOutcomeWindowSupportStatus(string $routeKey, ?float $successRate, ?float $advantage): string
    {
        if ($successRate === null) {
            return 'missing';
        }

        if ($successRate >= 70.0) {
            return 'proven';
        }

        if ($routeKey === 'draft_detail') {
            if ($successRate >= 50.0 || ($advantage !== null && $advantage >= 3.0)) {
                return 'emerging';
            }

            return 'guarded';
        }

        if ($successRate >= 55.0 || ($advantage !== null && $advantage >= 3.0)) {
            return 'emerging';
        }

        return 'guarded';
    }

    private function routeOutcomeWindowGuidanceStatus(string $currentSupportStatus, string $topSupportStatus): string
    {
        if ($currentSupportStatus === 'missing' && $topSupportStatus === 'missing') {
            return 'guarded';
        }

        if ($currentSupportStatus === 'proven' && $topSupportStatus === 'proven') {
            return 'safe';
        }

        if (in_array($currentSupportStatus, ['proven', 'emerging'], true) || in_array($topSupportStatus, ['proven', 'emerging'], true)) {
            return 'watching';
        }

        return 'guarded';
    }

    /**
     * @param array<string, mixed> $routeWindow
     */
    private function routeOutcomeWindowActionMode(
        array $routeWindow,
        string $guidanceStatus,
        string $currentSupportStatus,
        string $topSupportStatus,
    ): string {
        $routeKey = (string) ($routeWindow['route_key'] ?? $routeWindow['current_route_key'] ?? $routeWindow['top_route_key'] ?? 'other');

        if (in_array($guidanceStatus, ['guarded', 'blocked'], true)) {
            return 'focus_cluster';
        }

        $supportsItem = in_array($currentSupportStatus, ['proven', 'emerging'], true) && in_array($topSupportStatus, ['proven', 'emerging'], true);

        if ($routeKey === 'draft_detail') {
            return $supportsItem ? 'jump_to_item' : 'focus_cluster';
        }

        if ($routeKey === 'approvals') {
            return $supportsItem ? 'bulk_retry_publish' : 'focus_cluster';
        }

        return $supportsItem ? 'bulk_retry_publish' : 'focus_cluster';
    }

    /**
     * @param array<string, mixed> $routeWindow
     */
    private function routeOutcomeWindowGuidanceReason(
        array $routeWindow,
        string $currentSupportStatus,
        string $topSupportStatus,
        string $guidanceStatus,
        string $recommendedActionMode,
    ): string {
        $windowLabel = (string) ($routeWindow['label'] ?? $routeWindow['window_days'] ?? 'pencere');
        $routeLabel = (string) ($routeWindow['route_label'] ?? $routeWindow['current_route_label'] ?? $routeWindow['top_route_label'] ?? 'route');

        return match ($guidanceStatus) {
            'safe' => sprintf('%s icin %s yolu hem kisa hem uzun vadede guvenli; onerilen aksiyon %s olarak korunabilir.', $windowLabel, $routeLabel, $this->routeOutcomeActionLabel($recommendedActionMode)),
            'watching' => sprintf('%s icin %s yolu en az bir tarafta destekli; sistem bu pencereyi takip ediyor.', $windowLabel, $routeLabel),
            default => sprintf('%s icin %s yolu yeterince stabil degil; odak guvenli cluster incelemesine donmeli.', $windowLabel, $routeLabel),
        };
    }

    /**
     * @param array<string, mixed> $routeWindow
     */
    private function routeOutcomeWindowScore(array $routeWindow): float
    {
        $statusWeight = match ((string) ($routeWindow['guidance_status'] ?? 'guarded')) {
            'safe' => 4,
            'watching' => 3,
            'guarded' => 2,
            'blocked' => 1,
            default => 0,
        };

        return ((float) $statusWeight * 1000)
            + ((float) ($routeWindow['success_rate'] ?? 0) * 10)
            + ((float) ($routeWindow['window_days'] ?? 0));
    }

    /**
     * @param array<string, mixed> $cluster
     */
    private function routeOutcomeActionMode(array $cluster, ?array $routeSeriesSpotlight = null): string
    {
        if (! (bool) ($cluster['safe_bulk_retry'] ?? false)) {
            return 'focus_cluster';
        }

        $routeSeriesSpotlight ??= $this->routeSeriesSpotlight($cluster);

        if (! is_array($routeSeriesSpotlight)) {
            return 'focus_cluster';
        }

        $routeKey = (string) data_get($cluster, 'primary_action.route_key', '');
        $spotlightStatus = (string) ($routeSeriesSpotlight['status'] ?? 'missing');
        $currentSupportStatus = (string) ($routeSeriesSpotlight['current_window_support_status'] ?? 'missing');
        $longTermSupportStatus = (string) ($routeSeriesSpotlight['long_term_window_support_status'] ?? 'missing');
        $currentWindowSuccessRate = isset($routeSeriesSpotlight['current_window_success_rate'])
            ? (float) $routeSeriesSpotlight['current_window_success_rate']
            : null;
        $longTermWindowSuccessRate = isset($routeSeriesSpotlight['long_term_window_success_rate'])
            ? (float) $routeSeriesSpotlight['long_term_window_success_rate']
            : null;

        if (in_array($spotlightStatus, ['softening', 'sparse'], true)) {
            return 'focus_cluster';
        }

        $currentSupportIsStrong = in_array($currentSupportStatus, ['proven', 'emerging'], true);
        $longTermSupportIsStrong = in_array($longTermSupportStatus, ['proven', 'emerging'], true);

        if ($routeKey === 'draft_detail') {
            return $currentSupportIsStrong && $longTermSupportIsStrong ? 'jump_to_item' : 'focus_cluster';
        }

        if ($routeKey === 'approvals') {
            if ($currentSupportIsStrong && $longTermSupportIsStrong) {
                return 'bulk_retry_publish';
            }

            if ($currentWindowSuccessRate !== null && $currentWindowSuccessRate >= 70.0 && $longTermWindowSuccessRate !== null && $longTermWindowSuccessRate >= 70.0) {
                return 'bulk_retry_publish';
            }

            return 'focus_cluster';
        }

        return $currentSupportIsStrong && $longTermSupportIsStrong ? 'bulk_retry_publish' : 'focus_cluster';
    }

    /**
     * @param array<string, mixed> $cluster
     */
    private function featuredRecommendationActionMode(
        array $cluster,
        ?array $routeSeriesSpotlight = null,
        ?array $routeOutcomeSpotlight = null,
    ): string {
        $routeOutcomeSpotlight ??= $this->routeOutcomeSpotlight($cluster, $routeSeriesSpotlight);

        if (! is_array($routeOutcomeSpotlight)) {
            return $this->retryGuidedActionMode($cluster, $routeSeriesSpotlight);
        }

        $guidanceStatus = (string) ($routeOutcomeSpotlight['guidance_status'] ?? 'guarded');
        $recommendedActionMode = (string) ($routeOutcomeSpotlight['recommended_action_mode'] ?? $this->retryGuidedActionMode($cluster, $routeSeriesSpotlight));

        if (in_array($guidanceStatus, ['guarded', 'blocked', 'watching'], true)) {
            return 'focus_cluster';
        }

        return $recommendedActionMode;
    }

    /**
     * @param array<string, mixed> $routeSeriesSpotlight
     */
    private function routeOutcomeGuidanceStatus(array $routeSeriesSpotlight, string $recommendedActionMode): string
    {
        $spotlightStatus = (string) ($routeSeriesSpotlight['status'] ?? 'missing');
        $currentSupportStatus = (string) ($routeSeriesSpotlight['current_window_support_status'] ?? 'missing');
        $longTermSupportStatus = (string) ($routeSeriesSpotlight['long_term_window_support_status'] ?? 'missing');

        if (in_array($spotlightStatus, ['softening', 'sparse'], true)) {
            return 'guarded';
        }

        if ($recommendedActionMode === 'focus_cluster') {
            return 'blocked';
        }

        if ($currentSupportStatus === 'proven' && $longTermSupportStatus === 'proven') {
            return 'safe';
        }

        if (in_array($currentSupportStatus, ['proven', 'emerging'], true) || in_array($longTermSupportStatus, ['proven', 'emerging'], true)) {
            return 'watching';
        }

        return 'guarded';
    }

    private function routeOutcomeGuidanceLabel(string $status): string
    {
        return match ($status) {
            'safe' => 'guvenli',
            'watching' => 'izleniyor',
            'blocked' => 'kilitli',
            default => 'temkinli',
        };
    }

    /**
     * @param array<string, mixed> $routeSeriesSpotlight
     */
    private function routeOutcomeGuidanceReason(array $routeSeriesSpotlight, string $recommendedActionMode, string $guidanceStatus): string
    {
        $routeLabel = (string) ($routeSeriesSpotlight['route_label'] ?? $this->routeLabel((string) ($routeSeriesSpotlight['route_key'] ?? 'approvals')));
        $currentWindowDays = (int) ($routeSeriesSpotlight['current_window_days'] ?? ($routeSeriesSpotlight['window_days'] ?? 0));
        $longTermWindowDays = (int) ($routeSeriesSpotlight['long_term_window_days'] ?? 90);
        $currentSupportStatus = (string) ($routeSeriesSpotlight['current_window_support_status'] ?? 'missing');
        $longTermSupportStatus = (string) ($routeSeriesSpotlight['long_term_window_support_status'] ?? 'missing');
        $currentRate = $routeSeriesSpotlight['current_window_success_rate'] ?? null;
        $longTermRate = $routeSeriesSpotlight['long_term_window_success_rate'] ?? null;

        $baseReason = match ($recommendedActionMode) {
            'jump_to_item' => sprintf('%s rotasi current ve long-term pencerelerde yeterince guclu destek aliyor; tek tek detay odagi guvenli gorunuyor.', $routeLabel),
            'bulk_retry_publish' => sprintf("%s rotasi current ve long-term outcome tarafinda toplu publish''i tasiyabilecek kadar guclu gorunuyor.", $routeLabel),
            default => sprintf('%s rotasi toplu aksiyon yerine cluster focus gerektiriyor.', $routeLabel),
        };

        $parts = [$baseReason];

        if ($currentRate !== null) {
            $parts[] = sprintf('%s gunluk pencere basarisi %s.', $currentWindowDays ?: 7, $this->formatPercentage((float) $currentRate));
        }

        if ($longTermRate !== null) {
            $parts[] = sprintf('%s gunluk stabilite %s.', $longTermWindowDays, $this->formatPercentage((float) $longTermRate));
        }

        $parts[] = sprintf('Current destek: %s, long-term destek: %s, guidance: %s.', $currentSupportStatus, $longTermSupportStatus, $guidanceStatus);

        return implode(' ', $parts);
    }

    private function routeOutcomeActionLabel(string $actionMode): string
    {
        return match ($actionMode) {
            'jump_to_item' => 'Detaya Git',
            'bulk_retry_publish' => 'Toplu Publish Dene',
            default => 'Kumeyi Incele',
        };
    }

    /**
     * @param array<string, mixed> $routeSeriesSpotlight
     */
    private function routeOutcomeDecisionContextSource(array $routeSeriesSpotlight, string $recommendedActionMode): string
    {
        if (($routeSeriesSpotlight['long_term_window_support_status'] ?? null) === 'proven') {
            return 'long_term';
        }

        if ($recommendedActionMode === 'jump_to_item') {
            return 'current_window';
        }

        return 'mixed';
    }

    /**
     * @param array<string, mixed> $routeSeriesSpotlight
     */
    private function routeOutcomeDecisionContextWindowDays(array $routeSeriesSpotlight, string $recommendedActionMode): int
    {
        $source = $this->routeOutcomeDecisionContextSource($routeSeriesSpotlight, $recommendedActionMode);

        return $source === 'long_term'
            ? (int) ($routeSeriesSpotlight['long_term_window_days'] ?? 90)
            : (int) ($routeSeriesSpotlight['current_window_days'] ?? ($routeSeriesSpotlight['window_days'] ?? 7));
    }

    /**
     * @param array<string, mixed> $routeSeriesSpotlight
     */
    private function routeOutcomeDecisionContextSuccessRate(array $routeSeriesSpotlight, string $recommendedActionMode): float|null
    {
        $source = $this->routeOutcomeDecisionContextSource($routeSeriesSpotlight, $recommendedActionMode);

        return $source === 'long_term'
            ? ($routeSeriesSpotlight['long_term_window_success_rate'] ?? null)
            : ($routeSeriesSpotlight['current_window_success_rate'] ?? null);
    }

    private function formatPercentage(float $value): string
    {
        return number_format($value, 1, '.', '');
    }

    private function formatRate(float $value): string
    {
        return number_format($value, 1, '.', '');
    }

    /**
     * @param Collection<int, array{key: string, label: string, description: string, recommended_action_code: string}> $clusterDefinitions
     * @param Collection<int, array<string, mixed>> $presentedApprovals
     * @param Collection<int, AuditLog> $auditLogs
     * @param Collection<int, AuditLog> $featuredInteractionLogs
     * @param array<string, array<int, array<string, mixed>>> $clusterRouteWindowSeries
     * @return Collection<int, array<string, mixed>>
     */
    private function buildClusterItems(
        Collection $clusterDefinitions,
        Collection $presentedApprovals,
        Collection $auditLogs,
        Collection $featuredInteractionLogs,
        array $clusterRouteWindowSeries = [],
    ): Collection {
        return $clusterDefinitions
            ->map(function (array $cluster) use ($presentedApprovals, $auditLogs, $featuredInteractionLogs, $clusterRouteWindowSeries): array {
                $currentItems = $presentedApprovals
                    ->filter(fn (array $approval): bool => $this->matchesCluster($approval, $cluster['recommended_action_code']));

                $clusterLogs = $auditLogs
                    ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'remediation_context.cluster_key') === $cluster['key']);

                $featuredMetrics = $this->featuredMetricsForCluster($featuredInteractionLogs, $cluster['key']);
                $clusterOutcomeLogs = $featuredInteractionLogs
                    ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'acted_cluster_key') === $cluster['key']);
                $sourceBreakdown = $this->sourceBreakdown($clusterOutcomeLogs);
                $routeTrends = $this->routeTrends($sourceBreakdown);
                $topInteractionSource = $sourceBreakdown->first();
                $topOutcomeSource = $this->topOutcomeSource($sourceBreakdown);
                $topRoute = $routeTrends->first();
                $outcomeChainSummary = $this->outcomeChainSummary($clusterOutcomeLogs);
                $draftDetailOutcomeSummary = $this->draftDetailOutcomeSummary($clusterOutcomeLogs);
                $sampleItemRoute = $currentItems
                    ->pluck('approvable_route')
                    ->filter(fn (mixed $route): bool => is_string($route) && $route !== '')
                    ->first();

                $manualChecks = $clusterLogs->where('action', 'approval_manual_check_completed');
                $publishAttempts = $clusterLogs->where('action', 'publish_attempted');
                $successfulPublishes = $publishAttempts->filter(
                    fn (AuditLog $log): bool => (bool) data_get($log->metadata, 'success', false),
                );
                $failedPublishes = $publishAttempts->reject(
                    fn (AuditLog $log): bool => (bool) data_get($log->metadata, 'success', false),
                );

                $route = sprintf(
                    '/approvals?status=publish_failed&recommended_action_code=%s',
                    $cluster['recommended_action_code'],
                );

                return [
                    'cluster_key' => $cluster['key'],
                    'label' => $cluster['label'],
                    'description' => $cluster['description'],
                    'recommended_action_code' => $cluster['recommended_action_code'],
                    'current_items' => $currentItems->count(),
                    'manual_check_completions' => $manualChecks->count(),
                    'publish_attempts' => $publishAttempts->count(),
                    'successful_publishes' => $successfulPublishes->count(),
                    'failed_publishes' => $failedPublishes->count(),
                    'publish_success_rate' => $publishAttempts->count() > 0
                        ? round(($successfulPublishes->count() / $publishAttempts->count()) * 100, 1)
                        : null,
                    'last_activity_at' => optional($clusterLogs->first()?->occurred_at)?->toIso8601String(),
                    'effectiveness_score' => $this->effectivenessScore(
                        $publishAttempts->count(),
                        $successfulPublishes->count(),
                        $currentItems->count(),
                        $featuredMetrics['featured_follow_rate'],
                    ),
                    'effectiveness_status' => $this->effectivenessStatus(
                        $publishAttempts->count(),
                        $successfulPublishes->count(),
                        $currentItems->count(),
                    ),
                    'health_status' => $this->healthStatus($cluster['recommended_action_code'], $currentItems->count(), $publishAttempts->count(), $successfulPublishes->count()),
                    'health_summary' => $this->healthSummary($cluster['recommended_action_code'], $currentItems->count(), $publishAttempts->count(), $successfulPublishes->count()),
                    'route' => $route,
                    'top_interaction_source_key' => $topInteractionSource['source_key'] ?? null,
                    'top_interaction_source_label' => $topInteractionSource['label'] ?? null,
                    'top_outcome_source_key' => $topOutcomeSource['source_key'] ?? null,
                    'top_outcome_source_label' => $topOutcomeSource['label'] ?? null,
                    'top_outcome_publish_success_rate' => $topOutcomeSource['publish_success_rate'] ?? null,
                    'top_route_key' => $topRoute['route_key'] ?? null,
                    'top_route_label' => $topRoute['label'] ?? null,
                    'top_route_source_key' => $topRoute['top_source_key'] ?? null,
                    'top_route_source_label' => $topRoute['top_source_label'] ?? null,
                    'top_route_publish_success_rate' => $topRoute['publish_success_rate'] ?? null,
                    'sample_item_route' => $sampleItemRoute,
                    'source_breakdown' => $sourceBreakdown->all(),
                    'route_trends' => $routeTrends->all(),
                    'route_window_series' => $clusterRouteWindowSeries[$cluster['key']] ?? [],
                    'outcome_chain_summary' => $outcomeChainSummary,
                    'draft_detail_outcome_summary' => $draftDetailOutcomeSummary,
                    ...$featuredMetrics,
                ];
            })
            ->sortByDesc(fn (array $item): int => $item['current_items'] * 1000 + $item['publish_attempts'] * 10 + $item['manual_check_completions'])
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $sourceBreakdown
     * @return Collection<int, array<string, mixed>>
     */
    private function routeTrends(Collection $sourceBreakdown): Collection
    {
        return $sourceBreakdown
            ->groupBy(fn (array $item): string => $this->routeKeyForSource((string) ($item['source_key'] ?? 'other')))
            ->map(function (Collection $items, string $routeKey): array {
                $trackedInteractions = $items->sum(fn (array $item): int => (int) ($item['tracked_interactions'] ?? 0));
                $followedInteractions = $items->sum(fn (array $item): int => (int) ($item['followed_featured_interactions'] ?? 0));
                $publishAttempts = $items->sum(fn (array $item): int => (int) ($item['publish_attempts'] ?? 0));
                $successfulPublishes = $items->sum(fn (array $item): int => (int) ($item['successful_publishes'] ?? 0));
                $failedPublishes = $items->sum(fn (array $item): int => (int) ($item['failed_publishes'] ?? 0));
                $topSource = $this->topOutcomeSource($items);

                return [
                    'route_key' => $routeKey,
                    'label' => $this->routeLabel($routeKey),
                    'tracked_interactions' => $trackedInteractions,
                    'followed_featured_interactions' => $followedInteractions,
                    'publish_attempts' => $publishAttempts,
                    'successful_publishes' => $successfulPublishes,
                    'failed_publishes' => $failedPublishes,
                    'publish_success_rate' => $publishAttempts > 0
                        ? round(($successfulPublishes / $publishAttempts) * 100, 1)
                        : null,
                    'top_source_key' => $topSource['source_key'] ?? null,
                    'top_source_label' => $topSource['label'] ?? null,
                ];
            })
            ->sortByDesc(function (array $item): float {
                return ((float) ($item['publish_success_rate'] ?? 0) * 1000)
                    + ((float) ($item['publish_attempts'] ?? 0) * 10)
                    + ((float) ($item['successful_publishes'] ?? 0));
            })
            ->values();
    }

    /**
     * @param Collection<int, AuditLog> $featuredInteractionLogs
     * @return Collection<int, array<string, mixed>>
     */
    private function routeWindowSeries(Collection $featuredInteractionLogs, ?array $selectedRoute = null): Collection
    {
        return collect([7, 30, 90])
            ->map(fn (int $windowDays): array => $this->routeWindowSnapshot($featuredInteractionLogs, $windowDays, $selectedRoute))
            ->values();
    }

    /**
     * @param Collection<int, AuditLog> $featuredInteractionLogs
     * @return Collection<int, array<string, mixed>>
     */
    private function routeOutcomeWindowSeries(Collection $featuredInteractionLogs, ?array $selectedRoute = null): Collection
    {
        return $this->routeWindowSeries($featuredInteractionLogs, $selectedRoute)
            ->map(fn (array $routeWindow): array => $this->routeOutcomeWindowSnapshot($routeWindow))
            ->values();
    }

    /**
     * @param array<string, mixed> $routeWindow
     * @return array<string, mixed>
     */
    private function routeOutcomeWindowSnapshot(array $routeWindow): array
    {
        $currentSupportStatus = $this->routeOutcomeWindowSupportStatus(
            (string) ($routeWindow['current_route_key'] ?? $routeWindow['top_route_key'] ?? 'other'),
            isset($routeWindow['current_route_success_rate']) ? (float) $routeWindow['current_route_success_rate'] : null,
            isset($routeWindow['current_route_advantage']) ? (float) $routeWindow['current_route_advantage'] : null,
        );
        $topSupportStatus = $this->routeOutcomeWindowSupportStatus(
            (string) ($routeWindow['top_route_key'] ?? $routeWindow['current_route_key'] ?? 'other'),
            isset($routeWindow['top_route_success_rate']) ? (float) $routeWindow['top_route_success_rate'] : null,
            isset($routeWindow['top_route_advantage']) ? (float) $routeWindow['top_route_advantage'] : null,
        );
        $guidanceStatus = $this->routeOutcomeWindowGuidanceStatus($currentSupportStatus, $topSupportStatus);
        $recommendedActionMode = $this->routeOutcomeWindowActionMode($routeWindow, $guidanceStatus, $currentSupportStatus, $topSupportStatus);

        return [
            'window_days' => $routeWindow['window_days'] ?? null,
            'label' => $routeWindow['label'] ?? null,
            'route_key' => $routeWindow['current_route_key'] ?? $routeWindow['top_route_key'] ?? null,
            'route_label' => $routeWindow['current_route_label'] ?? $routeWindow['top_route_label'] ?? null,
            'preferred_flow' => $routeWindow['preferred_flow'] ?? null,
            'guidance_status' => $guidanceStatus,
            'guidance_label' => $this->routeOutcomeGuidanceLabel($guidanceStatus),
            'guidance_reason' => $this->routeOutcomeWindowGuidanceReason($routeWindow, $currentSupportStatus, $topSupportStatus, $guidanceStatus, $recommendedActionMode),
            'recommended_action_mode' => $recommendedActionMode,
            'recommended_action_label' => $this->routeOutcomeActionLabel($recommendedActionMode),
            'success_rate' => $routeWindow['current_route_success_rate'] ?? $routeWindow['top_route_success_rate'] ?? null,
            'current_support_status' => $currentSupportStatus,
            'long_term_support_status' => $topSupportStatus,
            'summary_label' => $routeWindow['summary_label'] ?? null,
            'route_trends' => $routeWindow['route_trends'] ?? [],
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $routeOutcomeWindowSeries
     * @return array<string, mixed>|null
     */
    private function topRouteOutcomeWindow(Collection $routeOutcomeWindowSeries): ?array
    {
        return $routeOutcomeWindowSeries
            ->sortByDesc(fn (array $item): float => $this->routeOutcomeWindowScore($item))
            ->first();
    }

    /**
     * @param Collection<int, array{key: string, label: string, description: string, recommended_action_code: string}> $clusterDefinitions
     * @param Collection<int, AuditLog> $featuredInteractionLogs
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function clusterRouteWindowSeries(Collection $clusterDefinitions, Collection $featuredInteractionLogs): array
    {
        return $clusterDefinitions
            ->mapWithKeys(function (array $cluster) use ($featuredInteractionLogs): array {
                $clusterLogs = $featuredInteractionLogs
                    ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'acted_cluster_key') === $cluster['key'])
                    ->values();

                return [
                    $cluster['key'] => $this->routeWindowSeries($clusterLogs)->all(),
                ];
            })
            ->all();
    }

    /**
     * @param Collection<int, AuditLog> $featuredInteractionLogs
     * @return array<string, mixed>
     */
    private function routeWindowSnapshot(Collection $featuredInteractionLogs, int $windowDays, ?array $selectedRoute = null): array
    {
        $windowStart = now()->subDays($windowDays);
        $windowLogs = $featuredInteractionLogs
            ->filter(fn (AuditLog $log): bool => $log->occurred_at >= $windowStart)
            ->values();
        $sourceBreakdown = $this->sourceBreakdown($windowLogs);
        $routeTrends = $this->routeTrends($sourceBreakdown);
        $topRoute = $routeTrends->first();
        $secondaryRoute = $routeTrends->slice(1)->first();
        $selectedRouteKey = $selectedRoute['route_key'] ?? $topRoute['route_key'] ?? null;
        $currentRoute = $selectedRouteKey !== null
            ? $routeTrends->first(fn (array $item): bool => ($item['route_key'] ?? null) === $selectedRouteKey)
            : null;
        if (! is_array($currentRoute) && $selectedRoute === null && is_array($topRoute)) {
            $currentRoute = $topRoute;
        }
        $currentAlternativeRoute = is_array($currentRoute)
            ? $routeTrends->first(fn (array $item): bool => ($item['route_key'] ?? null) !== ($currentRoute['route_key'] ?? null))
            : null;
        $topSupportStatus = $this->routeWindowSupportStatus($topRoute, $secondaryRoute);
        $currentSupportStatus = $this->routeWindowSupportStatus($currentRoute, $currentAlternativeRoute);
        $topRouteKey = $topRoute['route_key'] ?? null;
        $topRouteLabel = $topRoute['label'] ?? null;
        $currentRouteKey = $currentRoute['route_key'] ?? $selectedRouteKey;
        $currentRouteLabel = $currentRoute['label'] ?? ($currentRouteKey !== null ? $this->routeLabel((string) $currentRouteKey) : null);
        $topRouteSuccessRate = isset($topRoute['publish_success_rate']) ? (float) $topRoute['publish_success_rate'] : null;
        $topRouteAttempts = isset($topRoute['publish_attempts']) ? (int) $topRoute['publish_attempts'] : 0;
        $currentRouteSuccessRate = isset($currentRoute['publish_success_rate']) ? (float) $currentRoute['publish_success_rate'] : null;
        $currentRouteAttempts = is_array($currentRoute) ? (int) ($currentRoute['publish_attempts'] ?? 0) : null;
        $currentRouteAdvantage = is_array($currentRoute)
            ? $this->routeAdvantage($currentRoute, $currentAlternativeRoute)
            : null;
        $topRouteAdvantage = $this->routeAdvantage($topRoute, $secondaryRoute);
        $preferredFlow = $this->preferredFlowForRouteKey($topRouteKey);

        return [
            'window_days' => $windowDays,
            'label' => $this->routeWindowLabel($windowDays),
            'tracked_interactions' => $windowLogs->count(),
            'preferred_flow' => $preferredFlow,
            'confidence' => $this->routeWindowConfidence($topSupportStatus),
            'current_route_key' => $currentRouteKey,
            'current_route_label' => $currentRouteLabel,
            'current_route_success_rate' => $currentRouteSuccessRate,
            'current_route_attempts' => $currentRouteAttempts,
            'current_route_advantage' => $currentRouteAdvantage,
            'top_route_key' => $topRouteKey,
            'top_route_label' => $topRouteLabel,
            'top_route_source_key' => $topRoute['top_source_key'] ?? null,
            'top_route_source_label' => $topRoute['top_source_label'] ?? null,
            'top_route_publish_attempts' => $topRouteAttempts,
            'top_route_tracked_interactions' => $topRoute['tracked_interactions'] ?? 0,
            'top_route_publish_success_rate' => $topRouteSuccessRate,
            'top_route_success_rate' => $topRouteSuccessRate,
            'top_route_attempts' => $topRouteAttempts,
            'top_route_advantage' => $topRouteAdvantage,
            'summary_label' => $this->routeWindowSummaryLabel($windowDays, $currentRouteLabel, $topRouteLabel, $currentRouteKey, $topRouteKey),
            'reason' => $this->routeWindowReason(
                $windowDays,
                $currentRouteLabel,
                $topRouteLabel,
                $topRouteSuccessRate,
                $topRouteAdvantage,
                $topSupportStatus,
                $currentSupportStatus,
                $topRoute['top_source_label'] ?? null,
            ),
            'route_trends' => $routeTrends->all(),
        ];
    }

    private function routeWindowLabel(int $windowDays): string
    {
        return match ($windowDays) {
            7 => 'Kisa Vade',
            30 => 'Operasyon Penceresi',
            90 => 'Uzun Donem',
            default => sprintf('%s Gun', $windowDays),
        };
    }

    private function preferredFlowForRouteKey(string|null $routeKey): string
    {
        return match ($routeKey) {
            'draft_detail' => 'draft_detail',
            'approvals' => 'approvals_native',
            default => 'balanced',
        };
    }

    private function routeWindowConfidence(string $supportStatus): string
    {
        return match ($supportStatus) {
            'proven' => 'high',
            'emerging' => 'medium',
            default => 'low',
        };
    }

    private function routeWindowSupportStatus(?array $route, ?array $alternativeRoute, bool $safeBulkRetry = false): string
    {
        if (! is_array($route)) {
            return 'missing';
        }

        $routeKey = (string) ($route['route_key'] ?? 'other');
        $publishAttempts = (int) ($route['publish_attempts'] ?? 0);
        $publishSuccessRate = isset($route['publish_success_rate']) ? (float) $route['publish_success_rate'] : null;
        $advantage = $this->routeAdvantage($route, $alternativeRoute) ?? 0.0;

        if ($publishAttempts < 1 || $publishSuccessRate === null) {
            return 'missing';
        }

        if ($routeKey === 'draft_detail') {
            if ($publishAttempts >= 2 && ($publishSuccessRate >= 70.0 || $advantage >= 10.0)) {
                return 'proven';
            }

            if ($publishSuccessRate >= 50.0 || $advantage >= 5.0) {
                return 'emerging';
            }

            return 'guarded';
        }

        if ($publishAttempts >= 2 && $publishSuccessRate >= 70.0 && ($advantage >= 5.0 || ! $safeBulkRetry)) {
            return 'proven';
        }

        if ($publishSuccessRate >= ($safeBulkRetry ? 60.0 : 55.0) || $advantage >= 3.0) {
            return 'emerging';
        }

        return 'guarded';
    }

    private function routeSupportLabel(string $status): string
    {
        return match ($status) {
            'proven' => 'kanitli',
            'emerging' => 'yukselen',
            'guarded' => 'temkinli',
            default => 'veri bekleyen',
        };
    }

    private function routeWindowSummaryLabel(
        int $windowDays,
        string|null $currentRouteLabel,
        string|null $topRouteLabel,
        string|null $currentRouteKey,
        string|null $topRouteKey,
    ): string {
        if ($topRouteLabel === null) {
            return sprintf('%s gunluk pencerede route verisi bekleniyor', $windowDays);
        }

        if ($currentRouteKey !== null && $currentRouteKey === $topRouteKey) {
            return sprintf('%s bu pencerede one cikiyor', $topRouteLabel);
        }

        if ($currentRouteLabel !== null) {
            return sprintf('%s, secili %s rotasini geride birakiyor', $topRouteLabel, $currentRouteLabel);
        }

        return sprintf('%s bu pencerede kazanan rota', $topRouteLabel);
    }

    private function routeWindowReason(
        int $windowDays,
        string|null $currentRouteLabel,
        string|null $topRouteLabel,
        float|null $topRouteSuccessRate,
        float|null $topRouteAdvantage,
        string $topSupportStatus,
        string $currentSupportStatus,
        string|null $topRouteSourceLabel,
    ): string {
        if ($topRouteLabel === null) {
            return sprintf('%s gunluk pencere icin route telemetry henuz yeterli degil.', $windowDays);
        }

        $parts = [];

        if ($currentRouteLabel !== null && $currentRouteLabel !== $topRouteLabel) {
            $parts[] = sprintf('%s gunluk pencerede %s, secili %s rotasinin onune geciyor.', $windowDays, $topRouteLabel, $currentRouteLabel);
        } else {
            $parts[] = sprintf('%s gunluk pencerede %s %s destek sinyali veriyor.', $windowDays, $topRouteLabel, $this->routeSupportLabel($topSupportStatus));
        }

        if ($topRouteSuccessRate !== null) {
            $parts[] = sprintf('Publish basarisi %s.', $this->formatPercentage($topRouteSuccessRate));
        }

        if ($topRouteAdvantage !== null) {
            $parts[] = sprintf('Alternatif route farki %+0.1f puan.', $topRouteAdvantage);
        }

        if ($currentRouteLabel !== null && $currentRouteLabel !== $topRouteLabel) {
            $parts[] = sprintf('Secili route su an %s gorunuyor.', $this->routeSupportLabel($currentSupportStatus));
        }

        if ($topRouteSourceLabel !== null) {
            $parts[] = sprintf('Top kaynak: %s.', $topRouteSourceLabel);
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{status: string, reason: string, series: array<int, array<string, mixed>>}
     */
    private function primaryActionTrendContext(string $routeKey, array $cluster, bool $safeBulkRetry): array
    {
        $series = collect($cluster['route_window_series'] ?? [])
            ->map(function (array $item) use ($routeKey, $safeBulkRetry): array {
                $routeTrends = collect($item['route_trends'] ?? []);
                $selectedRoute = $routeTrends
                    ->first(fn (array $route): bool => ($route['route_key'] ?? null) === $routeKey);
                $alternativeRoute = $routeTrends
                    ->first(fn (array $route): bool => ($route['route_key'] ?? null) !== $routeKey);
                $supportStatus = $this->routeWindowSupportStatus($selectedRoute, $alternativeRoute, $safeBulkRetry);

                return [
                    'window_days' => (int) ($item['window_days'] ?? 0),
                    'route_key' => $routeKey,
                    'route_label' => $this->routeLabel($routeKey),
                    'is_window_leader' => ($item['top_route_key'] ?? null) === $routeKey,
                    'tracked_interactions' => (int) ($selectedRoute['tracked_interactions'] ?? 0),
                    'publish_attempts' => (int) ($selectedRoute['publish_attempts'] ?? 0),
                    'successful_publishes' => (int) ($selectedRoute['successful_publishes'] ?? 0),
                    'failed_publishes' => (int) ($selectedRoute['failed_publishes'] ?? 0),
                    'publish_success_rate' => isset($selectedRoute['publish_success_rate'])
                        ? (float) $selectedRoute['publish_success_rate']
                        : null,
                    'leader_route_key' => $item['top_route_key'] ?? null,
                    'leader_route_label' => $item['top_route_label'] ?? null,
                    'support_status' => $supportStatus,
                ];
            })
            ->sortBy('window_days')
            ->values();

        $byWindow = $series->keyBy('window_days');
        $shortWindow = $byWindow->get(7);
        $midWindow = $byWindow->get(30);
        $longWindow = $byWindow->get(90);
        $shortAttempts = (int) ($shortWindow['publish_attempts'] ?? 0);
        $midAttempts = (int) ($midWindow['publish_attempts'] ?? 0);
        $longAttempts = (int) ($longWindow['publish_attempts'] ?? 0);
        $shortRate = isset($shortWindow['publish_success_rate']) ? (float) $shortWindow['publish_success_rate'] : null;
        $midRate = isset($midWindow['publish_success_rate']) ? (float) $midWindow['publish_success_rate'] : null;
        $longRate = isset($longWindow['publish_success_rate']) ? (float) $longWindow['publish_success_rate'] : null;
        $shortStable = in_array($shortWindow['support_status'] ?? null, ['proven', 'emerging'], true);
        $midStable = in_array($midWindow['support_status'] ?? null, ['proven', 'emerging'], true);
        $longStable = in_array($longWindow['support_status'] ?? null, ['proven', 'emerging'], true);
        $status = 'missing';
        $reason = 'Route pencerelerinde anlamli telemetry birikmedi.';

        if ($longStable && ($shortAttempts + $midAttempts) < 2) {
            $status = 'sparse';
            $reason = 'Uzun donem sinyal var ama son 7 ve 30 gunde route karari yeterli deneme biriktirmedi.';
        } elseif (
            $shortStable
            && $midStable
            && $shortRate !== null
            && max(array_filter([$midRate, $longRate], fn ($value): bool => $value !== null)) - $shortRate >= 15.0
        ) {
            $status = 'softening';
            $reason = 'Son 7 gun sinyali, 30 veya 90 gunluk route basarisinin belirgin altina inmeye basladi.';
        } elseif ($shortStable && $midStable) {
            $status = 'stable';
            $reason = 'Son 7 ve 30 gun ayni route kararini destekliyor.';
        } elseif ($shortStable || $midStable || $longStable) {
            $status = 'forming';
            $reason = 'Route karari bazi pencerelerde destek aliyor ama henuz tam stabil degil.';
        }

        return [
            'status' => $status,
            'reason' => $reason,
            'series' => $series->all(),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $sourceBreakdown
     * @return array<string, mixed>|null
     */
    private function topOutcomeSource(Collection $sourceBreakdown): ?array
    {
        return $sourceBreakdown
            ->sortByDesc(function (array $item): float {
                return ((float) ($item['publish_success_rate'] ?? 0) * 1000)
                    + ((float) ($item['publish_attempts'] ?? 0) * 10)
                    + ((float) ($item['successful_publishes'] ?? 0));
            })
            ->first();
    }

    /**
     * @param array<string, mixed> $cluster
     * @return array<string, mixed>
     */
    private function withPrimaryAction(array $cluster): array
    {
        return [
            ...$cluster,
            'primary_action' => $this->buildPrimaryAction($cluster),
        ];
    }

    /**
     * @param array<string, mixed> $cluster
     * @return array<string, mixed>
     */
    private function buildPrimaryAction(array $cluster): array
    {
        $routeTrends = collect($cluster['route_trends'] ?? []);
        $topRoute = $routeTrends->first();
        $alternativeRoute = $routeTrends
            ->first(fn (array $item): bool => $item['route_key'] !== ($topRoute['route_key'] ?? null));
        $routeKey = (string) ($topRoute['route_key'] ?? 'approvals');
        $routeLabel = (string) ($topRoute['label'] ?? $this->routeLabel($routeKey));
        $sourceKey = $topRoute['top_source_key'] ?? $cluster['top_outcome_source_key'] ?? null;
        $sourceLabel = $topRoute['top_source_label'] ?? $cluster['top_outcome_source_label'] ?? null;
        $publishAttempts = (int) ($topRoute['publish_attempts'] ?? 0);
        $publishSuccessRate = isset($topRoute['publish_success_rate']) ? (float) $topRoute['publish_success_rate'] : null;
        $advantage = $this->routeAdvantage($topRoute, $alternativeRoute);
        $confidenceStatus = $this->primaryActionConfidenceStatus($topRoute, $alternativeRoute, $cluster);
        $confidenceLabel = $this->primaryActionConfidenceLabel($confidenceStatus);
        $trackedInteractions = (int) ($topRoute['tracked_interactions'] ?? 0);
        $successfulPublishes = (int) ($topRoute['successful_publishes'] ?? 0);
        $failedPublishes = (int) ($topRoute['failed_publishes'] ?? 0);
        $followedFeaturedInteractions = (int) ($topRoute['followed_featured_interactions'] ?? 0);
        $preferredFlow = $routeKey === 'draft_detail' ? 'draft_detail' : ($routeKey === 'approvals' ? 'approvals_native' : 'balanced');
        $alternativeRouteKey = $alternativeRoute['route_key'] ?? null;
        $alternativeRouteLabel = $alternativeRoute['label'] ?? ($alternativeRouteKey !== null ? $this->routeLabel((string) $alternativeRouteKey) : null);
        $alternativePublishSuccessRate = isset($alternativeRoute['publish_success_rate'])
            ? (float) $alternativeRoute['publish_success_rate']
            : null;
        $trendContext = $this->primaryActionTrendContext($routeKey, $cluster, (bool) ($cluster['safe_bulk_retry'] ?? false));

        if (
            $routeKey === 'draft_detail'
            && filled($cluster['sample_item_route'] ?? null)
            && $confidenceStatus !== 'guarded'
            && ($publishSuccessRate !== null || (int) ($topRoute['successful_publishes'] ?? 0) > 0)
        ) {
            return [
                'mode' => 'jump_to_item',
                'route' => $cluster['sample_item_route'],
                'route_key' => $routeKey,
                'route_label' => $routeLabel,
                'source_key' => $sourceKey,
                'source_label' => $sourceLabel,
                'publish_attempts' => $publishAttempts,
                'publish_success_rate' => $publishSuccessRate,
                'tracked_interactions' => $trackedInteractions,
                'successful_publishes' => $successfulPublishes,
                'failed_publishes' => $failedPublishes,
                'followed_featured_interactions' => $followedFeaturedInteractions,
                'preferred_flow' => $preferredFlow,
                'confidence_status' => $confidenceStatus,
                'confidence_label' => $confidenceLabel,
                'trend_status' => $trendContext['status'],
                'trend_reason' => $trendContext['reason'],
                'route_series' => $trendContext['series'],
                'alternative_route_key' => $alternativeRouteKey,
                'alternative_route_label' => $alternativeRouteLabel,
                'alternative_publish_success_rate' => $alternativePublishSuccessRate,
                'advantage_vs_alternative_route' => $advantage,
                'reason' => $publishSuccessRate !== null
                    ? sprintf(
                        '%s kaynagi draft detail uzerinde %s publish basarisi urettigi ve rota guveni %s oldugu icin operatoru dogrudan ilgili taslak detayina indir.',
                        $sourceLabel ?? 'Bu route',
                        $this->formatPercentage($publishSuccessRate),
                        mb_strtolower($confidenceLabel),
                    )
                    : sprintf(
                        'Draft detail kaynagi bu cluster icin daha guclu sonuc urettigi icin operatoru dogrudan ilgili taslak detayina indir. Rota guveni: %s.',
                        mb_strtolower($confidenceLabel),
                    ),
            ];
        }

        $mode = (bool) ($cluster['safe_bulk_retry'] ?? false) ? 'bulk_retry_publish' : 'focus_cluster';

        return [
            'mode' => $mode,
            'route' => $cluster['route'] ?? null,
            'route_key' => $routeKey,
            'route_label' => $routeLabel,
            'source_key' => $sourceKey,
            'source_label' => $sourceLabel,
            'publish_attempts' => $publishAttempts,
            'publish_success_rate' => $publishSuccessRate,
            'tracked_interactions' => $trackedInteractions,
            'successful_publishes' => $successfulPublishes,
            'failed_publishes' => $failedPublishes,
            'followed_featured_interactions' => $followedFeaturedInteractions,
            'preferred_flow' => $preferredFlow,
            'confidence_status' => $confidenceStatus,
            'confidence_label' => $confidenceLabel,
            'trend_status' => $trendContext['status'],
            'trend_reason' => $trendContext['reason'],
            'route_series' => $trendContext['series'],
            'alternative_route_key' => $alternativeRouteKey,
            'alternative_route_label' => $alternativeRouteLabel,
            'alternative_publish_success_rate' => $alternativePublishSuccessRate,
            'advantage_vs_alternative_route' => $advantage,
            'reason' => $mode === 'bulk_retry_publish'
                ? ($cluster['retry_guidance_reason'] ?? sprintf(
                    'Bu cluster approvals merkezi uzerinde guvenli toplu retry sinyali veriyor. Rota guveni: %s.',
                    mb_strtolower($confidenceLabel),
                ))
                : ($cluster['retry_guidance_reason'] ?? sprintf(
                    'Bu cluster once approvals merkezinde odakli inceleme gerektiriyor. Rota guveni: %s.',
                    mb_strtolower($confidenceLabel),
                )),
        ];
    }

    /**
     * @param array<string, mixed>|null $topRoute
     * @param array<string, mixed>|null $alternativeRoute
     * @param array<string, mixed> $cluster
     */
    private function primaryActionConfidenceStatus(?array $topRoute, ?array $alternativeRoute, array $cluster): string
    {
        if (! is_array($topRoute)) {
            return 'guarded';
        }

        $routeKey = (string) ($topRoute['route_key'] ?? 'approvals');
        $trackedInteractions = (int) ($topRoute['tracked_interactions'] ?? 0);
        $publishAttempts = (int) ($topRoute['publish_attempts'] ?? 0);
        $successfulPublishes = (int) ($topRoute['successful_publishes'] ?? 0);
        $publishSuccessRate = isset($topRoute['publish_success_rate']) ? (float) $topRoute['publish_success_rate'] : null;
        $hasAlternativeRoute = is_array($alternativeRoute);
        $advantage = $this->routeAdvantage($topRoute, $alternativeRoute) ?? 0.0;
        $safeBulkRetry = (bool) ($cluster['safe_bulk_retry'] ?? false);
        $routeWindowSeries = collect($cluster['route_window_series'] ?? []);
        $matchingWindows = $routeWindowSeries
            ->filter(fn (array $item): bool => ($item['top_route_key'] ?? null) === $routeKey)
            ->values();
        $provenWindowCount = $matchingWindows
            ->filter(function (array $item) use ($routeKey, $safeBulkRetry): bool {
                $windowPublishAttempts = (int) ($item['top_route_publish_attempts'] ?? 0);
                $windowPublishSuccessRate = isset($item['top_route_publish_success_rate'])
                    ? (float) $item['top_route_publish_success_rate']
                    : null;
                $windowAdvantage = isset($item['top_route_advantage']) ? (float) $item['top_route_advantage'] : 0.0;

                if ($windowPublishAttempts < 2 || $windowPublishSuccessRate === null) {
                    return false;
                }

                if ($routeKey === 'draft_detail') {
                    return $windowPublishSuccessRate >= 70.0 || $windowAdvantage >= 10.0;
                }

                return $safeBulkRetry
                    && $windowPublishSuccessRate >= 70.0
                    && $windowAdvantage >= 5.0;
            })
            ->count();
        $emergingWindowCount = $matchingWindows
            ->filter(function (array $item) use ($routeKey, $safeBulkRetry): bool {
                $windowPublishAttempts = (int) ($item['top_route_publish_attempts'] ?? 0);
                $windowPublishSuccessRate = isset($item['top_route_publish_success_rate'])
                    ? (float) $item['top_route_publish_success_rate']
                    : null;
                $windowAdvantage = isset($item['top_route_advantage']) ? (float) $item['top_route_advantage'] : 0.0;

                if ($windowPublishAttempts < 1 || $windowPublishSuccessRate === null) {
                    return false;
                }

                if ($routeKey === 'draft_detail') {
                    return $windowPublishSuccessRate >= 50.0 || $windowAdvantage >= 5.0;
                }

                return $safeBulkRetry
                    && ($windowPublishSuccessRate >= 60.0 || $windowAdvantage >= 3.0);
            })
            ->count();

        $baseStatus = 'guarded';

        if ($routeKey === 'draft_detail') {
            if (
                ($publishAttempts >= 2 && $successfulPublishes >= 2 && (! $hasAlternativeRoute || $advantage >= 10.0))
                || $provenWindowCount >= 2
            ) {
                $baseStatus = 'proven';
            } elseif (
                ($trackedInteractions >= 1 && $successfulPublishes >= 1 && (! $hasAlternativeRoute || $advantage >= 5.0))
                || $emergingWindowCount >= 1
            ) {
                $baseStatus = 'emerging';
            }
        } elseif (
            $safeBulkRetry
            && (($publishAttempts >= 2 && ($publishSuccessRate ?? 0.0) >= 70.0) || $provenWindowCount >= 2)
        ) {
            $baseStatus = 'proven';
        } elseif (
            $safeBulkRetry
            && (($successfulPublishes >= 1 && $trackedInteractions >= 1) || $emergingWindowCount >= 1)
        ) {
            $baseStatus = 'emerging';
        }

        $trendContext = $this->primaryActionTrendContext($routeKey, $cluster, $safeBulkRetry);

        if ($trendContext['status'] === 'sparse') {
            return 'guarded';
        }

        if ($trendContext['status'] === 'softening' && $baseStatus === 'proven') {
            return 'emerging';
        }

        return $baseStatus;
    }

    private function primaryActionConfidenceLabel(string $status): string
    {
        return match ($status) {
            'proven' => 'Kanitli',
            'emerging' => 'Yukselen',
            default => 'Temkinli',
        };
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private function topLongTermStableCluster(Collection $items): ?array
    {
        return $items
            ->filter(function (array $item): bool {
                return (bool) ($item['safe_bulk_retry'] ?? false)
                    && (int) ($item['publish_attempts'] ?? 0) >= 2
                    && (float) ($item['publish_success_rate'] ?? 0) >= 70.0;
            })
            ->sortByDesc(function (array $item): float {
                return ((float) ($item['effectiveness_score'] ?? 0) * 1000)
                    + ((float) ($item['publish_success_rate'] ?? 0) * 10)
                    + ((float) ($item['featured_follow_rate'] ?? 0));
            })
            ->first();
    }

    /**
     * @param array<string, mixed>|null $item
     * @return array<string, mixed>
     */
    private function longTermClusterSignals(?array $item): array
    {
        if (! is_array($item)) {
            return [];
        }

        return [
            'long_term_current_items' => $item['current_items'] ?? null,
            'long_term_manual_check_completions' => $item['manual_check_completions'] ?? null,
            'long_term_publish_attempts' => $item['publish_attempts'] ?? null,
            'long_term_successful_publishes' => $item['successful_publishes'] ?? null,
            'long_term_failed_publishes' => $item['failed_publishes'] ?? null,
            'long_term_publish_success_rate' => $item['publish_success_rate'] ?? null,
            'long_term_last_activity_at' => $item['last_activity_at'] ?? null,
            'long_term_effectiveness_score' => $item['effectiveness_score'] ?? null,
            'long_term_effectiveness_status' => $item['effectiveness_status'] ?? null,
            'long_term_health_status' => $item['health_status'] ?? null,
            'long_term_health_summary' => $item['health_summary'] ?? null,
            'long_term_route' => $item['route'] ?? null,
            'long_term_top_interaction_source_key' => $item['top_interaction_source_key'] ?? null,
            'long_term_top_interaction_source_label' => $item['top_interaction_source_label'] ?? null,
            'long_term_source_breakdown' => $item['source_breakdown'] ?? [],
            'long_term_outcome_chain_summary' => $item['outcome_chain_summary'] ?? [],
            'long_term_draft_detail_outcome_summary' => $item['draft_detail_outcome_summary'] ?? [],
            'long_term_retry_guidance_status' => $item['retry_guidance_status'] ?? null,
            'long_term_retry_guidance_label' => $item['retry_guidance_label'] ?? null,
            'long_term_retry_guidance_reason' => $item['retry_guidance_reason'] ?? null,
            'long_term_safe_bulk_retry' => $item['safe_bulk_retry'] ?? null,
            'long_term_featured_interactions' => $item['featured_interactions'] ?? null,
            'long_term_featured_followed_interactions' => $item['featured_followed_interactions'] ?? null,
            'long_term_featured_override_interactions' => $item['featured_override_interactions'] ?? null,
            'long_term_featured_publish_attempts' => $item['featured_publish_attempts'] ?? null,
            'long_term_featured_successful_publishes' => $item['featured_successful_publishes'] ?? null,
            'long_term_featured_follow_rate' => $item['featured_follow_rate'] ?? null,
            'long_term_featured_publish_success_rate' => $item['featured_publish_success_rate'] ?? null,
        ];
    }

    private function effectivenessScore(
        int $publishAttempts,
        int $successfulPublishes,
        int $currentItems,
        ?float $featuredFollowRate,
    ): float {
        if ($publishAttempts === 0) {
            return $currentItems > 0 ? 10.0 : 0.0;
        }

        $publishSuccessRate = ($successfulPublishes / max($publishAttempts, 1)) * 100;
        $volumeScore = min($publishAttempts, 5) * 5;
        $followScore = $featuredFollowRate !== null ? min($featuredFollowRate, 100.0) * 0.15 : 0.0;
        $backlogPressure = $currentItems > 0 ? min($currentItems, 5) * 2 : 0;

        return round($publishSuccessRate * 0.7 + $volumeScore + $followScore + $backlogPressure, 1);
    }

    private function effectivenessStatus(int $publishAttempts, int $successfulPublishes, int $currentItems): string
    {
        if ($publishAttempts === 0) {
            return $currentItems > 0 ? 'insufficient_data' : 'idle';
        }

        $rate = $successfulPublishes / max($publishAttempts, 1);

        if ($rate >= 0.75) {
            return 'proven';
        }

        if ($rate >= 0.4) {
            return 'mixed';
        }

        return 'weak';
    }

    /**
     * @param Collection<int, AuditLog> $logs
     * @return Collection<int, array<string, mixed>>
     */
    private function sourceBreakdown(Collection $logs): Collection
    {
        $sourceDefinitions = collect($this->interactionSourceDefinitions())->keyBy('key');
        $groupedLogs = $logs->groupBy(
            fn (AuditLog $log): string => $this->normalizeInteractionSource(
                data_get($log->metadata, 'interaction_source')
            ),
        );

        return $sourceDefinitions
            ->map(function (array $definition, string $sourceKey) use ($groupedLogs): ?array {
                /** @var Collection<int, AuditLog> $sourceLogs */
                $sourceLogs = $groupedLogs->get($sourceKey, collect());
                $trackedInteractions = $sourceLogs->count();

                if ($trackedInteractions === 0) {
                    return null;
                }

                $followedInteractions = $sourceLogs
                    ->filter(fn (AuditLog $log): bool => (bool) data_get($log->metadata, 'followed_featured', false))
                    ->count();
                $overrideInteractions = $trackedInteractions - $followedInteractions;
                $publishAttempts = $sourceLogs->sum(
                    fn (AuditLog $log): int => (int) data_get($log->metadata, 'attempted_count', 0),
                );
                $successfulPublishes = $sourceLogs->sum(
                    fn (AuditLog $log): int => (int) data_get($log->metadata, 'success_count', 0),
                );
                $failedPublishes = $sourceLogs->sum(
                    fn (AuditLog $log): int => (int) data_get($log->metadata, 'failure_count', 0),
                );

                return [
                    'source_key' => $sourceKey,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'tracked_interactions' => $trackedInteractions,
                    'followed_featured_interactions' => $followedInteractions,
                    'override_interactions' => $overrideInteractions,
                    'manual_check_completions' => $sourceLogs
                        ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'interaction_type') === 'manual_check_completed')
                        ->count(),
                    'publish_retry_actions' => $sourceLogs
                        ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'interaction_type') === 'publish_retry')
                        ->count(),
                    'bulk_retry_actions' => $sourceLogs
                        ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'interaction_type') === 'bulk_retry_publish')
                        ->count(),
                    'publish_attempts' => $publishAttempts,
                    'successful_publishes' => $successfulPublishes,
                    'failed_publishes' => $failedPublishes,
                    'follow_rate' => $trackedInteractions > 0
                        ? round(($followedInteractions / $trackedInteractions) * 100, 1)
                        : null,
                    'publish_success_rate' => $publishAttempts > 0
                        ? round(($successfulPublishes / $publishAttempts) * 100, 1)
                        : null,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $item): int => $item['tracked_interactions'] * 1000 + $item['publish_attempts'] * 10 + $item['successful_publishes'])
            ->values();
    }

    /**
     * @param array<string, mixed>|null $topRoute
     * @param array<string, mixed>|null $alternativeRoute
     */
    private function routeAdvantage(?array $topRoute, ?array $alternativeRoute): ?float
    {
        if (! is_array($topRoute) || ! is_array($alternativeRoute)) {
            return null;
        }

        $topRate = $topRoute['publish_success_rate'] ?? null;
        $alternativeRate = $alternativeRoute['publish_success_rate'] ?? null;

        if ($topRate === null || $alternativeRate === null) {
            return null;
        }

        return round((float) $topRate - (float) $alternativeRate, 1);
    }

    private function routeKeyForSource(string $sourceKey): string
    {
        if (str_starts_with($sourceKey, 'draft_detail')) {
            return 'draft_detail';
        }

        if (str_starts_with($sourceKey, 'approvals')) {
            return 'approvals';
        }

        return 'other';
    }

    private function routeLabel(string $routeKey): string
    {
        return match ($routeKey) {
            'draft_detail' => 'Draft Detail',
            'approvals' => 'Approvals Native',
            default => 'Diger',
        };
    }

    /**
     * @param Collection<int, AuditLog> $logs
     * @return array<string, int|float|null>
     */
    private function outcomeChainSummary(Collection $logs): array
    {
        $trackedInteractions = $logs->count();
        $manualCheckCompletions = $logs
            ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'interaction_type') === 'manual_check_completed')
            ->count();
        $publishRetryActions = $logs
            ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'interaction_type') === 'publish_retry')
            ->count();
        $bulkRetryActions = $logs
            ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'interaction_type') === 'bulk_retry_publish')
            ->count();
        $focusActions = $logs
            ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'interaction_type') === 'focus_cluster')
            ->count();
        $jumpActions = $logs
            ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'interaction_type') === 'jump_to_item')
            ->count();
        $publishAttempts = $logs->sum(fn (AuditLog $log): int => (int) data_get($log->metadata, 'attempted_count', 0));
        $successfulPublishes = $logs->sum(fn (AuditLog $log): int => (int) data_get($log->metadata, 'success_count', 0));
        $failedPublishes = $logs->sum(fn (AuditLog $log): int => (int) data_get($log->metadata, 'failure_count', 0));

        return [
            'tracked_interactions' => $trackedInteractions,
            'manual_check_completions' => $manualCheckCompletions,
            'publish_retry_actions' => $publishRetryActions,
            'bulk_retry_actions' => $bulkRetryActions,
            'focus_actions' => $focusActions,
            'jump_actions' => $jumpActions,
            'total_retry_actions' => $publishRetryActions + $bulkRetryActions,
            'publish_attempts' => $publishAttempts,
            'successful_publishes' => $successfulPublishes,
            'failed_publishes' => $failedPublishes,
            'publish_success_rate' => $publishAttempts > 0
                ? round(($successfulPublishes / $publishAttempts) * 100, 1)
                : null,
        ];
    }

    /**
     * @param Collection<int, AuditLog> $logs
     * @return array<string, int|float|string|null>
     */
    private function draftDetailOutcomeSummary(Collection $logs): array
    {
        $draftDetailLogs = $logs
            ->filter(function (AuditLog $log): bool {
                $source = $this->normalizeInteractionSource(
                    data_get($log->metadata, 'interaction_source')
                );

                return str_starts_with($source, 'draft_detail');
            });

        $sourceBreakdown = $this->sourceBreakdown($draftDetailLogs);
        $topSource = $sourceBreakdown->first();

        return [
            ...$this->outcomeChainSummary($draftDetailLogs),
            'top_source_key' => $topSource['source_key'] ?? null,
            'top_source_label' => $topSource['label'] ?? null,
        ];
    }

    /**
     * @param Collection<int, AuditLog> $logs
     * @return array<string, int|float|string|null>
     */
    private function approvalsNativeOutcomeSummary(Collection $logs): array
    {
        $approvalsNativeLogs = $logs
            ->filter(function (AuditLog $log): bool {
                $source = $this->normalizeInteractionSource(
                    data_get($log->metadata, 'interaction_source')
                );

                return str_starts_with($source, 'approvals');
            });

        $sourceBreakdown = $this->sourceBreakdown($approvalsNativeLogs);
        $topSource = $sourceBreakdown->first();

        return [
            ...$this->outcomeChainSummary($approvalsNativeLogs),
            'top_source_key' => $topSource['source_key'] ?? null,
            'top_source_label' => $topSource['label'] ?? null,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    private function interactionSourceDefinitions(): array
    {
        return [
            [
                'key' => 'approvals_featured',
                'label' => 'Featured Kart',
                'description' => 'Approvals merkezindeki featured remediation karari uzerinden gelen etkilesimler.',
            ],
            [
                'key' => 'approvals_cluster',
                'label' => 'Cluster Karti',
                'description' => 'Approvals cluster kartlari uzerinden baslatilan etkilesimler.',
            ],
            [
                'key' => 'approvals_retry_ready',
                'label' => "Retry-Hazir Bandi",
                'description' => "Tekrar publish'e hazir bandi uzerinden gelen etkilesimler.",
            ],
            [
                'key' => 'approvals_item',
                'label' => 'Approval Satiri',
                'description' => 'Approval satiri seviyesinde operator aksiyonlari.',
            ],
            [
                'key' => 'approvals_bulk',
                'label' => 'Toplu Aksiyon',
                'description' => 'Approvals ekranindaki toplu retry publish akislari.',
            ],
            [
                'key' => 'approvals',
                'label' => 'Approvals Genel',
                'description' => 'Approvals ekranindan gelen genel odak etkilesimleri.',
            ],
            [
                'key' => 'draft_detail',
                'label' => 'Draft Detay',
                'description' => 'Draft detail ekranindaki remediation CTA etkilesimleri.',
            ],
            [
                'key' => 'draft_detail_from_approvals_featured',
                'label' => 'Draft Detay / Featured Kart',
                'description' => 'Featured approvals odagindan draft detail remediation CTA aksiyonlari.',
            ],
            [
                'key' => 'draft_detail_from_approvals_cluster',
                'label' => 'Draft Detay / Cluster Karti',
                'description' => 'Cluster approvals odagindan draft detail remediation CTA aksiyonlari.',
            ],
            [
                'key' => 'draft_detail_from_approvals_retry_ready',
                'label' => 'Draft Detay / Retry-Hazir',
                'description' => 'Retry-hazir approvals bandindan draft detail remediation CTA aksiyonlari.',
            ],
            [
                'key' => 'draft_detail_from_approvals_item',
                'label' => 'Draft Detay / Approval Satiri',
                'description' => 'Approval satiri odagindan draft detail remediation CTA aksiyonlari.',
            ],
            [
                'key' => 'other',
                'label' => 'Diger',
                'description' => 'Tanimlanmamis veya eski kaynaklardan gelen telemetry kayitlari.',
            ],
        ];
    }

    private function normalizeInteractionSource(?string $source): string
    {
        $allowedSources = collect($this->interactionSourceDefinitions())
            ->pluck('key')
            ->all();

        return in_array($source, $allowedSources, true) ? $source : 'other';
    }

    /**
     * @param Collection<int, AuditLog> $featuredInteractionLogs
     * @return array<string, int|float|null>
     */
    private function featuredSummary(Collection $featuredInteractionLogs): array
    {
        $trackedInteractions = $featuredInteractionLogs->count();
        $followedInteractions = $featuredInteractionLogs
            ->filter(fn (AuditLog $log): bool => (bool) data_get($log->metadata, 'followed_featured', false))
            ->count();
        $overrideInteractions = $trackedInteractions - $followedInteractions;
        $publishAttempts = $featuredInteractionLogs->sum(
            fn (AuditLog $log): int => (int) data_get($log->metadata, 'attempted_count', 0),
        );
        $successfulPublishes = $featuredInteractionLogs->sum(
            fn (AuditLog $log): int => (int) data_get($log->metadata, 'success_count', 0),
        );

        return [
            'tracked_featured_interactions' => $trackedInteractions,
            'followed_featured_interactions' => $followedInteractions,
            'override_featured_interactions' => $overrideInteractions,
            'featured_publish_attempts' => $publishAttempts,
            'successful_featured_publishes' => $successfulPublishes,
            'featured_follow_rate' => $trackedInteractions > 0
                ? round(($followedInteractions / $trackedInteractions) * 100, 1)
                : null,
            'featured_publish_success_rate' => $publishAttempts > 0
                ? round(($successfulPublishes / $publishAttempts) * 100, 1)
                : null,
        ];
    }

    /**
     * @param Collection<int, AuditLog> $featuredInteractionLogs
     * @return array<string, int|float|null>
     */
    private function featuredMetricsForCluster(Collection $featuredInteractionLogs, string $clusterKey): array
    {
        $clusterLogs = $featuredInteractionLogs
            ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'featured_cluster_key') === $clusterKey);

        $trackedInteractions = $clusterLogs->count();
        $followedInteractions = $clusterLogs
            ->filter(fn (AuditLog $log): bool => (bool) data_get($log->metadata, 'followed_featured', false))
            ->count();
        $overrideInteractions = $trackedInteractions - $followedInteractions;
        $publishAttempts = $clusterLogs->sum(
            fn (AuditLog $log): int => (int) data_get($log->metadata, 'attempted_count', 0),
        );
        $successfulPublishes = $clusterLogs->sum(
            fn (AuditLog $log): int => (int) data_get($log->metadata, 'success_count', 0),
        );

        return [
            'featured_interactions' => $trackedInteractions,
            'featured_followed_interactions' => $followedInteractions,
            'featured_override_interactions' => $overrideInteractions,
            'featured_publish_attempts' => $publishAttempts,
            'featured_successful_publishes' => $successfulPublishes,
            'featured_follow_rate' => $trackedInteractions > 0
                ? round(($followedInteractions / $trackedInteractions) * 100, 1)
                : null,
            'featured_publish_success_rate' => $publishAttempts > 0
                ? round(($successfulPublishes / $publishAttempts) * 100, 1)
                : null,
        ];
    }
}
