<?php

namespace Tests\Unit\Reporting;

use App\Domain\Reporting\Services\ReportAdaptiveFeaturedFailureResolutionService;
use App\Domain\Reporting\Services\ReportFeaturedFailureResolutionAnalyticsService;
use App\Domain\Reporting\Services\ReportFeaturedFailureResolutionService;
use PHPUnit\Framework\TestCase;

class ReportAdaptiveFeaturedFailureResolutionServiceTest extends TestCase
{
    public function test_it_keeps_base_recommendation_and_attaches_analytics_context(): void
    {
        $analyticsService = $this->createMock(ReportFeaturedFailureResolutionAnalyticsService::class);
        $analyticsService
            ->expects($this->once())
            ->method('index')
            ->with('workspace-1', 90, 'account', 'entity-1')
            ->willReturn([
                'summary' => [],
                'items' => [[
                    'featured_action_code' => 'retry_failed_runs',
                    'featured_action_label' => 'Basarisiz teslimleri tekrar dene',
                    'featured_status' => 'working_fix',
                    'featured_status_label' => 'Calisan Duzeltme',
                    'featured_source' => 'effectiveness',
                    'reason_code' => 'smtp_timeout',
                    'reason_label' => 'SMTP Timeout',
                    'provider_label' => 'SMTP',
                    'delivery_stage_label' => 'Baglanti',
                    'tracked_interactions' => 2,
                    'featured_interactions' => 2,
                    'override_interactions' => 0,
                    'featured_api_attempts' => 2,
                    'override_api_attempts' => 0,
                    'successful_featured_executions' => 2,
                    'partial_featured_executions' => 0,
                    'failed_featured_executions' => 0,
                    'successful_override_executions' => 0,
                    'partial_override_executions' => 0,
                    'failed_override_executions' => 0,
                    'follow_rate' => 100.0,
                    'featured_success_rate' => 100.0,
                    'override_success_rate' => null,
                    'top_override_action_label' => null,
                    'usage_summary' => '2 oneriyi takip',
                    'last_seen_at' => now()->subMinute()->toDateTimeString(),
                ]],
            ]);

        $service = new ReportAdaptiveFeaturedFailureResolutionService(
            new ReportFeaturedFailureResolutionService(),
            $analyticsService,
        );

        $result = $service->recommendForEntity(
            workspaceId: 'workspace-1',
            entityType: 'account',
            entityId: 'entity-1',
            actions: [[
                'code' => 'retry_failed_runs',
                'label' => 'Basarisiz teslimleri tekrar dene',
                'action_kind' => 'api',
                'button_label' => 'Toplu Retry Calistir',
                'is_available' => true,
                'route' => null,
                'target_tab' => 'reports',
                'metadata' => ['retryable_runs' => 2],
            ]],
            retryRecommendations: [[
                'reason_code' => 'smtp_timeout',
                'label' => 'SMTP Timeout',
                'provider_label' => 'SMTP',
                'delivery_stage_label' => 'Baglanti',
                'retry_policy' => 'auto_retry',
                'retry_policy_label' => 'Otomatik Retry Uygun',
                'recommended_wait_minutes' => 10,
                'recommended_max_attempts' => 2,
                'primary_action_code' => 'retry_failed_runs',
                'operator_note' => 'Retry calistir.',
            ]],
            effectivenessItems: [[
                'reason_code' => 'smtp_timeout',
                'label' => 'SMTP Timeout',
                'provider_label' => 'SMTP',
                'delivery_stage_label' => 'Baglanti',
                'recommended_action' => [
                    'code' => 'retry_failed_runs',
                    'retry_policy' => 'auto_retry',
                    'retry_policy_label' => 'Otomatik Retry Uygun',
                    'recommended_wait_minutes' => 10,
                    'recommended_max_attempts' => 2,
                ],
                'effectiveness_status' => 'working_well',
                'effectiveness_label' => 'Duzeltme Ise Yariyor',
                'effectiveness_summary' => 'Onerilen retry aksiyonu ise yariyor.',
            ]],
        );

        $this->assertSame('working_fix', $result['status']);
        $this->assertSame('retry_failed_runs', $result['action_code']);
        $this->assertSame(100.0, $result['analytics_follow_rate']);
        $this->assertSame(100.0, $result['analytics_featured_success_rate']);
        $this->assertNull($result['analytics_override_success_rate']);
        $this->assertSame('2 etkileşim izlendi / takip %100.0 / onerilen basari %100.0', $result['analytics_guidance']);
    }

