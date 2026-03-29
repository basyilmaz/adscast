<?php

namespace Tests\Feature\Approvals;

use App\Models\Approval;
use App\Models\CampaignDraft;
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

    /**
     * @return array{0: string, 1: string, 2: CampaignDraft, 3: Approval}
     */
    private function seedFailedPublishFixture(): array
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'account.manager@adscast.test',
            'password' => 'Password123!',
            'device_name' => 'phpunit',
        ]);

        $token = $loginResponse->json('token');
        $workspaceId = $loginResponse->json('workspaces.0.id');

        $draft = CampaignDraft::factory()->create([
            'workspace_id' => $workspaceId,
            'objective' => 'LEADS',
            'product_service' => 'Meta cleanup testi',
            'status' => 'publish_failed',
            'publish_response_metadata' => [
                'success' => false,
                'status' => 'error',
                'message' => 'Meta API hatasi: Invalid targeting payload.',
                'meta_reference' => [
                    'campaign_id' => 'meta_campaign_partial',
                    'ad_set_id' => null,
                ],
                'cleanup' => [
                    'attempted' => true,
                    'success' => false,
                    'deleted_campaign_id' => 'meta_campaign_partial',
                    'message' => 'Delete failed.',
                ],
            ],
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
