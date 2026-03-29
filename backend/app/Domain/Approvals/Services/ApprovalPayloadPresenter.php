<?php

namespace App\Domain\Approvals\Services;

use App\Models\Approval;
use App\Models\CampaignDraft;
use Illuminate\Support\Str;

class ApprovalPayloadPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Approval $approval): array
    {
        $approvable = $approval->approvable;
        $publishState = $this->presentPublishState($approval->publish_response_metadata);

        return [
            'id' => $approval->id,
            'status' => $approval->status,
            'approvable_type' => $approval->approvable_type,
            'approvable_type_label' => class_basename($approval->approvable_type),
            'approvable_id' => $approval->approvable_id,
            'approvable_label' => $this->resolveApprovableLabel($approvable),
            'approvable_route' => $this->resolveApprovableRoute($approvable),
            'submitted_at' => optional($approval->submitted_at)?->toIso8601String(),
            'approved_at' => optional($approval->approved_at)?->toIso8601String(),
            'rejected_at' => optional($approval->rejected_at)?->toIso8601String(),
            'published_at' => optional($approval->published_at)?->toIso8601String(),
            'rejection_reason' => $approval->rejection_reason,
            'publish_state' => $publishState,
        ];
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @return array<string, mixed>|null
     */
    private function presentPublishState(?array $metadata): ?array
    {
        if (! is_array($metadata) || $metadata === []) {
            return null;
        }

        $success = data_get($metadata, 'success');
        $cleanupAttempted = (bool) data_get($metadata, 'cleanup.attempted', false);
        $cleanupSuccess = $cleanupAttempted ? (bool) data_get($metadata, 'cleanup.success', false) : null;
        $metaCampaignId = data_get($metadata, 'meta_reference.campaign_id');
        $metaAdSetId = data_get($metadata, 'meta_reference.ad_set_id');
        $partialPublishDetected = filled($metaCampaignId) && ! $success;
        $manualCheckCompleted = (bool) data_get($metadata, 'manual_check.completed', false);
        $manualCheckRequired = $partialPublishDetected && $cleanupAttempted && $cleanupSuccess === false && ! $manualCheckCompleted;

        return [
            'status' => data_get($metadata, 'status'),
            'success' => is_bool($success) ? $success : null,
            'message' => data_get($metadata, 'message'),
            'meta_campaign_id' => $metaCampaignId,
            'meta_ad_set_id' => $metaAdSetId,
            'partial_publish_detected' => $partialPublishDetected,
            'cleanup_attempted' => $cleanupAttempted,
            'cleanup_success' => $cleanupSuccess,
            'cleanup_message' => data_get($metadata, 'cleanup.message'),
            'manual_check_required' => $manualCheckRequired,
            'manual_check_completed' => $manualCheckCompleted,
            'manual_check_completed_at' => data_get($metadata, 'manual_check.completed_at'),
            'manual_check_completed_by' => data_get($metadata, 'manual_check.completed_by'),
            'manual_check_note' => data_get($metadata, 'manual_check.note'),
            'recommended_action_code' => $this->recommendedActionCode($manualCheckRequired, $manualCheckCompleted, $partialPublishDetected, $cleanupSuccess, $success),
            'recommended_action_label' => $this->recommendedActionLabel($manualCheckRequired, $manualCheckCompleted, $partialPublishDetected, $cleanupSuccess, $success),
            'operator_guidance' => $this->operatorGuidance($manualCheckRequired, $manualCheckCompleted, $partialPublishDetected, $cleanupSuccess, $success, $metaCampaignId),
        ];
    }

    private function recommendedActionCode(
        bool $manualCheckRequired,
        bool $manualCheckCompleted,
        bool $partialPublishDetected,
        ?bool $cleanupSuccess,
        mixed $success,
    ): ?string
    {
        if ($manualCheckRequired) {
            return 'manual_meta_check';
        }

        if ($manualCheckCompleted && $success === false) {
            return 'retry_publish_after_manual_check';
        }

        if ($partialPublishDetected && $cleanupSuccess === true) {
            return 'fix_and_retry_publish';
        }

        if ($success === false) {
            return 'review_publish_error';
        }

        return null;
    }

    private function recommendedActionLabel(
        bool $manualCheckRequired,
        bool $manualCheckCompleted,
        bool $partialPublishDetected,
        ?bool $cleanupSuccess,
        mixed $success,
    ): ?string
    {
        if ($manualCheckRequired) {
            return 'Meta uzerinde manuel kontrol yap';
        }

        if ($manualCheckCompleted && $success === false) {
            return 'Kontrol sonrasi tekrar publish dene';
        }

        if ($partialPublishDetected && $cleanupSuccess === true) {
            return 'Taslagi duzeltip tekrar publish dene';
        }

        if ($success === false) {
            return 'Publish hatasini incele';
        }

        return null;
    }

    private function operatorGuidance(
        bool $manualCheckRequired,
        bool $manualCheckCompleted,
        bool $partialPublishDetected,
        ?bool $cleanupSuccess,
        mixed $success,
        ?string $metaCampaignId,
    ): ?string
    {
        if ($manualCheckRequired) {
            return sprintf(
                'Meta kampanyasi olusmus olabilir. Ads Manager uzerinde %s kaydini manuel kontrol etmeden tekrar publish denemeyin.',
                $metaCampaignId ?: 'ilgili kampanya'
            );
        }

        if ($manualCheckCompleted && $success === false) {
            return 'Manuel kontrol tamamlandi. Gerekli duzeltmeleri yaptiysaniz publish islemini tekrar deneyebilirsiniz.';
        }

        if ($partialPublishDetected && $cleanupSuccess === true) {
            return 'Kampanya rollback ile temizlendi. Draft girdilerini duzeltip publish islemini guvenle tekrar deneyebilirsiniz.';
        }

        if ($success === false) {
            return 'Publish hatasi partial publish birakmadi. Hata mesajini inceleyip draft verisini duzeltin.';
        }

        return null;
    }

    private function resolveApprovableLabel(mixed $approvable): string
    {
        if ($approvable instanceof CampaignDraft) {
            $service = Str::limit((string) $approvable->product_service, 48);

            return trim(sprintf('%s - %s', $approvable->objective, $service), ' -');
        }

        return 'Onay kaydi';
    }

    private function resolveApprovableRoute(mixed $approvable): ?string
    {
        if ($approvable instanceof CampaignDraft) {
            return sprintf('/drafts/detail?id=%s', $approvable->id);
        }

        return null;
    }
}
