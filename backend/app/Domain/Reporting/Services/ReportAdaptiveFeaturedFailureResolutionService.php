<?php

namespace App\Domain\Reporting\Services;

use Illuminate\Support\Collection;

class ReportAdaptiveFeaturedFailureResolutionService
{
    public function __construct(
        private readonly ReportFeaturedFailureResolutionService $reportFeaturedFailureResolutionService,
        private readonly ReportFeaturedFailureResolutionAnalyticsService $reportFeaturedFailureResolutionAnalyticsService,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $retryRecommendations
     * @param  array<int, array<string, mixed>>  $effectivenessItems
     * @return array<string, mixed>|null
     */
    public function recommendForEntity(
        string $workspaceId,
        string $entityType,
        string $entityId,
        array $actions,
        array $retryRecommendations,
        array $effectivenessItems,
    ): ?array {
        $baseRecommendation = $this->reportFeaturedFailureResolutionService->recommend(
            actions: $actions,
            retryRecommendations: $retryRecommendations,
            effectivenessItems: $effectivenessItems,
        );

        if ($baseRecommendation === null) {
            return null;
        }

        $analytics = $this->reportFeaturedFailureResolutionAnalyticsService->index(
            workspaceId: $workspaceId,
            entityType: $entityType,
            entityId: $entityId,
        );

        $analyticsItems = collect($analytics['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values();

        if ($analyticsItems->isEmpty()) {
            return $baseRecommendation;
        }

        $baseAnalytics = $this->bestMatchingAnalytics($analyticsItems, $baseRecommendation);
        $sameReasonAnalytics = $this->bestReasonAnalytics($analyticsItems, $baseRecommendation['reason_code'] ?? null);
        $preferredAnalytics = $baseAnalytics ?? $sameReasonAnalytics;

        $overrideRecommendation = $this->overrideRecommendation(
            actions: collect($actions)->filter(fn (mixed $item): bool => is_array($item))->values(),
            baseRecommendation: $baseRecommendation,
            analytics: $sameReasonAnalytics,
            retryRecommendations: $retryRecommendations,
        );

        if ($overrideRecommendation !== null) {
            return $overrideRecommendation;
        }

        return $this->withAnalyticsContext($baseRecommendation, $preferredAnalytics);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $retryRecommendations
     * @param  array<string, mixed>  $baseRecommendation
     * @param  array<string, mixed>|null  $analytics
     * @return array<string, mixed>|null
     */
    private function overrideRecommendation(
        Collection $actions,
        array $baseRecommendation,
        ?array $analytics,
        array $retryRecommendations,
    ): ?array {
        if ($analytics === null) {
            return null;
        }

        $overrideActionLabel = data_get($analytics, 'top_override_action_label');

        if (! is_string($overrideActionLabel) || $overrideActionLabel === '') {
            return null;
        }

        $overrideAction = $actions->first(
            fn (mixed $item): bool => is_array($item) && ($item['label'] ?? null) === $overrideActionLabel,
        );

        if (! is_array($overrideAction)) {
            return null;
        }

        if (($overrideAction['code'] ?? null) === ($baseRecommendation['action_code'] ?? null)) {
            return null;
        }

        $overrideInteractions = (int) ($analytics['override_interactions'] ?? 0);
        $featuredInteractions = (int) ($analytics['featured_interactions'] ?? 0);
        $successfulOverrideExecutions = (int) ($analytics['successful_override_executions'] ?? 0);
        $overrideSuccessRate = $this->toFloatOrNull($analytics['override_success_rate'] ?? null);
        $featuredSuccessRate = $this->toFloatOrNull($analytics['featured_success_rate'] ?? null);

        if ($overrideInteractions <= 0 || $successfulOverrideExecutions <= 0) {
            return null;
        }

        $overrideIsStronger = $featuredSuccessRate === null
            ? true
            : $overrideSuccessRate !== null && $overrideSuccessRate > $featuredSuccessRate;

        $interactionSupportsOverride = $overrideInteractions >= max(1, $featuredInteractions);

        if (! $overrideIsStronger || ! $interactionSupportsOverride) {
            return null;
        }

        $retryRecommendation = $this->matchRetryRecommendation($retryRecommendations, $analytics['reason_code'] ?? null);

        return [
            'status' => 'analytics_override_preferred',
            'status_label' => 'Gozlenen Daha Iyi Duzeltme',
            'source' => 'analytics_feedback',
            'action_code' => $overrideAction['code'],
            'action_label' => $overrideAction['label'],
            'action_kind' => $overrideAction['action_kind'],
            'button_label' => $overrideAction['button_label'],
            'is_available' => (bool) ($overrideAction['is_available'] ?? false),
            'route' => $overrideAction['route'],
            'target_tab' => $overrideAction['target_tab'],
            'reason_code' => $analytics['reason_code'] ?? $baseRecommendation['reason_code'] ?? null,
            'reason_label' => $analytics['reason_label'] ?? $baseRecommendation['reason_label'] ?? null,
            'provider_label' => $analytics['provider_label'] ?? $baseRecommendation['provider_label'] ?? null,
            'delivery_stage_label' => $analytics['delivery_stage_label'] ?? $baseRecommendation['delivery_stage_label'] ?? null,
            'retry_policy' => data_get($retryRecommendation, 'retry_policy'),
            'retry_policy_label' => data_get($retryRecommendation, 'retry_policy_label'),
            'recommended_wait_minutes' => data_get($retryRecommendation, 'recommended_wait_minutes'),
            'recommended_max_attempts' => data_get($retryRecommendation, 'recommended_max_attempts'),
            'effectiveness_status' => $baseRecommendation['effectiveness_status'] ?? null,
            'effectiveness_label' => $baseRecommendation['effectiveness_label'] ?? null,
            'summary' => sprintf(
                '"%s" aksiyonu bu hata tipinde gozlenen operator davranisinda daha iyi sonuc verdi.',
                $overrideAction['label']
            ),
            'metadata' => is_array($overrideAction['metadata'] ?? null) ? $overrideAction['metadata'] : null,
        ] + $this->analyticsContext($analytics);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $analyticsItems
     * @param  array<string, mixed>  $baseRecommendation
     * @return array<string, mixed>|null
     */
    private function bestMatchingAnalytics(Collection $analyticsItems, array $baseRecommendation): ?array
    {
        $reasonCode = is_string($baseRecommendation['reason_code'] ?? null)
            ? $baseRecommendation['reason_code']
            : null;
        $actionCode = is_string($baseRecommendation['action_code'] ?? null)
            ? $baseRecommendation['action_code']
            : null;

        $match = $analyticsItems
            ->filter(function (array $item) use ($reasonCode, $actionCode): bool {
                if ($actionCode === null || ($item['featured_action_code'] ?? null) !== $actionCode) {
                    return false;
                }

                return $reasonCode === null || ($item['reason_code'] ?? null) === $reasonCode;
            })
            ->sort(fn (array $left, array $right): int => $this->compareAnalyticsItems($left, $right))
            ->first();

        return is_array($match) ? $match : null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $analyticsItems
     * @return array<string, mixed>|null
     */
    private function bestReasonAnalytics(Collection $analyticsItems, ?string $reasonCode): ?array
    {
        if ($reasonCode === null || $reasonCode === '') {
            return null;
        }

        $match = $analyticsItems
            ->filter(fn (array $item): bool => ($item['reason_code'] ?? null) === $reasonCode)
            ->sort(fn (array $left, array $right): int => $this->compareAnalyticsItems($left, $right))
            ->first();

        return is_array($match) ? $match : null;
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @param  array<string, mixed>|null  $analytics
     * @return array<string, mixed>
     */
    private function withAnalyticsContext(array $recommendation, ?array $analytics): array
    {
        if ($analytics === null) {
            return $recommendation;
        }

        return $recommendation + $this->analyticsContext($analytics);
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<string, mixed>
     */
    private function analyticsContext(array $analytics): array
    {
        $followRate = $this->toFloatOrNull($analytics['follow_rate'] ?? null);
        $featuredSuccessRate = $this->toFloatOrNull($analytics['featured_success_rate'] ?? null);
        $overrideSuccessRate = $this->toFloatOrNull($analytics['override_success_rate'] ?? null);
        $topOverrideActionLabel = is_string($analytics['top_override_action_label'] ?? null)
            ? $analytics['top_override_action_label']
            : null;
        $trackedInteractions = (int) ($analytics['tracked_interactions'] ?? 0);

        return [
            'analytics_follow_rate' => $followRate,
            'analytics_featured_success_rate' => $featuredSuccessRate,
            'analytics_override_success_rate' => $overrideSuccessRate,
            'analytics_override_action_label' => $topOverrideActionLabel,
            'analytics_guidance' => $this->analyticsGuidance(
                trackedInteractions: $trackedInteractions,
                followRate: $followRate,
                featuredSuccessRate: $featuredSuccessRate,
                overrideSuccessRate: $overrideSuccessRate,
                topOverrideActionLabel: $topOverrideActionLabel,
            ),
        ];
    }

    private function analyticsGuidance(
        int $trackedInteractions,
        ?float $followRate,
        ?float $featuredSuccessRate,
        ?float $overrideSuccessRate,
        ?string $topOverrideActionLabel,
    ): ?string {
        if ($trackedInteractions <= 0) {
            return null;
        }

        $parts = [sprintf('%d etkileşim izlendi', $trackedInteractions)];

        if ($followRate !== null) {
            $parts[] = sprintf('takip %%%s', number_format($followRate, 1, '.', ''));
        }

        if ($featuredSuccessRate !== null) {
            $parts[] = sprintf('onerilen basari %%%s', number_format($featuredSuccessRate, 1, '.', ''));
        }

        if ($overrideSuccessRate !== null && $topOverrideActionLabel) {
            $parts[] = sprintf(
                'override "%s" basari %%%s',
                $topOverrideActionLabel,
                number_format($overrideSuccessRate, 1, '.', '')
            );
        }

        return implode(' / ', $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $retryRecommendations
     * @return array<string, mixed>|null
     */
    private function matchRetryRecommendation(array $retryRecommendations, ?string $reasonCode): ?array
    {
        if (! is_string($reasonCode) || $reasonCode === '') {
            return null;
        }

        $match = collect($retryRecommendations)->first(
            fn (mixed $item): bool => is_array($item) && ($item['reason_code'] ?? null) === $reasonCode,
        );

        return is_array($match) ? $match : null;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareAnalyticsItems(array $left, array $right): int
    {
        $trackedComparison = ((int) ($right['tracked_interactions'] ?? 0)) <=> ((int) ($left['tracked_interactions'] ?? 0));

        if ($trackedComparison !== 0) {
            return $trackedComparison;
        }

        $featuredSuccessComparison = ($this->toFloatOrNull($right['featured_success_rate'] ?? null) ?? -1)
            <=> ($this->toFloatOrNull($left['featured_success_rate'] ?? null) ?? -1);

        if ($featuredSuccessComparison !== 0) {
            return $featuredSuccessComparison;
        }

        return ((int) ($right['successful_featured_executions'] ?? 0)) <=> ((int) ($left['successful_featured_executions'] ?? 0));
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