    public function test_it_promotes_override_action_when_override_outperforms_featured_choice(): void
    {
        $analyticsService = $this->createMock(ReportFeaturedFailureResolutionAnalyticsService::class);
        $analyticsService
            ->expects($this->once())
            ->method('index')
            ->with('workspace-1', 90, 'campaign', 'entity-2')
            ->willReturn([
                'summary' => [],
                'items' => [[
                    'featured_action_code' => 'review_contact_book',
                    'featured_action_label' => 'Alici kisilerini kontrol et',
                    'featured_status' => 'blocked_retry',
                    'featured_status_label' => 'Retry Bloklu',
                    'featured_source' => 'retry_policy',
                    'reason_code' => 'recipient_rejected',
                    'reason_label' => 'Alici Reddi',
                    'provider_label' => 'SMTP',
                    'delivery_stage_label' => 'Alici Dogrulama',
                    'tracked_interactions' => 2,
                    'featured_interactions' => 0,
                    'override_interactions' => 2,
                    'featured_api_attempts' => 0,
                    'override_api_attempts' => 1,
                    'successful_featured_executions' => 0,
                    'partial_featured_executions' => 0,
                    'failed_featured_executions' => 0,
                    'successful_override_executions' => 1,
                    'partial_override_executions' => 0,
                    'failed_override_executions' => 0,
                    'follow_rate' => 0.0,
                    'featured_success_rate' => null,
                    'override_success_rate' => 100.0,
                    'top_override_action_label' => 'Alici grubunu duzelt',
                    'usage_summary' => '2 override',
                    'last_seen_at' => now()->subMinute()->toDateTimeString(),
                ]],
            ]);

        $service = new ReportAdaptiveFeaturedFailureResolutionService(
            new ReportFeaturedFailureResolutionService(),
            $analyticsService,
        );

        $result = $service->recommendForEntity(
            workspaceId: 'workspace-1',
            entityType: 'campaign',
            entityId: 'entity-2',
            actions: [
                [
                    'code' => 'review_contact_book',
                    'label' => 'Alici kisilerini kontrol et',
                    'action_kind' => 'route',
                    'button_label' => 'Kisi Havuzunu Ac',
                    'is_available' => true,
                    'route' => '/reports#contacts',
                    'target_tab' => null,
                    'metadata' => ['sample_recipients' => ['rejected@castintech.com']],
                ],
                [
                    'code' => 'review_recipient_groups',
                    'label' => 'Alici grubunu duzelt',
                    'action_kind' => 'route',
                    'button_label' => 'Alici Gruplarina Git',
                    'is_available' => true,
                    'route' => '/reports#recipient-groups',
                    'target_tab' => null,
                    'metadata' => ['affected_group_labels' => ['Recipient Reject Group']],
                ],
            ],
            retryRecommendations: [[
                'reason_code' => 'recipient_rejected',
                'label' => 'Alici Reddi',
                'provider_label' => 'SMTP',
                'delivery_stage_label' => 'Alici Dogrulama',
                'retry_policy' => 'do_not_retry',
                'retry_policy_label' => 'Retry Bloklu',
                'recommended_wait_minutes' => null,
                'recommended_max_attempts' => 0,
                'primary_action_code' => 'review_contact_book',
                'operator_note' => 'Alici grubunu kontrol edin.',
            ]],
            effectivenessItems: [[
                'reason_code' => 'recipient_rejected',
                'label' => 'Alici Reddi',
                'provider_label' => 'SMTP',
                'delivery_stage_label' => 'Alici Dogrulama',
                'recommended_action' => [
                    'code' => 'review_contact_book',
                    'retry_policy' => 'do_not_retry',
                    'retry_policy_label' => 'Retry Bloklu',
                    'recommended_wait_minutes' => null,
                    'recommended_max_attempts' => 0,
                ],
                'effectiveness_status' => 'not_applied',
                'effectiveness_label' => 'Henuz Uygulanmadi',
                'effectiveness_summary' => 'Onerilen aksiyon henuz kullanilmadi.',
            ]],
        );

        $this->assertSame('analytics_override_preferred', $result['status']);
        $this->assertSame('analytics_feedback', $result['source']);
        $this->assertSame('review_recipient_groups', $result['action_code']);
        $this->assertSame('recipient_rejected', $result['reason_code']);
        $this->assertSame(100.0, $result['analytics_override_success_rate']);
        $this->assertSame('Alici grubunu duzelt', $result['analytics_override_action_label']);
    }
}
