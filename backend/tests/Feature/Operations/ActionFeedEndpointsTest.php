<?php

namespace Tests\Feature\Operations;

use App\Models\Alert;
use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\Recommendation;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActionFeedEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_index_returns_entity_groups_and_next_best_actions(): void
    {
        [$workspace, $token, $campaign] = $this->seedFeedFixture();

        Alert::factory()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'campaign',
            'entity_id' => $campaign->id,
            'code' => 'spend_no_result',
            'severity' => 'high',
            'summary' => 'Lead Engine Campaign sonuc vermeden harciyor.',
            'explanation' => 'Kampanya sonuc getirmediginde butce dogrudan verimsiz tukenir.',
            'recommended_action' => 'Kampanyayi yaratıcı ve hedefleme bazinda inceleyin.',
            'status' => 'open',
            'date_detected' => '2026-03-14',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/alerts');

        $response->assertOk()
            ->assertJsonPath('summary.open_total', 1)
            ->assertJsonPath('summary.critical_total', 1)
            ->assertJsonPath('entity_groups.0.entity_type', 'campaign')
            ->assertJsonPath('entity_groups.0.items.0.entity_label', 'Lead Engine Campaign')
            ->assertJsonPath('entity_groups.0.items.0.impact_summary', 'Kampanya sonuc getirmediginde butce dogrudan verimsiz tukenir.')
            ->assertJsonPath('next_best_actions.0.source', 'alert')
            ->assertJsonPath('next_best_actions.0.route', '/campaigns/detail?id='.$campaign->id)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        [
                            'entity_type',
                            'entity_label',
                            'context_label',
                            'why_it_matters',
                            'next_step',
                            'route',
                        ],
                    ],
                ],
                'summary',
                'entity_groups',
                'next_best_actions',
            ]);
    }

    public function test_recommendation_index_returns_operator_and_client_views(): void
    {
        [$workspace, $token, $campaign] = $this->seedFeedFixture();

        Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'campaign',
            'target_id' => $campaign->id,
            'summary' => 'Kazanan kampanyada kontrollu butce artisi deneyin.',
            'details' => 'Butceyi gunde %10 artisla ilerletin.',
            'action_type' => 'ai_guidance',
            'priority' => 'high',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => '2026-03-15 10:00:00',
            'metadata' => [
                'client_friendly_summary' => 'Kampanya olumlu sinyal veriyor; kontrollu buyutme denenebilir.',
                'operator_notes' => 'Butce artisina gecmeden once frekans trendini kontrol edin.',
                'what_to_test_next' => 'Yeni teklif acisi ile ikinci kreatif varyasyonu acin.',
                'budget_note' => 'Butceyi bir anda degil kademeli artirin.',
                'creative_note' => 'Creative rotation ekleyin.',
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/recommendations');

        $response->assertOk()
            ->assertJsonPath('summary.open_total', 1)
            ->assertJsonPath('summary.high_priority_total', 1)
            ->assertJsonPath('entity_groups.0.entity_type', 'campaign')
            ->assertJsonPath('entity_groups.0.items.0.entity_label', 'Lead Engine Campaign')
            ->assertJsonPath('entity_groups.0.items.0.operator_view.summary', 'Butce artisina gecmeden once frekans trendini kontrol edin.')
            ->assertJsonPath('entity_groups.0.items.0.client_view.summary', 'Kampanya olumlu sinyal veriyor; kontrollu buyutme denenebilir.')
            ->assertJsonPath('entity_groups.0.items.0.action_status.label', 'Bekliyor')
            ->assertJsonPath('next_best_actions.0.source', 'recommendation')
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        [
                            'entity_type',
                            'entity_label',
                            'operator_view',
                            'client_view',
                            'action_status',
                            'route',
                        ],
                    ],
                ],
                'summary',
                'entity_groups',
                'next_best_actions',
            ]);
    }

    /**
     * @return array{0: Workspace, 1: string, 2: Campaign}
     */
    private function seedFeedFixture(): array
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
        $workspace = Workspace::query()->findOrFail($workspaceId);

        $connection = MetaConnection::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $account = MetaAdAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_feed',
            'name' => 'Feed Account',
            'status' => 'active',
            'is_active' => true,
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_feed',
            'name' => 'Lead Engine Campaign',
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$workspace, $token, $campaign];
    }
}
