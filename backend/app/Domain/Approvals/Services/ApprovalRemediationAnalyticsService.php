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
     *     interaction_sources: array<int, array<string, mixed>>,
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

        $currentRawItems = $this->buildClusterItems(
            $clusterDefinitions,
            $presentedApprovals,
            $currentWindowAuditLogs,
            $currentFeaturedInteractionLogs,
        );
        $longTermRawItems = $this->buildClusterItems(
            $clusterDefinitions,
            $presentedApprovals,
            $longTermWindowAuditLogs,
            $longTermFeaturedInteractionLogs,
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
        $featuredSummary = $this->featuredSummary($currentFeaturedInteractionLogs);

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
                ...$featuredSummary,
                'window_days' => $windowDays,
                'long_term_window_days' => $longTermWindowDays,
            ],
            'featured_recommendation' => $featuredRecommendation,
            'interaction_sources' => $interactionSources->all(),
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
            $recommended = $this->applyRetryGuidance(
                $manualCheckRequired,
                $manualCheckRequired['publish_success_rate'] ?? null,
            );

            return [
                ...$recommended,
                'decision_status' => 'manual_attention',
                'decision_reason' => 'Cleanup basarisiz kalan publish hatalari once manuel kontrol gerektiriyor.',
                'action_mode' => 'focus_cluster',
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
            $recommended = $this->applyRetryGuidance(
                $topLongTermStableCluster,
                $currentReferenceSuccessRate,
            );
            $longTermSuccessRate = (float) ($recommended['publish_success_rate'] ?? 0);

            return [
                ...$recommended,
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
                'action_mode' => $this->retryGuidedActionMode($recommended),
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
            $recommended = $this->applyRetryGuidance(
                $topDraftDetailCluster,
                $topWorkingCluster['publish_success_rate'] ?? null,
            );

            return [
                ...$recommended,
                'decision_status' => 'draft_detail_preferred',
                'decision_reason' => sprintf(
                    'Son %d gunde draft detail uzerinden takip edilen remediation aksiyonlari approvals merkezindeki dogrudan akislarin uzerinde sonuc uretti. Bu nedenle draft detail odaginda daha iyi calisan cluster one cikarildi.',
                    $windowDays,
                ),
                'decision_context_source' => 'draft_detail',
                'decision_context_success_rate' => $draftDetailPublishSuccessRate,
                'decision_context_advantage' => round($draftDetailLead, 1),
                'action_mode' => $this->retryGuidedActionMode($recommended),
            ];
        }

        if (
            is_array($topEffectiveCluster)
            && ($topEffectiveCluster['current_items'] ?? 0) > 0
            && ($topEffectiveCluster['effectiveness_score'] ?? 0) >= 40
        ) {
            $recommended = $this->applyRetryGuidance(
                $topEffectiveCluster,
                $topWorkingCluster['publish_success_rate'] ?? null,
            );

            return [
                ...$recommended,
                'decision_status' => 'effectiveness_preferred',
                'decision_reason' => sprintf(
                    'Son %d gunun effectiveness skoruna gore publish toparlama ihtimali en guclu remediation cluster one cikarildi.',
                    $windowDays,
                ),
                'action_mode' => $this->retryGuidedActionMode($recommended),
            ];
        }

        if (is_array($topWorkingCluster) && ($topWorkingCluster['current_items'] ?? 0) > 0) {
            $recommended = $this->applyRetryGuidance(
                $topWorkingCluster,
                $topWorkingCluster['publish_success_rate'] ?? null,
            );

            return [
                ...$recommended,
                'decision_status' => 'analytics_preferred',
                'decision_reason' => sprintf(
                    'Son %d gunun publish sonucuna gore su an en iyi toparlayan remediation cluster one cikarildi.',
                    $windowDays,
                ),
                'action_mode' => $this->retryGuidedActionMode($recommended),
            ];
        }

        $fallback = $items->first(fn (array $item): bool => $item['current_items'] > 0);

        if (! is_array($fallback)) {
            return null;
        }

        $recommended = $this->applyRetryGuidance(
            $fallback,
            $topWorkingCluster['publish_success_rate'] ?? null,
        );

        return [
            ...$recommended,
            'decision_status' => 'rule_based',
            'decision_reason' => sprintf(
                'Son %d gun icinde yeterli publish sonucu olmadigi icin aktif remediation durumuna gore kurala dayali oncelik secildi.',
                $windowDays,
            ),
            'action_mode' => $this->retryGuidedActionMode($recommended),
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
    private function retryGuidedActionMode(array $cluster): string
    {
        return (bool) ($cluster['safe_bulk_retry'] ?? false) ? 'bulk_retry_publish' : 'focus_cluster';
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
     * @return Collection<int, array<string, mixed>>
     */
    private function buildClusterItems(
        Collection $clusterDefinitions,
        Collection $presentedApprovals,
        Collection $auditLogs,
        Collection $featuredInteractionLogs,
    ): Collection {
        return $clusterDefinitions
            ->map(function (array $cluster) use ($presentedApprovals, $auditLogs, $featuredInteractionLogs): array {
                $currentItems = $presentedApprovals
                    ->filter(fn (array $approval): bool => $this->matchesCluster($approval, $cluster['recommended_action_code']));

                $clusterLogs = $auditLogs
                    ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'remediation_context.cluster_key') === $cluster['key']);

                $featuredMetrics = $this->featuredMetricsForCluster($featuredInteractionLogs, $cluster['key']);
                $clusterOutcomeLogs = $featuredInteractionLogs
                    ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'acted_cluster_key') === $cluster['key']);
                $sourceBreakdown = $this->sourceBreakdown($clusterOutcomeLogs);
                $topInteractionSource = $sourceBreakdown->first();
                $outcomeChainSummary = $this->outcomeChainSummary($clusterOutcomeLogs);
                $draftDetailOutcomeSummary = $this->draftDetailOutcomeSummary($clusterOutcomeLogs);

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
                    'source_breakdown' => $sourceBreakdown->all(),
                    'outcome_chain_summary' => $outcomeChainSummary,
                    'draft_detail_outcome_summary' => $draftDetailOutcomeSummary,
                    ...$featuredMetrics,
                ];
            })
            ->sortByDesc(fn (array $item): int => $item['current_items'] * 1000 + $item['publish_attempts'] * 10 + $item['manual_check_completions'])
            ->values();
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
