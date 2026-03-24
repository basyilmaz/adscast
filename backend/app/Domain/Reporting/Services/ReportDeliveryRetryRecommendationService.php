<?php

namespace App\Domain\Reporting\Services;

use Illuminate\Support\Collection;

class ReportDeliveryRetryRecommendationService
{
    /**
     * @param  array<string, mixed>  $failureReason
     * @return array<string, mixed>
     */
    public function recommendationForFailureReason(array $failureReason): array
    {
        $reasonCode = (string) ($failureReason['reason_code'] ?? $failureReason['code'] ?? 'unknown_failure');
        $provider = (string) ($failureReason['provider'] ?? 'application');
        $deliveryStage = (string) ($failureReason['delivery_stage'] ?? 'unknown');
        $failedRuns = (int) ($failureReason['failed_runs'] ?? 1);

        [$retryPolicy, $retryPolicyLabel, $primaryActionCode, $recommendedWaitMinutes, $recommendedMaxAttempts, $operatorNote] = match ($reasonCode) {
            'smtp_timeout' => [
                'auto_retry',
                'Otomatik Retry Uygun',
                'retry_failed_runs',
                10,
                2,
                'SMTP timeout gecici olabilir. Ilk adimda toplu retry calistirmak mantikli.',
            ],
            'smtp_connectivity' => [
                'auto_retry',
                'Otomatik Retry Uygun',
                'retry_failed_runs',
                15,
                2,
                'Baglanti problemi gecici olabilir. Retry oncesi provider ve ag durumu kisa sure izlenmeli.',
            ],
            'manual_retry_pending' => [
                'manual_retry',
                'Manuel Retry Bekleniyor',
                'retry_failed_runs',
                0,
                1,
                'Run zaten manuel retry bekliyor; operator dogrudan retry aksiyonunu calistirabilir.',
            ],
            'recipient_rejected' => [
                'do_not_retry',
                'Retry Oncesi Alici Temizligi Gerekli',
                'review_contact_book',
                null,
                0,
                'Alici reddi olan teslimler temizlenmeden tekrar denenmemeli.',
            ],
            'invalid_configuration', 'share_delivery_failure', 'snapshot_export_failure' => [
                'retry_after_fix',
                'Konfigurasyon Duzeltildikten Sonra Retry',
                'focus_delivery_profile',
                null,
                1,
                'Profil, share veya export ayari duzeltilmeden retry verimli olmaz.',
            ],
            'smtp_auth', 'smtp_tls', 'sender_rejected' => [
                'do_not_retry',
                'Retry Oncesi Kanal Duzeltmesi Gerekli',
                'focus_delivery_profile',
                null,
                0,
                'Kimlik dogrulama veya sender/tls sorunu cozulmeden retry etmeyin.',
            ],
            default => [
                'manual_review',
                'Operator Incelemesi Gerekli',
                'focus_delivery_profile',
                null,
                1,
                'Siniflandirma net degil. Once operator incelemesi yapilip sonra retry karari verilmelidir.',
            ],
        };

        return [
            'reason_code' => $reasonCode,
            'label' => (string) ($failureReason['label'] ?? 'Bilinmeyen Hata'),
            'provider' => $provider,
            'provider_label' => (string) ($failureReason['provider_label'] ?? 'Uygulama'),
            'delivery_stage' => $deliveryStage,
            'delivery_stage_label' => (string) ($failureReason['delivery_stage_label'] ?? 'Bilinmeyen Asama'),
            'severity' => (string) ($failureReason['severity'] ?? 'warning'),
            'failed_runs' => $failedRuns,
            'retry_policy' => $retryPolicy,
            'retry_policy_label' => $retryPolicyLabel,
            'primary_action_code' => $primaryActionCode,
            'recommended_wait_minutes' => $recommendedWaitMinutes,
            'recommended_max_attempts' => $recommendedMaxAttempts,
            'operator_note' => $operatorNote,
            'summary' => sprintf(
                '%s / %s / %s',
                (string) ($failureReason['label'] ?? 'Bilinmeyen Hata'),
                (string) ($failureReason['provider_label'] ?? 'Uygulama'),
                (string) ($failureReason['delivery_stage_label'] ?? 'Bilinmeyen Asama'),
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $failureReasons
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function fromFailureReasonItems(array $failureReasons): array
    {
        $items = collect($failureReasons)
            ->map(fn (array $item): array => $this->recommendationForFailureReason($item))
            ->sort(function (array $left, array $right): int {
                $failedComparison = $right['failed_runs'] <=> $left['failed_runs'];

                if ($failedComparison !== 0) {
                    return $failedComparison;
                }

                return strcmp($left['label'], $right['label']);
            })
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
    private function summaryPayload(Collection $items): array
    {
        $topPolicy = $items
            ->groupBy('retry_policy')
            ->sortByDesc(fn (Collection $group): int => (int) $group->sum('failed_runs'))
            ->map(fn (Collection $group): array => $group->first())
            ->first();

        return [
            'total_recommendations' => $items->count(),
            'auto_retry_recommendations' => $items->where('retry_policy', 'auto_retry')->count(),
            'manual_retry_recommendations' => $items->where('retry_policy', 'manual_retry')->count(),
            'retry_after_fix_recommendations' => $items->where('retry_policy', 'retry_after_fix')->count(),
            'blocked_retry_recommendations' => $items->where('retry_policy', 'do_not_retry')->count(),
            'top_policy_label' => is_array($topPolicy) ? ($topPolicy['retry_policy_label'] ?? null) : null,
        ];
    }
}
