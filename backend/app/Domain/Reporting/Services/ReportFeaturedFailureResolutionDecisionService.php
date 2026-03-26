<?php

namespace App\Domain\Reporting\Services;

use Illuminate\Support\Collection;

class ReportFeaturedFailureResolutionDecisionService
{
    /**
     * @param  array<int, array<string, mixed>>  $effectivenessItems
     * @param  array<int, array<string, mixed>>  $featuredAnalyticsItems
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function build(array $effectivenessItems, array $featuredAnalyticsItems): array
    {
        $analyticsByReason = collect($featuredAnalyticsItems)
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['reason_code'] ?? null))
            ->groupBy(fn (array $item): string => (string) $item['reason_code'])
            ->map(fn (Collection $items): array => $this->bestAnalyticsItem($items));

        $items = collect($effectivenessItems)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $effectivenessItem) use ($analyticsByReason): array {
                $reasonCode = (string) ($effectivenessItem['reason_code'] ?? 'unknown_failure');
                $analytics = $analyticsByReason->get($reasonCode);

                return $this->decisionItem($effectivenessItem, is_array($analytics) ? $analytics : null);
            })
            ->sort(fn (array $left, array $right): int => $this->compareItems($left, $right))
            ->values();

        return [
            'summary' => $this->summaryPayload($items),
            'items' => $items->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function bestAnalyticsItem(Collection $items): array
    {
        /** @var array<string, mixed> $item */
        $item = $items
            ->sort(fn (array $left, array $right): int => $this->compareAnalyticsItems($left, $right))
            ->first();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $effectivenessItem
     * @param  array<string, mixed>|null  $analytics
     * @return array<string, mixed>
     */
    private function decisionItem(array $effectivenessItem, ?array $analytics): array
    {
        $recommendedAction = is_array($effectivenessItem['recommended_action'] ?? null)
            ? $effectivenessItem['recommended_action']
            : [];
        $topObservedAction = is_array($effectivenessItem['top_observed_action'] ?? null)
            ? $effectivenessItem['top_observed_action']
            : null;
        $reasonCode = (string) ($effectivenessItem['reason_code'] ?? 'unknown_failure');
        $reasonLabel = (string) ($effectivenessItem['label'] ?? 'Bilinmeyen Hata');
        $workingEffectiveness = ($effectivenessItem['effectiveness_status'] ?? null) === 'working_well';
        $manualFollowup = ($effectivenessItem['effectiveness_status'] ?? null) === 'manual_followup_active';
        $overridePreferred = $this->shouldPreferOverride($analytics);

        if ($overridePreferred) {
            $selectedActionCode = $this->actionCodeFromEffectiveness($effectivenessItem, (string) ($analytics['top_override_action_label'] ?? ''));
            $selectedActionLabel = (string) ($analytics['top_override_action_label'] ?? 'Alternatif operator aksiyonu');
            $decisionStatus = 'analytics_override_preferred';
            $decisionLabel = 'Analytics Override Tercihi';
            $source = 'analytics_feedback';
            $whySelected = sprintf(
                '%s icin override aksiyonu daha iyi sonuc verdi. Sistem artik "%s" aksiyonunu one cikariyor.',
                $reasonLabel,
                $selectedActionLabel,
            );
        } elseif ($workingEffectiveness) {
            $selectedActionCode = (string) ($recommendedAction['code'] ?? 'unknown_action');
            $selectedActionLabel = (string) ($recommendedAction['label'] ?? $selectedActionCode);
            $decisionStatus = 'working_featured';
            $decisionLabel = 'Calisan Featured Fix';
            $source = 'effectiveness';
            $whySelected = (string) ($effectivenessItem['effectiveness_summary'] ?? 'Onerilen fix gozlenen basari verisiyle destekleniyor.');
        } elseif ($manualFollowup) {
            $selectedActionCode = (string) ($recommendedAction['code'] ?? 'unknown_action');
            $selectedActionLabel = (string) ($recommendedAction['label'] ?? $selectedActionCode);
            $decisionStatus = 'manual_followup';
            $decisionLabel = 'Manuel Takip';
            $source = 'effectiveness';
            $whySelected = (string) ($effectivenessItem['effectiveness_summary'] ?? 'Bu hata tipi manuel operator takibi istiyor.');
        } else {
            $selectedActionCode = (string) ($recommendedAction['code'] ?? 'unknown_action');
            $selectedActionLabel = (string) ($recommendedAction['label'] ?? $selectedActionCode);
            $decisionStatus = 'default_recommendation';
            $decisionLabel = 'Varsayilan Oneri';
            $source = $analytics ? 'featured_analytics' : 'effectiveness';
            $whySelected = $analytics && is_string($analytics['usage_summary'] ?? null)
                ? sprintf('Takip verisi sinirli. Varsayilan fix korunuyor. %s', $analytics['usage_summary'])
                : 'Takip verisi sinirli oldugu icin varsayilan fix korunuyor.';
        }

        return [
            'reason_code' => $reasonCode,
            'reason_label' => $reasonLabel,
            'provider_label' => $effectivenessItem['provider_label'] ?? null,
            'delivery_stage_label' => $effectivenessItem['delivery_stage_label'] ?? null,
            'failed_runs' => (int) ($effectivenessItem['failed_runs'] ?? 0),
            'decision_status' => $decisionStatus,
            'decision_status_label' => $decisionLabel,
            'source' => $source,
            'selected_action_code' => $selectedActionCode,
            'selected_action_label' => $selectedActionLabel,
            'recommended_action_code' => $recommendedAction['code'] ?? null,
            'recommended_action_label' => $recommendedAction['label'] ?? null,
            'top_observed_action_label' => $topObservedAction['label'] ?? null,
            'follow_rate' => $this->toFloatOrNull($analytics['follow_rate'] ?? null),
            'featured_success_rate' => $this->toFloatOrNull($analytics['featured_success_rate'] ?? null),
            'override_success_rate' => $this->toFloatOrNull($analytics['override_success_rate'] ?? null),
            'top_override_action_label' => $analytics['top_override_action_label'] ?? null,
            'tracked_interactions' => (int) ($analytics['tracked_interactions'] ?? 0),
            'featured_interactions' => (int) ($analytics['featured_interactions'] ?? 0),
            'override_interactions' => (int) ($analytics['override_interactions'] ?? 0),
            'primary_entity' => is_array($analytics['top_entity'] ?? null)
                ? [
                    'entity_type' => $analytics['top_entity']['entity_type'] ?? null,
                    'entity_id' => $analytics['top_entity']['entity_id'] ?? null,
                    'label' => $analytics['top_entity']['label'] ?? null,
                    'context_label' => $analytics['top_entity']['context_label'] ?? null,
                    'route' => $analytics['top_entity']['route'] ?? null,
                    'uses_count' => (int) ($analytics['top_entity']['uses_count'] ?? 0),
                ]
                : null,
            'why_selected' => $whySelected,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $analytics
     */
    private function shouldPreferOverride(?array $analytics): bool
    {
        if ($analytics === null) {
            return false;
        }

        $overrideActionLabel = $analytics['top_override_action_label'] ?? null;
        $overrideInteractions = (int) ($analytics['override_interactions'] ?? 0);
        $featuredInteractions = (int) ($analytics['featured_interactions'] ?? 0);
        $successfulOverrideExecutions = (int) ($analytics['successful_override_executions'] ?? 0);
        $overrideSuccessRate = $this->toFloatOrNull($analytics['override_success_rate'] ?? null);
        $featuredSuccessRate = $this->toFloatOrNull($analytics['featured_success_rate'] ?? null);

        if (! is_string($overrideActionLabel) || $overrideActionLabel === '' || $overrideInteractions <= 0 || $successfulOverrideExecutions <= 0) {
            return false;
        }

        if ($featuredSuccessRate === null) {
            return true;
        }

        return $overrideSuccessRate !== null
            && $overrideSuccessRate > $featuredSuccessRate
            && $overrideInteractions >= max(1, $featuredInteractions);
    }

    /**
     * @param  array<string, mixed>  $effectivenessItem
     */
    private function actionCodeFromEffectiveness(array $effectivenessItem, string $label): ?string
    {
        $actions = collect($effectivenessItem['actions'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['label'] ?? null) === $label);

        return $actions->value('action_code');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summaryPayload(Collection $items): array
    {
        $topDecision = $items
            ->sortByDesc('failed_runs')
            ->first();

        return [
            'total_reasons' => $items->count(),
            'analytics_override_preferred' => $items->where('decision_status', 'analytics_override_preferred')->count(),
            'working_featured' => $items->where('decision_status', 'working_featured')->count(),
            'manual_followup' => $items->where('decision_status', 'manual_followup')->count(),
            'default_recommendation' => $items->where('decision_status', 'default_recommendation')->count(),
            'top_selected_action_label' => data_get($topDecision, 'selected_action_label'),
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareItems(array $left, array $right): int
    {
        $failedComparison = ($right['failed_runs'] ?? 0) <=> ($left['failed_runs'] ?? 0);

        if ($failedComparison !== 0) {
            return $failedComparison;
        }

        $priority = [
            'analytics_override_preferred' => 0,
            'working_featured' => 1,
            'manual_followup' => 2,
            'default_recommendation' => 3,
        ];
        $statusComparison = ($priority[$left['decision_status']] ?? 99) <=> ($priority[$right['decision_status']] ?? 99);

        if ($statusComparison !== 0) {
            return $statusComparison;
        }

        return strcmp((string) $left['reason_label'], (string) $right['reason_label']);
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

        return ((int) ($right['successful_featured_executions'] ?? 0)) <=> ((int) ($left['successful_featured_executions'] ?? 0));
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
