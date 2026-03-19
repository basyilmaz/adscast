<?php

namespace Tests\Feature;

use App\Models\MetaConnection;
use App\Models\RawApiPayload;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAssetSyncLiveAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_mode_asset_sync_persists_meta_assets_and_masks_raw_tokens(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        config()->set('services.meta.mode', 'live');
        config()->set('services.meta.graph_base_url', 'https://graph.facebook.com');
        config()->set('services.meta.raw_payload_retention_days', 30);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'agency.admin@adscast.test',
            'password' => 'Password123!',
        ]);

        $token = $login->json('token');
        $workspaceId = collect($login->json('workspaces'))
            ->firstWhere('slug', 'operations-main')['id'] ?? null;

        $connection = MetaConnection::query()->create([
            'workspace_id' => $workspaceId,
            'provider' => 'meta',
            'api_version' => 'v20.0',
            'status' => 'active',
            'external_user_id' => 'usr_123',
            'access_token_encrypted' => Crypt::encryptString('live_token_value'),
            'scopes' => ['ads_read', 'business_management'],
            'metadata' => [
                'connection_mode' => 'live',
                'connected_user_name' => 'Castintech Operator',
            ],
            'connected_at' => now()->subHour(),
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/me/adaccounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'act_123456',
                        'account_id' => '123456',
                        'name' => 'Castintech Performance',
                        'currency' => 'TRY',
                        'timezone_name' => 'Europe/Istanbul',
                        'account_status' => 1,
                        'business' => [
                            'id' => 'biz_987',
                            'name' => 'Castintech Agency',
                            'verification_status' => 'verified',
                        ],
                    ],
                ],
            ], 200, [
                'x-app-usage' => '{"call_count":2}',
            ]),
            'https://graph.facebook.com/v20.0/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'pg_555',
                        'name' => 'Castintech Page',
                        'category' => 'Brand',
                        'access_token' => 'page_token_should_be_masked',
                    ],
                ],
            ]),
            'https://graph.facebook.com/v20.0/act_123456/adspixels*' => Http::response([
                'data' => [
                    [
                        'id' => 'px_111',
                        'name' => 'Castintech Pixel',
                        'is_unavailable' => false,
                    ],
                ],
            ]),
            'https://graph.facebook.com/v20.0/act_123456/campaigns*' => Http::response([
                'data' => [
                    [
                        'id' => 'cmp_live_1',
                        'name' => 'TR Leads Campaign',
                        'objective' => 'LEADS',
                        'status' => 'ACTIVE',
                        'effective_status' => 'ACTIVE',
                        'buying_type' => 'AUCTION',
                        'daily_budget' => '250000',
                        'start_time' => now()->subDays(3)->toISOString(),
                    ],
                ],
            ]),
            'https://graph.facebook.com/v20.0/act_123456/adsets*' => Http::response([
                'data' => [
                    [
                        'id' => 'adset_live_1',
                        'campaign_id' => 'cmp_live_1',
                        'name' => 'TR Broad',
                        'status' => 'ACTIVE',
                        'effective_status' => 'ACTIVE',
                        'optimization_goal' => 'LEAD_GENERATION',
                        'billing_event' => 'IMPRESSIONS',
                        'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
                        'daily_budget' => '125000',
                        'targeting' => ['geo_locations' => ['countries' => ['TR']]],
                    ],
                ],
            ]),
            'https://graph.facebook.com/v20.0/act_123456/adcreatives*' => Http::response([
                'data' => [
                    [
                        'id' => 'crt_live_1',
                        'name' => 'Creative One',
                        'object_story_spec' => [
                            'link_data' => [
                                'message' => 'Yeni teklif',
                                'name' => 'Hemen Incele',
                                'description' => 'Aciklama',
                                'link' => 'https://castintech.com/landing',
                                'call_to_action' => ['type' => 'LEARN_MORE'],
                            ],
                        ],
                    ],
                ],
            ]),
            'https://graph.facebook.com/v20.0/act_123456/ads*' => Http::response([
                'data' => [
                    [
                        'id' => 'ad_live_1',
                        'campaign_id' => 'cmp_live_1',
                        'adset_id' => 'adset_live_1',
                        'name' => 'Ad One',
                        'status' => 'ACTIVE',
                        'effective_status' => 'ACTIVE',
                        'creative' => ['id' => 'crt_live_1'],
                        'preview_shareable_link' => 'https://facebook.com/preview/123',
                    ],
                ],
            ]),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson("/api/v1/meta/connections/{$connection->id}/sync-assets");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.summary.businesses', 1)
            ->assertJsonPath('data.summary.accounts', 1)
            ->assertJsonPath('data.summary.campaigns', 1);

        $this->assertDatabaseHas('meta_businesses', [
            'workspace_id' => $workspaceId,
            'business_id' => 'biz_987',
            'name' => 'Castintech Agency',
        ]);

        $this->assertDatabaseHas('meta_ad_accounts', [
            'workspace_id' => $workspaceId,
            'account_id' => 'act_123456',
            'name' => 'Castintech Performance',
        ]);

        $this->assertDatabaseHas('meta_pages', [
            'workspace_id' => $workspaceId,
            'page_id' => 'pg_555',
            'name' => 'Castintech Page',
        ]);

        $this->assertDatabaseHas('meta_pixels', [
            'workspace_id' => $workspaceId,
            'pixel_id' => 'px_111',
        ]);

        $this->assertDatabaseHas('campaigns', [
            'workspace_id' => $workspaceId,
            'meta_campaign_id' => 'cmp_live_1',
            'name' => 'TR Leads Campaign',
        ]);

        $this->assertDatabaseHas('ad_sets', [
            'workspace_id' => $workspaceId,
            'meta_ad_set_id' => 'adset_live_1',
        ]);

        $this->assertDatabaseHas('creatives', [
            'workspace_id' => $workspaceId,
            'meta_creative_id' => 'crt_live_1',
        ]);

        $this->assertDatabaseHas('ads', [
            'workspace_id' => $workspaceId,
            'meta_ad_id' => 'ad_live_1',
        ]);

        $rawPayload = RawApiPayload::query()
            ->where('resource_type', 'list_pages')
            ->firstOrFail();

        $this->assertSame('[masked]', data_get($rawPayload->payload, '0.page_access_token'));
    }
}
