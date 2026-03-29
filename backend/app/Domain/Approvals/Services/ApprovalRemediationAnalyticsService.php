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
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function build(string $workspaceId, int $windowDays = 30): array
    {
        $presentedApprovals = Approval::query()
            ->where('workspace_id', $workspaceId)
            ->with('approvable')
            ->where('status', 'publish_failed')
            ->get()
            ->map(fn (Approval $approval): array => $this->approvalPayloadPresenter->present($approval));

        $auditLogs = AuditLog::query()
            ->where('workspace_id', $workspaceId)
            ->where('target_type', 'approval')
            ->whereIn('action', ['approval_manual_check_completed', 'publish_attempted'])
            ->where('occurred_at', '>=', now()->subDays($windowDays))
            ->latest('occurred_at')
            ->get();

        $clusterDefinitions = collect($this->clusterDefinitions());

        $items = $clusterDefinitions
            ->map(function (array $cluster) use ($presentedApprovals, $auditLogs): array {
                $currentItems = $presentedApprovals
                    ->filter(fn (array $approval): bool => $this->matchesCluster($approval, $cluster['recommended_action_code']));

                $clusterLogs = $auditLogs
                    ->filter(fn (AuditLog $log): bool => data_get($log->metadata, 'remediation_context.cluster_key') === $cluster['key']);

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
                    'health_status' => $this->healthStatus($cluster['recommended_action_code'], $currentItems->count(), $publishAttempts->count(), $successfulPublishes->count()),
                    'health_summary' => $this->healthSummary($cluster['recommended_action_code'], $currentItems->count(), $publishAttempts->count(), $successfulPublishes->count()),
                    'route' => $route,
                ];
            })
            ->sortByDesc(fn (array $item): int => $item['current_items'] * 1000 + $item['publish_attempts'] * 10 + $item['manual_check_completions'])
            ->values();

        $topWorkingCluster = $items
            ->filter(fn (array $item): bool => $item['publish_success_rate'] !== null)
            ->sortByDesc(fn (array $item): float => (float) $item['publish_success_rate'])
            ->first();

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
                'tracked_manual_checks' => $auditLogs->where('action', 'approval_manual_check_completed')->count(),
                'tracked_publish_attempts' => $auditLogs->where('action', 'publish_attempted')->count(),
                'successful_publish_attempts' => $auditLogs
                    ->where('action', 'publish_attempted')
                    ->filter(fn (AuditLog $log): bool => (bool) data_get($log->metadata, 'success', false))
                    ->count(),
                'top_working_cluster_label' => $topWorkingCluster['label'] ?? null,
                'window_days' => $windowDays,
            ],
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
}
