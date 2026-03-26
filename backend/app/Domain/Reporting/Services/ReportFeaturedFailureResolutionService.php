<?php

namespace App\Domain\Reporting\Services;

use Illuminate\Support\Collection;

class ReportFeaturedFailureResolutionService
{
    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $retryRecommendations
     * @param  array<int, array<string, mixed>>  $effectivenessItems
     * @return array<string, mixed>|null
     */
    public function recommend(
        array $actions,
        array $retryRecommendations,
        array $effectivenessItems,
    ): ?array {
        $actionsCollection = collect($actions)->values();
        $effectivenessCollection = collect($effectivenessItems)->values();
        $retryCollection = collect($retryRecommendations)->values();

        if ($actionsCollection->isEmpty() && $effectivenessCollection->isEmpty() && $retryCollection->isEmpty()) {
            return null;
        }

        $workingFix = $this->firstEffectivenessMatch($actionsCollection, $effectivenessCollection, 'working_well');

        if ($workingFix) {
            return $this->payloadFromEffectiveness(
                action: $workingFix['action'],
                effectiveness: $workingFix['effectiveness'],
                status: 'working_fix',
                statusLabel: 'Calisan Duzeltme',
                source: 'effectiveness',
                summary: (string) ($workingFix['effectiveness']['effectiveness_summary'] ?? 'Bu hata tipi icin calisan bir duzeltme var.'),
            );
        }

        $manualFollowup = $this->firstEffectivenessMatch($actionsCollection, $effectivenessCollection, 'manual_followup_active');

        if ($manualFollowup) {
            return $this->payloadFromEffectiveness(
                action: $manualFollowup['action'],
                effectiveness: $manualFollowup['effectiveness'],
                status: 'manual_followup',
                statusLabel: 'Manuel Duzeltme Onerisi',
                source: 'effectiveness',
                summary: (string) ($manualFollowup['effectiveness']['effectiveness_summary'] ?? 'Bu hata tipi manuel operator duzeltmesi istiyor.'),
            );
        }

        $partialFix = $this->firstEffectivenessMatch($actionsCollection, $effectivenessCollection, 'partially_working');

        if ($partialFix) {
            return $this->payloadFromEffectiveness(
                action: $partialFix['action'],
                effectiveness: $partialFix['effectiveness'],
                status: 'partially_working',
                statusLabel: 'Kismen Calisan Duzeltme',
                source: 'effectiveness',
                summary: (string) ($partialFix['effectiveness']['effectiveness_summary'] ?? 'Bu hata tipi icin kismen sonuc veren bir duzeltme var.'),
            );
        }

        foreach ($retryCollection as $recommendation) {
            $primaryActionCode = data_get($recommendation, 'primary_action_code');
            $action = $this->matchAction($actionsCollection, is_string($primaryActionCode) ? $primaryActionCode : null);

            if (! $action) {
                continue;
            }

            return $this->payloadFromRetryRecommendation($action, $recommendation);
        }

        $fallbackAction = $actionsCollection->first();

        if (! is_array($fallbackAction)) {
            return null;
        }

        return [
            'status' => 'available_action',
            'status_label' => 'Hazir Aksiyon',
            'source' => 'action_inventory',
            'action_code' => $fallbackAction['code'],
            'action_label' => $fallbackAction['label'],
            'action_kind' => $fallbackAction['action_kind'],
            'button_label' => $fallbackAction['button_label'],
            'is_available' => (bool) ($fallbackAction['is_available'] ?? false),
            'route' => $fallbackAction['route'],
            'target_tab' => $fallbackAction['target_tab'],
            'reason_code' => null,
            'reason_label' => null,
            'provider_label' => null,
            'delivery_stage_label' => null,
            'retry_policy' => null,
            'retry_policy_label' => null,
            'recommended_wait_minutes' => null,
            'recommended_max_attempts' => null,
            'effectiveness_status' => null,
            'effectiveness_label' => null,
            'summary' => (string) ($fallbackAction['detail'] ?? 'Bu kayit icin uygulanabilir bir duzeltme aksiyonu var.'),
            'metadata' => is_array($fallbackAction['metadata'] ?? null) ? $fallbackAction['metadata'] : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $actions
     * @param  Collection<int, array<string, mixed>>  $effectivenessItems
     * @return array{action: array<string, mixed>, effectiveness: array<string, mixed>}|null
     */
    private function firstEffectivenessMatch(Collection $actions, Collection $effectivenessItems, string $status): ?array
    {
        foreach ($effectivenessItems as $effectiveness) {
            if (! is_array($effectiveness) || ($effectiveness['effectiveness_status'] ?? null) !== $status) {
                continue;
            }

            $actionCode = data_get($effectiveness, 'recommended_action.code');
            $action = $this->matchAction($actions, is_string($actionCode) ? $actionCode : null);

            if ($action) {
                return [
                    'action' => $action,
                    'effectiveness' => $effectiveness,
                ];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $actions
     * @return array<string, mixed>|null
     */
    private function matchAction(Collection $actions, ?string $actionCode): ?array
    {
        if (! $actionCode) {
            return null;
        }

        $matched = $actions->first(
            fn (mixed $item): bool => is_array($item) && ($item['code'] ?? null) === $actionCode,
        );

        return is_array($matched) ? $matched : null;
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $effectiveness
     * @return array<string, mixed>
     */
    private function payloadFromEffectiveness(
        array $action,
        array $effectiveness,
        string $status,
        string $statusLabel,
        string $source,
        string $summary,
    ): array {
        return [
            'status' => $status,
            'status_label' => $statusLabel,
            'source' => $source,
            'action_code' => $action['code'],
            'action_label' => $action['label'],
            'action_kind' => $action['action_kind'],
            'button_label' => $action['button_label'],
            'is_available' => (bool) ($action['is_available'] ?? false),
            'route' => $action['route'],
            'target_tab' => $action['target_tab'],
            'reason_code' => $effectiveness['reason_code'],
            'reason_label' => $effectiveness['label'],
            'provider_label' => $effectiveness['provider_label'],
            'delivery_stage_label' => $effectiveness['delivery_stage_label'],
            'retry_policy' => data_get($effectiveness, 'recommended_action.retry_policy'),
            'retry_policy_label' => data_get($effectiveness, 'recommended_action.retry_policy_label'),
            'recommended_wait_minutes' => data_get($effectiveness, 'recommended_action.recommended_wait_minutes'),
            'recommended_max_attempts' => data_get($effectiveness, 'recommended_action.recommended_max_attempts'),
            'effectiveness_status' => $effectiveness['effectiveness_status'],
            'effectiveness_label' => $effectiveness['effectiveness_label'],
            'summary' => $summary,
            'metadata' => is_array($action['metadata'] ?? null) ? $action['metadata'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>
     */
    private function payloadFromRetryRecommendation(array $action, array $recommendation): array
    {
        [$status, $statusLabel] = match ($recommendation['retry_policy'] ?? null) {
            'auto_retry' => ['retry_recommended', 'Retry Onerisi'],
            'manual_retry' => ['manual_retry', 'Manuel Retry'],
            'retry_after_fix' => ['fix_before_retry', 'Fix Sonrasi Retry'],
            'do_not_retry' => ['blocked_retry', 'Retry Bloklu'],
            default => ['manual_review', 'Operator Incelemesi'],
        };

        return [
            'status' => $status,
            'status_label' => $statusLabel,
            'source' => 'retry_policy',
            'action_code' => $action['code'],
            'action_label' => $action['label'],
            'action_kind' => $action['action_kind'],
            'button_label' => $action['button_label'],
            'is_available' => (bool) ($action['is_available'] ?? false),
            'route' => $action['route'],
            'target_tab' => $action['target_tab'],
            'reason_code' => $recommendation['reason_code'],
            'reason_label' => $recommendation['label'],
            'provider_label' => $recommendation['provider_label'],
            'delivery_stage_label' => $recommendation['delivery_stage_label'],
            'retry_policy' => $recommendation['retry_policy'],
            'retry_policy_label' => $recommendation['retry_policy_label'],
            'recommended_wait_minutes' => $recommendation['recommended_wait_minutes'],
            'recommended_max_attempts' => $recommendation['recommended_max_attempts'],
            'effectiveness_status' => null,
            'effectiveness_label' => null,
            'summary' => (string) ($recommendation['operator_note'] ?? $action['detail'] ?? 'Bu hata tipi icin onerilen bir duzeltme var.'),
            'metadata' => is_array($action['metadata'] ?? null) ? $action['metadata'] : null,
        ];
    }
}
