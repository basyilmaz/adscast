<?php

namespace Tests\Feature\Reporting;

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

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_overview_returns_operational_summary_blocks(): void
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
            'last_synced_at' => '2026-03-07 10:30:00',
        ]);

        $primaryAccount = MetaAdAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_1111111',
            'name' => 'Castintech Performance',
            'status' => 'active',
            'is_active' => true,
            'last_synced_at' => '2026-03-07 10:30:00',
        ]);

        $secondaryAccount = MetaAdAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_2222222',
            'name' => 'Castintech Legacy',
            'status' => 'restricted',
            'is_active' => false,
            'last_synced_at' => '2026-03-06 09:00:00',
        ]);

        $winnerCampaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $primaryAccount->id,
            'meta_campaign_id' => 'cmp_winner',
            'name' => 'Winner Campaign',
            'status' => 'active',
            'is_active' => true,
        ]);

        $watchCampaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $primaryAccount->id,
            'meta_campaign_id' => 'cmp_watch',
            'name' => 'Watch Campaign',
            'status' => 'active',
            'is_active' => true,
        ]);

        Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $secondaryAccount->id,
            'meta_campaign_id' => 'cmp_paused',
            'name' => 'Paused Campaign',
            'status' => 'paused',
            'is_active' => false,
        ]);

        foreach ([
            ['date' => '2026-03-03', 'campaign' => $winnerCampaign, 'spend' => 300, 'results' => 10, 'ctr' => 3.4, 'cpm' => 24.5, 'frequency' => 1.2],
            ['date' => '2026-03-04', 'campaign' => $watchCampaign, 'spend' => 250, 'results' => 0, 'ctr' => 1.1, 'cpm' => 40.2, 'frequency' => 2.4],
        ] as $item) {
            \App\Models\InsightDaily::factory()->create([
                'workspace_id' => $workspace->id,
                'level' => 'campaign',
                'entity_external_id' => $item['campaign']->meta_campaign_id,
                'date' => $item['date'],
                'spend' => $item['spend'],
                'results' => $item['results'],
                'leads' => $item['results'],
                'conversions' => $item['results'],
                'cost_per_result' => $item['results'] > 0 ? round($item['spend'] / $item['results'], 4) : null,
                'ctr' => $item['ctr'],
                'cpm' => $item['cpm'],
                'frequency' => $item['frequency'],
                'source' => 'meta',
            ]);
        }

        Alert::factory()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'campaign',
            'entity_id' => $watchCampaign->id,
            'severity' => 'high',
            'summary' => 'Harcama var ancak sonuc yok.',
            'recommended_action' => 'Kreatif ve hedef kitleyi hemen gozden gecirin.',
            'status' => 'open',
            'date_detected' => '2026-03-05',
        ]);

        Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
            'summary' => 'Kazanan kampanyada kontrollu butce artisi deneyin.',
            'details' => 'Sonuc ureten kampanyalarda kademeli butce artisi denenebilir.',
            'action_type' => 'ai_guidance',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => '2026-03-06 14:00:00',
            'metadata' => ['source' => 'phpunit'],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/dashboard/overview?start_date=2026-03-01&end_date=2026-03-07');

        $response->assertOk()
            ->assertJsonPath('data.metrics.total_spend', 550)
            ->assertJsonPath('data.metrics.total_results', 10)
            ->assertJsonPath('data.workspace_health.active_accounts', 1)
            ->assertJsonPath('data.workspace_health.active_campaigns', 2)
            ->assertJsonPath('data.workspace_health.campaigns_requiring_attention', 1)
            ->assertJsonPath('data.account_health.0.name', 'Castintech Performance')
            ->assertJsonPath('data.account_health.0.open_alerts', 1)
            ->assertJsonPath('data.active_campaigns.0.name', 'Watch Campaign')
            ->assertJsonPath('data.active_campaigns.0.open_alerts', 1)
            ->assertJsonPath('data.urgent_actions.0.source', 'alert')
            ->assertJsonPath('data.urgent_actions.1.source', 'recommendation')
            ->assertJsonPath('data.trend.0.date', '2026-03-01')
            ->assertJsonCount(7, 'data.trend')
            ->assertJsonStructure([
                'data' => [
                    'workspace_health' => [
                        'summary',
                        'active_accounts',
                        'total_accounts',
                        'active_campaigns',
                        'campaigns_requiring_attention',
                        'open_alerts',
                        'open_recommendations',
                        'last_synced_at',
                    ],
                    'account_health',
                    'urgent_actions',
                    'active_campaigns',
                    'trend',
                ],
            ]);
    }
}
