<?php

namespace Tests\Unit\Reporting;

use App\Domain\Reporting\Services\ReportDeliveryRetryRecommendationService;
use PHPUnit\Framework\TestCase;

class ReportDeliveryRetryRecommendationServiceTest extends TestCase
{
    public function test_it_recommends_auto_retry_for_smtp_timeout(): void
    {
        $service = new ReportDeliveryRetryRecommendationService();

        $recommendation = $service->recommendationForFailureReason([
            'reason_code' => 'smtp_timeout',
            'label' => 'SMTP Timeout',
            'provider' => 'smtp',
            'provider_label' => 'SMTP',
            'delivery_stage' => 'connect',
            'delivery_stage_label' => 'Baglanti',
            'failed_runs' => 2,
        ]);

        $this->assertSame('auto_retry', $recommendation['retry_policy']);
        $this->assertSame('retry_failed_runs', $recommendation['primary_action_code']);
        $this->assertSame(10, $recommendation['recommended_wait_minutes']);
        $this->assertSame(2, $recommendation['recommended_max_attempts']);
    }

    public function test_it_blocks_retry_for_recipient_rejected_failures(): void
    {
        $service = new ReportDeliveryRetryRecommendationService();

        $recommendation = $service->recommendationForFailureReason([
            'reason_code' => 'recipient_rejected',
            'label' => 'Alici Reddi',
            'provider' => 'smtp',
            'provider_label' => 'SMTP',
            'delivery_stage' => 'recipient_validation',
            'delivery_stage_label' => 'Alici Dogrulama',
            'failed_runs' => 1,
        ]);

        $this->assertSame('do_not_retry', $recommendation['retry_policy']);
        $this->assertSame('review_contact_book', $recommendation['primary_action_code']);
        $this->assertNull($recommendation['recommended_wait_minutes']);
        $this->assertSame(0, $recommendation['recommended_max_attempts']);
    }
}
