<?php

namespace Tests\Feature\Reporting;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\Creative;
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

class CampaignDrillDownTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_detail_returns_tabs_ready_drill_down_payload(): void
    {
        [$workspace, $token, $campaign, $adSet, $ad] = $this->seedDrillDownFixture();

        Setting::query()->create([
            'workspace_id' => $workspace->id,
            'key' => 'reports.delivery_profiles',
            'value' => [[
                'id' => (string) Str::uuid(),
                'entity_type' => 'campaign',
                'entity_id' => $campaign->id,
                'recipient_preset_id' => null,
                'delivery_channel' => 'email',
                'cadence' => 'monthly',
                'weekday' => null,
                'month_day' => 5,
                'send_time' => '08:45',
                'timezone' => 'Europe/Istanbul',
                'default_range_days' => 7,
                'layout_preset' => 'client_digest',
                'recipients' => ['musteri@castintech.com'],
                'share_delivery' => [
                    'enabled' => true,
                    'label_template' => '{template_name} / {end_date}',
                    'expires_in_days' => 14,
                    'allow_csv_download' => true,
                ],
                'is_active' => true,
                'created_at' => now()->subHour()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]],
            'is_encrypted' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/campaigns/{$campaign->id}?start_date=2026-03-10&end_date=2026-03-16");

        $response->assertOk()
            ->assertJsonPath('data.campaign.name', 'Lead Engine Campaign')
            ->assertJsonPath('data.health.status', 'warning')
            ->assertJsonPath('data.summary.active_ad_sets', 2)
            ->assertJsonPath('data.summary.active_ads', 2)
            ->assertJsonPath('data.analysis.biggest_risk', 'Lead Engine Campaign sonuc kaybi yasiyor.')
            ->assertJsonPath('data.next_best_actions.0.source', 'alert')
            ->assertJsonPath('data.delivery_profile.entity_type', 'campaign')
            ->assertJsonPath('data.delivery_profile.entity_id', $campaign->id)
            ->assertJsonPath('data.delivery_profile.delivery_channel', 'email')
            ->assertJsonPath('data.delivery_profile.month_day', 5)
            ->assertJsonPath('data.delivery_profile.recipients_count', 1)
            ->assertJsonPath('data.delivery_profile.recipient_group_summary.mode', 'manual')
            ->assertJsonPath('data.delivery_profile.recipient_group_summary.static_recipients_count', 1)
            ->assertJsonPath('data.delivery_profile.share_delivery.allow_csv_download', true)
            ->assertJsonPath('data.report_preview.next_test', 'Yeni aci ile headline varyasyonu test edin.')
            ->assertJsonFragment([
                'name' => 'Prospecting Ad Set',
                'performance_scope' => 'adset',
            ])
            ->assertJsonFragment([
                'name' => 'Primary Ad',
                'performance_scope' => 'ad',
            ])
            ->assertJsonFragment([
                'headline' => 'Ucretsiz denemeyi hemen baslat',
            ])
            ->assertJsonStructure([
                'data' => [
                    'campaign',
                    'health',
                    'summary',
                    'trend',
                    'ad_sets',
                    'ads',
                    'alerts',
                    'recommendations',
                    'next_best_actions',
                    'analysis',
                    'report_preview',
                ],
            ]);
    }

    public function test_campaign_list_supports_account_and_status_filters(): void
    {
        [$workspace, $token, $campaign] = $this->seedDrillDownFixture();

        Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $campaign->meta_ad_account_id,
            'meta_campaign_id' => 'cmp_paused_extra',
            'name' => 'Paused Retention Campaign',
            'objective' => 'MESSAGES',
            'status' => 'paused',
            'is_active' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson(sprintf(
                '/api/v1/campaigns?start_date=2026-03-10&end_date=2026-03-16&ad_account_id=%s&objective=LEADS&status=active',
                $campaign->meta_ad_account_id,
            ));

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Lead Engine Campaign')
            ->assertJsonPath('data.items.0.ad_account_name', 'Castintech Growth Account')
            ->assertJsonPath('data.items.0.ad_account_external_id', 'act_555')
            ->assertJsonPath('data.items.0.objective', 'LEADS')
            ->assertJsonPath('data.items.0.status', 'active');
    }

    public function test_ad_set_and_ad_detail_return_scoped_context(): void
    {
        [$workspace, $token, $campaign, $adSet, $ad] = $this->seedDrillDownFixture();

        $adSetResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/ad-sets/{$adSet->id}?start_date=2026-03-10&end_date=2026-03-16");

        $adSetResponse->assertOk()
            ->assertJsonPath('data.ad_set.name', 'Prospecting Ad Set')
            ->assertJsonPath('data.ad_set.campaign.name', 'Lead Engine Campaign')
            ->assertJsonPath('data.summary.performance_scope', 'adset')
            ->assertJsonPath('data.targeting_summary.countries.0', 'TR')
            ->assertJsonPath('data.sibling_ad_sets.0.name', 'Retargeting Ad Set')
            ->assertJsonPath('data.ads.0.name', 'Primary Ad')
            ->assertJsonPath('data.guidance.data_scope_note', 'Bu gorunum ad set seviyesinde normalized performans kullaniyor.');

        $adResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/ads/{$ad->id}?start_date=2026-03-10&end_date=2026-03-16");

        $adResponse->assertOk()
            ->assertJsonPath('data.ad.name', 'Primary Ad')
            ->assertJsonPath('data.ad.campaign.name', 'Lead Engine Campaign')
            ->assertJsonPath('data.ad.ad_set.name', 'Prospecting Ad Set')
            ->assertJsonPath('data.summary.performance_scope', 'ad')
            ->assertJsonPath('data.creative.headline', 'Ucretsiz denemeyi hemen baslat')
            ->assertJsonPath('data.sibling_ads.0.name', 'Retargeting Ad')
            ->assertJsonPath('data.guidance.creative_note', 'Mevcut headline: "Ucretsiz denemeyi hemen baslat". Farkli bir aci ile ikinci varyasyon test edin.');
    }

    /**
     * @return array{0: Workspace, 1: string, 2: Campaign, 3: AdSet, 4: Ad}
     */
    private function seedDrillDownFixture(): array
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
            'account_id' => 'act_555',
            'name' => 'Castintech Growth Account',
            'status' => 'active',
            'is_active' => true,
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_lead_engine',
            'name' => 'Lead Engine Campaign',
            'objective' => 'LEADS',
            'status' => 'active',
            'is_active' => true,
            'daily_budget' => 300,
        ]);

        $prospecting = AdSet::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'meta_ad_set_id' => 'adset_prospecting',
            'name' => 'Prospecting Ad Set',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'optimization_goal' => 'LEAD_GENERATION',
            'billing_event' => 'IMPRESSIONS',
            'bid_strategy' => 'LOWEST_COST',
            'daily_budget' => 150,
            'targeting' => [
                'geo_locations' => ['countries' => ['TR']],
                'age_min' => 24,
                'age_max' => 45,
                'publisher_platforms' => ['facebook', 'instagram'],
                'flexible_spec' => [
                    [
                        ['name' => 'Digital marketing'],
                    ],
                ],
            ],
            'last_synced_at' => now(),
        ]);

        $retargeting = AdSet::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'meta_ad_set_id' => 'adset_retargeting',
            'name' => 'Retargeting Ad Set',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'optimization_goal' => 'LEAD_GENERATION',
            'daily_budget' => 80,
            'targeting' => [
                'geo_locations' => ['countries' => ['TR']],
            ],
            'last_synced_at' => now(),
        ]);

        $creativeA = Creative::query()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_creative_id' => 'crt_a',
            'name' => 'Creative A',
            'asset_type' => 'video',
            'headline' => 'Ucretsiz denemeyi hemen baslat',
            'body' => 'Ekibiniz icin performans odakli cozumleri bugun deneyin.',
            'description' => 'Kisa aciklama',
            'call_to_action' => 'LEARN_MORE',
            'destination_url' => 'https://example.com/lead',
            'last_synced_at' => now(),
        ]);

        $creativeB = Creative::query()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_creative_id' => 'crt_b',
            'name' => 'Creative B',
            'asset_type' => 'image',
            'headline' => 'Teklifinizi bugun alin',
            'body' => 'Retargeting kreatifi',
            'call_to_action' => 'SIGN_UP',
            'destination_url' => 'https://example.com/retarget',
            'last_synced_at' => now(),
        ]);

        $ad = Ad::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'ad_set_id' => $prospecting->id,
            'creative_id' => $creativeA->id,
            'meta_ad_id' => 'ad_primary',
            'name' => 'Primary Ad',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'preview_url' => 'https://example.com/preview/primary',
            'last_synced_at' => now(),
        ]);

        Ad::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'ad_set_id' => $prospecting->id,
            'creative_id' => $creativeB->id,
            'meta_ad_id' => 'ad_secondary',
            'name' => 'Retargeting Ad',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'preview_url' => 'https://example.com/preview/retargeting',
            'last_synced_at' => now(),
        ]);

        foreach ([
            ['level' => 'campaign', 'external' => 'cmp_lead_engine', 'spend' => 500, 'results' => 12, 'ctr' => 2.5, 'cpm' => 21.5, 'frequency' => 1.4],
            ['level' => 'campaign', 'external' => 'cmp_lead_engine', 'spend' => 260, 'results' => 0, 'ctr' => 1.1, 'cpm' => 30.2, 'frequency' => 2.3, 'date' => '2026-03-11'],
            ['level' => 'adset', 'external' => 'adset_prospecting', 'spend' => 420, 'results' => 10, 'ctr' => 2.9, 'cpm' => 18.4, 'frequency' => 1.3],
            ['level' => 'adset', 'external' => 'adset_retargeting', 'spend' => 120, 'results' => 2, 'ctr' => 2.1, 'cpm' => 16.2, 'frequency' => 1.1],
            ['level' => 'ad', 'external' => 'ad_primary', 'spend' => 310, 'results' => 8, 'ctr' => 3.2, 'cpm' => 14.8, 'frequency' => 1.2],
            ['level' => 'ad', 'external' => 'ad_secondary', 'spend' => 110, 'results' => 2, 'ctr' => 1.6, 'cpm' => 13.1, 'frequency' => 1.0],
        ] as $item) {
            \App\Models\InsightDaily::factory()->create([
                'workspace_id' => $workspace->id,
                'level' => $item['level'],
                'entity_external_id' => $item['external'],
                'date' => $item['date'] ?? '2026-03-10',
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
            'entity_id' => $campaign->id,
            'severity' => 'high',
            'summary' => 'Lead Engine Campaign sonuc kaybi yasiyor.',
            'recommended_action' => 'Hedefleme ve kreatif varyasyonlarini yeniden dengeleyin.',
            'status' => 'open',
            'date_detected' => '2026-03-12',
        ]);

        Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'campaign',
            'target_id' => $campaign->id,
            'summary' => 'Yeni aci ile headline varyasyonu test edin.',
            'details' => 'Prospecting ve retargeting icin farkli mesaj acilari ayirin.',
            'action_type' => 'ai_guidance',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => '2026-03-12 11:00:00',
            'metadata' => ['source' => 'phpunit'],
        ]);

        return [$workspace, $token, $campaign, $prospecting, $ad];
    }
}
