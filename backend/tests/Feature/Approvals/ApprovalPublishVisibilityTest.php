<?php

namespace Tests\Feature\Approvals;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\CampaignDraft;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalPublishVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_approvals_index_surfaces_cleanup_failed_publish_state(): void
    {
        [$workspaceId, $token, $draft, $approval] = $this->seedFailedPublishFixture();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals');

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', $approval->id)
            ->assertJsonPath('data.data.0.approvable_route', "/drafts/detail?id={$draft->id}")
            ->assertJsonPath('data.data.0.publish_state.partial_publish_detected', true)
            ->assertJsonPath('data.data.0.publish_state.cleanup_attempted', true)
            ->assertJsonPath('data.data.0.publish_state.cleanup_success', false)
            ->assertJsonPath('data.data.0.publish_state.manual_check_required', true)
            ->assertJsonPath('data.data.0.publish_state.recommended_action_code', 'manual_meta_check');
    }

    public function test_draft_detail_surfaces_enriched_approval_publish_state(): void
    {
        [$workspaceId, $token, $draft] = $this->seedFailedPublishFixture();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson("/api/v1/drafts/{$draft->id}");

        $response->assertOk()
            ->assertJsonPath('data.approval.id', $draft->approval->id)
            ->assertJsonPath('data.approval.publish_state.manual_check_required', true)
            ->assertJsonPath('data.approval.publish_state.meta_campaign_id', 'meta_campaign_partial')
            ->assertJsonPath('data.approval.publish_state.operator_guidance', 'Meta kampanyasi olusmus olabilir. Ads Manager uzerinde meta_campaign_partial kaydini manuel kontrol etmeden tekrar publish denemeyin.');
    }

    public function test_approvals_index_filters_publish_failed_operations_by_cleanup_and_manual_check_state(): void
    {
        [$workspaceId, $token, , $requiredApproval] = $this->seedFailedPublishFixture();
        [, , , $completedApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Manuel kontrol tamamlandi',
            'meta_campaign_id' => 'meta_campaign_completed',
            'manual_check' => [
                'completed' => true,
                'completed_at' => now()->subMinute()->toIso8601String(),
                'completed_by' => 'user-completed',
                'note' => 'Meta kontrol edildi.',
            ],
        ]);
        [, , , $cleanupSuccessApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Cleanup basarili',
            'meta_campaign_id' => 'meta_campaign_cleanup_success',
            'cleanup' => [
                'attempted' => true,
                'success' => true,
                'deleted_campaign_id' => 'meta_campaign_cleanup_success',
                'message' => 'Deleted.',
            ],
        ]);

        $requiredResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals?status=publish_failed&cleanup_state=failed&manual_check_state=required');

        $requiredResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $requiredApproval->id);

        $completedResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals?status=publish_failed&manual_check_state=completed');

        $completedResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $completedApproval->id)
            ->assertJsonPath('data.data.0.publish_state.manual_check_completed', true);

        $retryReadyResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals?status=publish_failed&recommended_action_code=retry_publish_after_manual_check');

        $retryReadyResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $completedApproval->id)
            ->assertJsonPath('data.data.0.publish_state.recommended_action_code', 'retry_publish_after_manual_check');

        $cleanupSuccessResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals?status=publish_failed&cleanup_state=successful');

        $cleanupSuccessResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $cleanupSuccessApproval->id);
    }

    public function test_manual_check_completion_marks_publish_state_as_retry_ready(): void
    {
        [$workspaceId, , $draft, $approval] = $this->seedFailedPublishFixture();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'agency.admin@adscast.test',
            'password' => 'Password123!',
            'device_name' => 'phpunit-manual-check',
        ]);

        $token = $loginResponse->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson("/api/v1/approvals/{$approval->id}/manual-check-completed", [
                'note' => 'Meta tarafinda kampanya kaydi kontrol edildi.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $approval->id)
            ->assertJsonPath('data.publish_state.manual_check_required', false)
            ->assertJsonPath('data.publish_state.manual_check_completed', true)
            ->assertJsonPath('data.publish_state.manual_check_note', 'Meta tarafinda kampanya kaydi kontrol edildi.')
            ->assertJsonPath('data.publish_state.recommended_action_code', 'retry_publish_after_manual_check');

        $detailResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson("/api/v1/drafts/{$draft->id}");

        $detailResponse->assertOk()
            ->assertJsonPath('data.approval.publish_state.manual_check_completed', true)
            ->assertJsonPath('data.approval.publish_state.manual_check_note', 'Meta tarafinda kampanya kaydi kontrol edildi.')
            ->assertJsonPath('data.approval.publish_state.operator_guidance', 'Manuel kontrol tamamlandi. Gerekli duzeltmeleri yaptiysaniz publish islemini tekrar deneyebilirsiniz.');
    }

    public function test_approvals_remediation_analytics_endpoint_summarizes_clusters(): void
    {
        [$workspaceId, $token, , $requiredApproval] = $this->seedFailedPublishFixture();
        [, , , $retryReadyApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Retry hazir analytics',
            'meta_campaign_id' => 'meta_campaign_retry_ready',
            'manual_check' => [
                'completed' => true,
                'completed_at' => now()->subMinutes(5)->toIso8601String(),
                'completed_by' => 'analytics-user',
                'note' => 'Kontrol tamam.',
            ],
        ]);

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'approval_manual_check_completed',
            'target_type' => 'approval',
            'target_id' => $requiredApproval->id,
            'metadata' => [
                'remediation_context' => [
                    'cluster_key' => 'manual-check-required',
                    'recommended_action_code' => 'manual_meta_check',
                    'next_cluster_key' => 'retry-ready',
                    'next_recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subMinutes(4),
        ]);

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $retryReadyApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'retry-ready',
                    'recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subMinutes(3),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals/remediation-analytics');

        $response->assertOk()
            ->assertJsonPath('data.summary.tracked_clusters', 4)
            ->assertJsonPath('data.summary.current_publish_failed', 2)
            ->assertJsonPath('data.summary.retry_ready_items', 1)
            ->assertJsonPath('data.summary.tracked_manual_checks', 1)
            ->assertJsonPath('data.summary.successful_publish_attempts', 1)
            ->assertJsonPath('data.summary.featured_cluster_label', 'Manuel Kontrol Bekleyenler')
            ->assertJsonPath('data.featured_recommendation.cluster_key', 'manual-check-required')
            ->assertJsonPath('data.featured_recommendation.decision_status', 'manual_attention')
            ->assertJsonPath('data.featured_recommendation.retry_guidance_status', 'blocked')
            ->assertJsonPath('data.featured_recommendation.safe_bulk_retry', false)
            ->assertJsonPath('data.featured_recommendation.action_mode', 'focus_cluster')
            ->assertJsonPath('data.items.0.cluster_key', 'retry-ready')
            ->assertJsonPath('data.items.0.successful_publishes', 1)
            ->assertJsonPath('data.items.0.publish_success_rate', 100)
            ->assertJsonPath('data.items.0.retry_guidance_status', 'guarded')
            ->assertJsonPath('data.items.0.safe_bulk_retry', false);
    }

    public function test_approvals_remediation_analytics_prefers_retry_ready_cluster_when_manual_check_queue_is_clear(): void
    {
        [$workspaceId, $token, , $retryReadyApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Retry hazir featured',
            'meta_campaign_id' => 'meta_campaign_retry_only',
            'manual_check' => [
                'completed' => true,
                'completed_at' => now()->subMinutes(5)->toIso8601String(),
                'completed_by' => 'analytics-user',
                'note' => 'Kontrol tamam.',
            ],
        ]);

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $retryReadyApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'retry-ready',
                    'recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subMinutes(2),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals/remediation-analytics');

        $response->assertOk()
            ->assertJsonPath('data.summary.featured_cluster_label', "Tekrar Publish'e Hazir")
            ->assertJsonPath('data.featured_recommendation.cluster_key', 'retry-ready')
            ->assertJsonPath('data.featured_recommendation.decision_status', 'effectiveness_preferred')
            ->assertJsonPath('data.featured_recommendation.retry_guidance_status', 'guarded')
            ->assertJsonPath('data.featured_recommendation.safe_bulk_retry', false)
            ->assertJsonPath('data.featured_recommendation.action_mode', 'focus_cluster');
    }

    public function test_approvals_remediation_analytics_supports_selectable_windows_and_effectiveness_fields(): void
    {
        [$workspaceId, $token, , $retryReadyApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Retry hazir window analytics',
            'meta_campaign_id' => 'meta_campaign_window_retry',
            'manual_check' => [
                'completed' => true,
                'completed_at' => now()->subMinutes(5)->toIso8601String(),
                'completed_by' => 'analytics-user',
                'note' => 'Kontrol tamam.',
            ],
        ]);

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $retryReadyApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'retry-ready',
                    'recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subDays(45),
        ]);

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $retryReadyApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'retry-ready',
                    'recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subDays(2),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals/remediation-analytics?window_days=7');

        $response->assertOk()
            ->assertJsonPath('data.summary.window_days', 7)
            ->assertJsonPath('data.summary.tracked_publish_attempts', 1)
            ->assertJsonPath('data.summary.top_effective_cluster_label', "Tekrar Publish'e Hazir")
            ->assertJsonPath('data.summary.top_effective_cluster_score', 77)
            ->assertJsonPath('data.featured_recommendation.decision_status', 'effectiveness_preferred')
            ->assertJsonPath('data.featured_recommendation.retry_guidance_status', 'guarded')
            ->assertJsonPath('data.featured_recommendation.safe_bulk_retry', false)
            ->assertJsonPath('data.featured_recommendation.effectiveness_score', 77)
            ->assertJsonPath('data.featured_recommendation.effectiveness_status', 'proven')
            ->assertJsonPath('data.items.0.effectiveness_score', 77)
            ->assertJsonPath('data.items.0.effectiveness_status', 'proven');
    }

    public function test_featured_recommendation_prefers_long_term_stable_cluster_when_current_window_is_sparse(): void
    {
        [$workspaceId, $token, , $retryReadyApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Sparse current retry-ready',
            'meta_campaign_id' => 'meta_campaign_sparse_current',
            'manual_check' => [
                'completed' => true,
                'completed_at' => now()->subMinutes(5)->toIso8601String(),
                'completed_by' => 'analytics-user',
                'note' => 'Kontrol tamam.',
            ],
        ]);

        [, , , $cleanupRecoveredApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Long term cleanup recovered leader',
            'meta_campaign_id' => 'meta_campaign_long_term_leader',
            'cleanup' => [
                'attempted' => true,
                'success' => true,
                'deleted_campaign_id' => 'meta_campaign_long_term_leader',
                'message' => 'Deleted.',
            ],
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/approvals/remediation-analytics/track', [
                'featured_cluster_key' => 'cleanup-recovered',
                'acted_cluster_key' => 'cleanup-recovered',
                'interaction_type' => 'bulk_retry_publish',
                'followed_featured' => true,
                'attempted_count' => 2,
                'success_count' => 2,
                'failure_count' => 0,
                'interaction_source' => 'draft_detail_from_approvals_featured',
            ])
            ->assertOk();

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $cleanupRecoveredApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'cleanup-recovered',
                    'recommended_action_code' => 'fix_and_retry_publish',
                ],
            ],
            'occurred_at' => now()->subDays(45),
        ]);

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $cleanupRecoveredApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'cleanup-recovered',
                    'recommended_action_code' => 'fix_and_retry_publish',
                ],
            ],
            'occurred_at' => now()->subDays(44),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals/remediation-analytics?window_days=7');

        $response->assertOk()
            ->assertJsonPath('data.summary.window_days', 7)
            ->assertJsonPath('data.summary.long_term_window_days', 90)
            ->assertJsonPath('data.summary.top_long_term_stable_cluster_label', 'Cleanup Ile Temizlenenler')
            ->assertJsonPath('data.summary.top_long_term_stable_cluster_score', 97)
            ->assertJsonPath('data.summary.top_long_term_route_key', 'draft_detail')
            ->assertJsonPath('data.summary.top_long_term_route_label', 'Draft Detail')
            ->assertJsonPath('data.long_term_draft_detail_outcome_summary.tracked_interactions', 1)
            ->assertJsonPath('data.long_term_draft_detail_outcome_summary.publish_success_rate', 100)
            ->assertJsonPath('data.long_term_route_trends.0.route_key', 'draft_detail')
            ->assertJsonPath('data.long_term_route_trends.0.top_source_label', 'Draft Detay / Featured Kart')
            ->assertJsonPath('data.featured_recommendation.cluster_key', 'cleanup-recovered')
            ->assertJsonPath('data.featured_recommendation.decision_status', 'long_term_preferred')
            ->assertJsonPath('data.featured_recommendation.decision_context_source', 'long_term')
            ->assertJsonPath('data.featured_recommendation.decision_context_window_days', 90)
            ->assertJsonPath('data.featured_recommendation.decision_context_success_rate', 100)
            ->assertJsonPath('data.featured_recommendation.safe_bulk_retry', true)
            ->assertJsonPath('data.featured_recommendation.action_mode', 'bulk_retry_publish')
            ->assertJsonPath('data.items.1.long_term_publish_success_rate', 100)
            ->assertJsonPath('data.items.1.long_term_retry_guidance_status', 'safe')
            ->assertJsonPath('data.items.1.long_term_safe_bulk_retry', true);
    }

    public function test_featured_remediation_tracking_updates_follow_and_success_metrics(): void
    {
        [$workspaceId, $token] = $this->seedFailedPublishFixture([
            'product_service' => 'Retry hazir tracking',
            'meta_campaign_id' => 'meta_campaign_retry_tracking',
            'manual_check' => [
                'completed' => true,
                'completed_at' => now()->subMinutes(5)->toIso8601String(),
                'completed_by' => 'analytics-user',
                'note' => 'Kontrol tamam.',
            ],
        ]);

        $followedResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/approvals/remediation-analytics/track', [
                'featured_cluster_key' => 'retry-ready',
                'acted_cluster_key' => 'retry-ready',
                'interaction_type' => 'bulk_retry_publish',
                'followed_featured' => true,
                'attempted_count' => 2,
                'success_count' => 1,
                'failure_count' => 1,
                'interaction_source' => 'draft_detail',
            ]);

        $followedResponse->assertOk();

        $overrideResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/approvals/remediation-analytics/track', [
                'featured_cluster_key' => 'retry-ready',
                'acted_cluster_key' => 'review-error',
                'interaction_type' => 'focus_cluster',
                'followed_featured' => false,
                'attempted_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'interaction_source' => 'approvals_cluster',
            ]);

        $overrideResponse->assertOk();

        $manualCheckResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/approvals/remediation-analytics/track', [
                'featured_cluster_key' => 'manual-check-required',
                'acted_cluster_key' => 'manual-check-required',
                'interaction_type' => 'manual_check_completed',
                'followed_featured' => true,
                'attempted_count' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'interaction_source' => 'approvals_item',
            ]);

        $manualCheckResponse->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals/remediation-analytics');

        $response->assertOk()
            ->assertJsonPath('data.summary.tracked_featured_interactions', 3)
            ->assertJsonPath('data.summary.followed_featured_interactions', 2)
            ->assertJsonPath('data.summary.override_featured_interactions', 1)
            ->assertJsonPath('data.summary.featured_publish_attempts', 2)
            ->assertJsonPath('data.summary.successful_featured_publishes', 1)
            ->assertJsonPath('data.summary.featured_follow_rate', 66.7)
            ->assertJsonPath('data.summary.featured_publish_success_rate', 50)
            ->assertJsonPath('data.summary.tracked_sources_count', 3)
            ->assertJsonPath('data.summary.top_interaction_source_key', 'draft_detail')
            ->assertJsonPath('data.summary.top_interaction_source_label', 'Draft Detay')
            ->assertJsonPath('data.summary.top_route_key', 'draft_detail')
            ->assertJsonPath('data.summary.top_route_label', 'Draft Detail')
            ->assertJsonPath('data.summary.top_route_source_key', 'draft_detail')
            ->assertJsonPath('data.summary.top_route_source_label', 'Draft Detay')
            ->assertJsonPath('data.summary.top_route_publish_success_rate', 50)
            ->assertJsonPath('data.summary.top_draft_detail_cluster_label', "Tekrar Publish'e Hazir")
            ->assertJsonPath('data.approvals_native_outcome_summary.tracked_interactions', 2)
            ->assertJsonPath('data.approvals_native_outcome_summary.manual_check_completions', 1)
            ->assertJsonPath('data.approvals_native_outcome_summary.focus_actions', 1)
            ->assertJsonPath('data.approvals_native_outcome_summary.publish_success_rate', null)
            ->assertJsonPath('data.draft_detail_outcome_summary.tracked_interactions', 1)
            ->assertJsonPath('data.draft_detail_outcome_summary.publish_success_rate', 50)
            ->assertJsonPath('data.featured_recommendation.featured_interactions', 2)
            ->assertJsonPath('data.featured_recommendation.featured_followed_interactions', 1)
            ->assertJsonPath('data.featured_recommendation.featured_override_interactions', 1)
            ->assertJsonPath('data.featured_recommendation.featured_publish_attempts', 2)
            ->assertJsonPath('data.featured_recommendation.featured_publish_success_rate', 50)
            ->assertJsonPath('data.featured_recommendation.retry_guidance_status', 'guarded')
            ->assertJsonPath('data.featured_recommendation.safe_bulk_retry', false)
            ->assertJsonPath('data.outcome_chain_summary.manual_check_completions', 1)
            ->assertJsonPath('data.outcome_chain_summary.bulk_retry_actions', 1)
            ->assertJsonPath('data.outcome_chain_summary.total_retry_actions', 1)
            ->assertJsonPath('data.outcome_chain_summary.focus_actions', 1)
            ->assertJsonPath('data.outcome_chain_summary.publish_attempts', 2)
            ->assertJsonPath('data.outcome_chain_summary.successful_publishes', 1)
            ->assertJsonPath('data.outcome_chain_summary.failed_publishes', 1)
            ->assertJsonFragment([
                'source_key' => 'draft_detail',
                'label' => 'Draft Detay',
                'tracked_interactions' => 1,
                'publish_attempts' => 2,
                'successful_publishes' => 1,
                'failed_publishes' => 1,
            ])
            ->assertJsonFragment([
                'source_key' => 'approvals_item',
                'label' => 'Approval Satiri',
                'tracked_interactions' => 1,
                'manual_check_completions' => 1,
            ])
            ->assertJsonPath('data.items.0.top_interaction_source_key', 'draft_detail')
            ->assertJsonPath('data.items.0.top_interaction_source_label', 'Draft Detay')
            ->assertJsonPath('data.route_trends.0.route_key', 'draft_detail')
            ->assertJsonPath('data.route_trends.0.top_source_label', 'Draft Detay')
            ->assertJsonPath('data.items.0.outcome_chain_summary.bulk_retry_actions', 1)
            ->assertJsonPath('data.items.0.outcome_chain_summary.publish_attempts', 2)
            ->assertJsonPath('data.items.0.draft_detail_outcome_summary.publish_attempts', 2)
            ->assertJsonPath('data.items.0.draft_detail_outcome_summary.top_source_label', 'Draft Detay');
    }

    public function test_featured_recommendation_prefers_draft_detail_outcome_when_it_outperforms_approvals_native_flow(): void
    {
        [$workspaceId, $token, $retryReadyDraft, $retryReadyApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Retry hazir draft detail leader',
            'meta_campaign_id' => 'meta_campaign_retry_detail_leader',
            'manual_check' => [
                'completed' => true,
                'completed_at' => now()->subMinutes(5)->toIso8601String(),
                'completed_by' => 'analytics-user',
                'note' => 'Kontrol tamam.',
            ],
        ]);

        [, , , $cleanupRecoveredApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Cleanup recovered comparison',
            'meta_campaign_id' => 'meta_campaign_cleanup_recovered',
            'cleanup' => [
                'attempted' => true,
                'success' => true,
                'deleted_campaign_id' => 'meta_campaign_cleanup_recovered',
                'message' => 'Deleted.',
            ],
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/approvals/remediation-analytics/track', [
                'featured_cluster_key' => 'retry-ready',
                'acted_cluster_key' => 'retry-ready',
                'interaction_type' => 'publish_retry',
                'followed_featured' => true,
                'attempted_count' => 2,
                'success_count' => 2,
                'failure_count' => 0,
                'interaction_source' => 'draft_detail_from_approvals_featured',
            ])
            ->assertOk();

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $retryReadyApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'retry-ready',
                    'recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subMinutes(4),
        ]);

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $retryReadyApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'retry-ready',
                    'recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subMinutes(3),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/approvals/remediation-analytics/track', [
                'featured_cluster_key' => 'cleanup-recovered',
                'acted_cluster_key' => 'cleanup-recovered',
                'interaction_type' => 'bulk_retry_publish',
                'followed_featured' => true,
                'attempted_count' => 2,
                'success_count' => 1,
                'failure_count' => 1,
                'interaction_source' => 'approvals_cluster',
            ])
            ->assertOk();

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $cleanupRecoveredApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'cleanup-recovered',
                    'recommended_action_code' => 'fix_and_retry_publish',
                ],
            ],
            'occurred_at' => now()->subMinutes(2),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals/remediation-analytics');

        $response->assertOk()
            ->assertJsonPath('data.summary.top_draft_detail_cluster_label', "Tekrar Publish'e Hazir")
            ->assertJsonPath('data.draft_detail_outcome_summary.publish_success_rate', 100)
            ->assertJsonPath('data.approvals_native_outcome_summary.publish_success_rate', 50)
            ->assertJsonPath('data.featured_recommendation.cluster_key', 'retry-ready')
            ->assertJsonPath('data.featured_recommendation.decision_status', 'draft_detail_preferred')
            ->assertJsonPath('data.featured_recommendation.decision_context_source', 'draft_detail')
            ->assertJsonPath('data.featured_recommendation.decision_context_success_rate', 100)
            ->assertJsonPath('data.featured_recommendation.decision_context_advantage', 50)
            ->assertJsonPath('data.featured_recommendation.retry_guidance_status', 'safe')
            ->assertJsonPath('data.featured_recommendation.safe_bulk_retry', true)
            ->assertJsonPath('data.featured_recommendation.action_mode', 'bulk_retry_publish')
            ->assertJsonPath('data.featured_recommendation.primary_action.mode', 'jump_to_item')
            ->assertJsonPath('data.featured_recommendation.primary_action.route_key', 'draft_detail')
            ->assertJsonPath('data.featured_recommendation.primary_action.confidence_status', 'proven')
            ->assertJsonPath('data.featured_recommendation.primary_action.tracked_interactions', 1)
            ->assertJsonPath('data.featured_recommendation.primary_action.alternative_route_label', null)
            ->assertJsonPath('data.featured_recommendation.primary_action.route', sprintf('/drafts/detail?id=%s', $retryReadyDraft->id))
            ->assertJsonPath('data.items.0.retry_guidance_status', 'safe')
            ->assertJsonPath('data.items.0.safe_bulk_retry', true);
    }

    public function test_featured_primary_action_stays_cluster_focused_when_approvals_native_outperforms_draft_detail(): void
    {
        [$workspaceId, $token, , $retryReadyApproval] = $this->seedFailedPublishFixture([
            'product_service' => 'Retry hazir approvals native leader',
            'meta_campaign_id' => 'meta_campaign_retry_native_leader',
            'manual_check' => [
                'completed' => true,
                'completed_at' => now()->subMinutes(5)->toIso8601String(),
                'completed_by' => 'analytics-user',
                'note' => 'Kontrol tamam.',
            ],
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/approvals/remediation-analytics/track', [
                'featured_cluster_key' => 'retry-ready',
                'acted_cluster_key' => 'retry-ready',
                'interaction_type' => 'bulk_retry_publish',
                'followed_featured' => true,
                'attempted_count' => 2,
                'success_count' => 2,
                'failure_count' => 0,
                'interaction_source' => 'approvals_featured',
            ])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/approvals/remediation-analytics/track', [
                'featured_cluster_key' => 'retry-ready',
                'acted_cluster_key' => 'retry-ready',
                'interaction_type' => 'jump_to_item',
                'followed_featured' => false,
                'attempted_count' => 1,
                'success_count' => 0,
                'failure_count' => 1,
                'interaction_source' => 'draft_detail_from_approvals_featured',
            ])
            ->assertOk();

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $retryReadyApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'retry-ready',
                    'recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subMinutes(3),
        ]);

        AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'action' => 'publish_attempted',
            'target_type' => 'approval',
            'target_id' => $retryReadyApproval->id,
            'metadata' => [
                'success' => true,
                'remediation_context' => [
                    'cluster_key' => 'retry-ready',
                    'recommended_action_code' => 'retry_publish_after_manual_check',
                ],
            ],
            'occurred_at' => now()->subMinutes(2),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/approvals/remediation-analytics');

        $response->assertOk()
            ->assertJsonPath('data.summary.top_route_key', 'approvals')
            ->assertJsonPath('data.featured_recommendation.cluster_key', 'retry-ready')
            ->assertJsonPath('data.featured_recommendation.primary_action.mode', 'focus_cluster')
            ->assertJsonPath('data.featured_recommendation.primary_action.route_key', 'approvals')
            ->assertJsonPath('data.featured_recommendation.primary_action.confidence_status', 'guarded')
            ->assertJsonPath(
                'data.featured_recommendation.primary_action.route',
                '/approvals?status=publish_failed&recommended_action_code=retry_publish_after_manual_check',
            );
    }

    /**
     * @return array{0: string, 1: string, 2: CampaignDraft, 3: Approval}
     */
    private function seedFailedPublishFixture(array $overrides = []): array
    {
        if (! User::query()->where('email', 'account.manager@adscast.test')->exists()) {
            $this->seed([
                RolePermissionSeeder::class,
                TenantSeeder::class,
            ]);
        }

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'account.manager@adscast.test',
            'password' => 'Password123!',
            'device_name' => 'phpunit',
        ]);

        $token = $loginResponse->json('token');
        $workspaceId = Workspace::query()
            ->where('slug', 'operations-main')
            ->value('id');

        $metaCampaignId = $overrides['meta_campaign_id'] ?? 'meta_campaign_partial';
        $publishMetadata = [
            'success' => false,
            'status' => 'error',
            'message' => 'Meta API hatasi: Invalid targeting payload.',
            'meta_reference' => [
                'campaign_id' => $metaCampaignId,
                'ad_set_id' => null,
            ],
            'cleanup' => $overrides['cleanup'] ?? [
                'attempted' => true,
                'success' => false,
                'deleted_campaign_id' => $metaCampaignId,
                'message' => 'Delete failed.',
            ],
        ];

        if (array_key_exists('manual_check', $overrides)) {
            $publishMetadata['manual_check'] = $overrides['manual_check'];
        }

        $draft = CampaignDraft::factory()->create([
            'workspace_id' => $workspaceId,
            'objective' => 'LEADS',
            'product_service' => $overrides['product_service'] ?? 'Meta cleanup testi',
            'status' => 'publish_failed',
            'publish_response_metadata' => $publishMetadata,
        ]);

        $approval = Approval::query()->create([
            'workspace_id' => $workspaceId,
            'approvable_type' => CampaignDraft::class,
            'approvable_id' => $draft->id,
            'status' => 'publish_failed',
            'publish_response_metadata' => $draft->publish_response_metadata,
            'created_by' => $draft->created_by,
            'submitted_at' => now()->subMinutes(10),
            'approved_at' => now()->subMinutes(5),
        ]);

        return [$workspaceId, $token, $draft->fresh(['approval']), $approval];
    }
}
