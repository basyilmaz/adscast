<?php

namespace Tests\Feature\Reporting;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\Recommendation;
use App\Models\Setting;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdAccountReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ad_account_list_returns_operational_summary_fields(): void
    {
        [$workspace, $token] = $this->bootstrapWorkspaceContext();

        $connection = MetaConnection::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $activeAccount = MetaAdAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_123',
            'name' => 'Castintech Growth',
            'status' => 'active',
            'is_active' => true,
            'last_synced_at' => now()->subHours(4),
        ]);

        MetaAdAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_999',
            'name' => 'Legacy Restricted',
            'status' => 'restricted',
            'is_active' => false,
            'last_synced_at' => now()->subDays(5),
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $activeAccount->id,
            'meta_campaign_id' => 'cmp_123',
            'name' => 'Lead Push',
            'status' => 'active',
            'is_active' => true,
        ]);

        \App\Models\InsightDaily::factory()->create([
            'workspace_id' => $workspace->id,
            'level' => 'campaign',
            'entity_external_id' => 'cmp_123',
            'date' => '2026-03-10',
            'spend' => 300,
            'results' => 12,
            'leads' => 12,
            'conversions' => 12,
            'cost_per_result' => 25,
            'ctr' => 2.4,
            'cpm' => 21.5,
            'frequency' => 1.3,
            'source' => 'meta',
        ]);

        Alert::factory()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'campaign',
            'entity_id' => $campaign->id,
            'severity' => 'high',
            'summary' => 'Hesap icindeki bir kampanya dikkat istiyor.',
            'status' => 'open',
            'date_detected' => '2026-03-11',
        ]);

        Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'campaign',
            'target_id' => $campaign->id,
            'summary' => 'Kazanan kreatifi ayirip ikinci varyasyon test edin.',
            'details' => 'Hesap bazli performansi korumak icin kreatif varyasyon planlayin.',
            'action_type' => 'ai_guidance',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => '2026-03-12 09:00:00',
            'metadata' => ['source' => 'phpunit'],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/meta/ad-accounts?start_date=2026-03-10&end_date=2026-03-16');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_accounts', 2)
            ->assertJsonPath('data.summary.active_accounts', 1)
            ->assertJsonPath('data.summary.accounts_requiring_attention', 2)
            ->assertJsonPath('data.summary.total_spend', 300)
            ->assertJsonPath('data.data.0.name', 'Castintech Growth')
            ->assertJsonPath('data.data.0.active_campaigns', 1)
            ->assertJsonPath('data.data.0.open_alerts', 1)
            ->assertJsonPath('data.data.0.open_recommendations', 1)
            ->assertJsonPath('data.data.0.sync_status', 'fresh')
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        [
                            'id',
                            'account_id',
                            'name',
                            'sync_status',
                            'active_campaigns',
                            'open_alerts',
                            'open_recommendations',
                            'health_status',
                            'health_summary',
                        ],
                    ],
                    'summary' => [
                        'total_accounts',
                        'active_accounts',
                        'accounts_requiring_attention',
                    ],
                    'range',
                ],
            ]);
    }

    public function test_ad_account_detail_returns_scoped_campaign_alert_and_report_data(): void
    {
        [$workspace, $token] = $this->bootstrapWorkspaceContext();

        $connection = MetaConnection::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $account = MetaAdAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_777',
            'name' => 'Castintech Main Account',
            'status' => 'active',
            'is_active' => true,
            'last_synced_at' => now()->subHours(3),
        ]);

        $winnerCampaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_winner',
            'name' => 'Winner Campaign',
            'status' => 'active',
            'is_active' => true,
        ]);

        $watchCampaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_watch',
            'name' => 'Watch Campaign',
            'status' => 'active',
            'is_active' => true,
        ]);

        AdSet::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $winnerCampaign->id,
            'meta_ad_set_id' => 'aset_1',
            'name' => 'Lead Ad Set',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'optimization_goal' => 'LEAD_GENERATION',
            'daily_budget' => 100,
            'last_synced_at' => now(),
        ]);

        Ad::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $winnerCampaign->id,
            'ad_set_id' => AdSet::query()->firstOrFail()->id,
            'meta_ad_id' => 'ad_1',
            'name' => 'Lead Ad',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'last_synced_at' => now(),
        ]);

        foreach ([
            ['date' => '2026-03-10', 'campaign' => $winnerCampaign, 'spend' => 400, 'results' => 16, 'ctr' => 2.8, 'cpm' => 20.5, 'frequency' => 1.4],
            ['date' => '2026-03-11', 'campaign' => $watchCampaign, 'spend' => 250, 'results' => 0, 'ctr' => 1.1, 'cpm' => 34.8, 'frequency' => 2.1],
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
            'summary' => 'Watch Campaign sonuc uretmiyor.',
            'recommended_action' => 'Hedefleme ve kreatifi inceleyin.',
            'status' => 'open',
            'date_detected' => '2026-03-12',
        ]);

        Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'campaign',
            'target_id' => $winnerCampaign->id,
            'summary' => 'Kazanan kampanyada kontrollu butce artisi deneyin.',
            'details' => 'Gunde %10-15 arasi artis ile ilerleyin.',
            'action_type' => 'ai_guidance',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => '2026-03-12 10:00:00',
            'metadata' => ['source' => 'phpunit'],
        ]);

        Setting::query()->create([
            'workspace_id' => $workspace->id,
            'key' => 'reports.delivery_profiles',
            'value' => [[
                'id' => (string) Str::uuid(),
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'recipient_preset_id' => null,
                'delivery_channel' => 'email_stub',
                'cadence' => 'weekly',
                'weekday' => 4,
                'month_day' => null,
                'send_time' => '11:30',
                'timezone' => 'Europe/Istanbul',
                'default_range_days' => 14,
                'layout_preset' => 'client_digest',
                'recipients' => ['client@castintech.com', 'ops@castintech.com'],
                'share_delivery' => [
                    'enabled' => true,
                    'label_template' => '{template_name} / {end_date}',
                    'expires_in_days' => 7,
                    'allow_csv_download' => false,
                ],
                'is_active' => true,
                'created_at' => now()->subHour()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]],
            'is_encrypted' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/meta/ad-accounts/{$account->id}?start_date=2026-03-10&end_date=2026-03-16");

        $response->assertOk()
            ->assertJsonPath('data.ad_account.name', 'Castintech Main Account')
            ->assertJsonPath('data.summary.spend', 650)
            ->assertJsonPath('data.summary.results', 16)
            ->assertJsonPath('data.summary.active_campaigns', 2)
            ->assertJsonPath('data.summary.active_ad_sets', 1)
            ->assertJsonPath('data.summary.active_ads', 1)
            ->assertJsonPath('data.health.status', 'warning')
            ->assertJsonPath('data.campaigns.0.name', 'Watch Campaign')
            ->assertJsonPath('data.campaigns.0.open_alerts', 1)
            ->assertJsonPath('data.campaigns.1.open_recommendations', 1)
            ->assertJsonPath('data.alerts.0.summary', 'Watch Campaign sonuc uretmiyor.')
            ->assertJsonPath('data.recommendations.0.summary', 'Kazanan kampanyada kontrollu butce artisi deneyin.')
            ->assertJsonPath('data.next_best_actions.0.source', 'alert')
            ->assertJsonPath('data.delivery_profile.entity_type', 'account')
            ->assertJsonPath('data.delivery_profile.entity_id', $account->id)
            ->assertJsonPath('data.delivery_profile.cadence', 'weekly')
            ->assertJsonPath('data.delivery_profile.delivery_channel', 'email_stub')
            ->assertJsonPath('data.delivery_profile.recipients_count', 2)
            ->assertJsonPath('data.delivery_profile.share_delivery.enabled', true)
            ->assertJsonPath('data.report_preview.headline', 'Castintech Main Account hesabi secili aralikta 650.00 harcama ile 16 sonuc uretti.')
            ->assertJsonCount(7, 'data.trend');
    }

    /**
     * @return array{0: Workspace, 1: string}
     */
    private function bootstrapWorkspaceContext(): array
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

        return [Workspace::query()->findOrFail($workspaceId), $token];
    }
}
