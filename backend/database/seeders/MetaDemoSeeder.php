<?php

namespace Database\Seeders;

use App\Domain\Meta\Services\MetaConnectionService;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\Approval;
use App\Models\Campaign;
use App\Models\CampaignDraft;
use App\Models\CampaignDraftItem;
use App\Models\Creative;
use App\Models\InsightDaily;
use App\Models\MetaAdAccount;
use App\Models\Recommendation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWorkspaceRole;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MetaDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspace = Workspace::query()->where('slug', 'demo-workspace')->first();

        if (! $workspace) {
            return;
        }

        $manager = User::query()->where('email', 'manager@adscast.local')->first();
        $reviewer = User::query()->where('email', 'agency_admin@adscast.local')->first();

        $connection = app(MetaConnectionService::class)->upsertConnection($workspace, [
            'access_token' => 'demo_access_token_that_is_long_enough',
            'refresh_token' => 'demo_refresh_token_that_is_long_enough',
            'external_user_id' => 'meta_demo_user',
            'api_version' => 'v20.0',
            'scopes' => ['ads_read', 'business_management'],
            'metadata' => [
                'seed' => true,
            ],
            'token_expires_at' => now()->addDays(45)->toIso8601String(),
        ]);

        $account = MetaAdAccount::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'account_id' => 'act_1001',
            ],
            [
                'id' => (string) Str::uuid(),
                'meta_connection_id' => $connection->id,
                'name' => 'Demo Hesap TR',
                'currency' => 'USD',
                'timezone_name' => 'Europe/Istanbul',
                'status' => 'active',
                'is_active' => true,
                'metadata' => ['seed' => true],
                'last_synced_at' => now(),
            ]
        );

        $campaign = Campaign::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'meta_campaign_id' => 'cmp_1001',
            ],
            [
                'id' => (string) Str::uuid(),
                'meta_ad_account_id' => $account->id,
                'name' => 'Ilk Performans Kampanyasi',
                'objective' => 'LEADS',
                'status' => 'active',
                'effective_status' => 'ACTIVE',
                'buying_type' => 'AUCTION',
                'daily_budget' => 150,
                'is_active' => true,
                'metadata' => ['seed' => true],
                'last_synced_at' => now(),
            ]
        );

        $creative = Creative::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'meta_creative_id' => 'crt_1001',
            ],
            [
                'id' => (string) Str::uuid(),
                'meta_ad_account_id' => $account->id,
                'name' => 'Creative Seed A',
                'asset_type' => 'video',
                'body' => 'Kisa sureli avantajli teklif.',
                'headline' => 'Hemen Incele',
                'description' => 'Demo creative aciklamasi',
                'call_to_action' => 'LEARN_MORE',
                'destination_url' => 'https://example.com/landing',
                'metadata' => ['seed' => true],
                'last_synced_at' => now(),
            ]
        );

        $adSet = AdSet::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'meta_ad_set_id' => 'adset_1001',
            ],
            [
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'name' => 'TR - Core Audience',
                'status' => 'active',
                'effective_status' => 'ACTIVE',
                'optimization_goal' => 'LEAD_GENERATION',
                'billing_event' => 'IMPRESSIONS',
                'bid_strategy' => 'LOWEST_COST',
                'daily_budget' => 80,
                'targeting' => ['country' => 'TR'],
                'metadata' => ['seed' => true],
                'last_synced_at' => now(),
            ]
        );

        Ad::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'meta_ad_id' => 'ad_1001',
            ],
            [
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'ad_set_id' => $adSet->id,
                'creative_id' => $creative->id,
                'name' => 'Video Ad Seed',
                'status' => 'active',
                'effective_status' => 'ACTIVE',
                'preview_url' => 'https://example.com/preview/ad_1001',
                'metadata' => ['seed' => true],
                'last_synced_at' => now(),
            ]
        );

        for ($i = 0; $i < 14; $i++) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $spend = 90 + ($i * 2.3);
            $results = max(0, 18 - $i);

            InsightDaily::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'level' => 'campaign',
                    'entity_external_id' => $campaign->meta_campaign_id,
                    'date' => $date,
                    'source' => 'meta',
                ],
                [
                    'entity_id' => $campaign->id,
                    'spend' => $spend,
                    'impressions' => 4000 + ($i * 120),
                    'reach' => 2900 + ($i * 80),
                    'frequency' => 1.2 + ($i * 0.12),
                    'clicks' => 120 - $i,
                    'link_clicks' => 90 - $i,
                    'ctr' => max(0.5, 3.2 - ($i * 0.12)),
                    'cpc' => 0.7 + ($i * 0.05),
                    'cpm' => 17 + ($i * 0.8),
                    'results' => $results,
                    'cost_per_result' => $results > 0 ? round($spend / $results, 4) : null,
                    'leads' => $results,
                    'purchases' => (int) floor($results / 5),
                    'roas' => 2.6 - ($i * 0.05),
                    'conversions' => $results,
                    'actions' => [
                        ['type' => 'lead', 'value' => $results],
                    ],
                    'synced_at' => now(),
                ]
            );
        }

        $alert = Alert::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'entity_type' => 'campaign',
                'entity_id' => $campaign->id,
                'code' => 'falling_ctr',
                'date_detected' => now()->toDateString(),
            ],
            [
                'id' => (string) Str::uuid(),
                'severity' => 'medium',
                'summary' => 'CTR dusus sinyali mevcut.',
                'explanation' => 'Son gunlerde CTR kademeli olarak geriliyor.',
                'recommended_action' => 'Creative varyasyon testlerini hizlandirin.',
                'confidence' => 0.82,
                'status' => 'open',
                'source_rule_version' => 'v1',
            ]
        );

        Recommendation::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'alert_id' => $alert->id,
                'summary' => 'Creative testi ve mesaj optimizasyonu onerilir.',
                'target_type' => 'campaign',
                'target_id' => $campaign->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'details' => '2 yeni headline + 2 yeni primary text varyasyonu acin.',
                'action_type' => 'creative_test',
                'priority' => 'medium',
                'status' => 'open',
                'source' => 'rules',
                'generated_at' => now(),
            ]
        );

        $draft = CampaignDraft::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'objective' => 'LEADS',
                'product_service' => 'Demo urun lansmani',
                'target_audience' => 'E-ticaret yoneticileri',
            ],
            [
                'id' => (string) Str::uuid(),
                'meta_ad_account_id' => $account->id,
                'location' => 'TR',
                'budget_min' => 50,
                'budget_max' => 150,
                'offer' => 'Ilk ay %20 indirim',
                'landing_page_url' => 'https://example.com/landing',
                'tone_style' => 'performans',
                'existing_creative_availability' => 'var',
                'notes' => 'Seed verisi ile olusturuldu.',
                'status' => 'pending_review',
                'created_by' => $manager?->id,
            ]
        );

        CampaignDraftItem::query()->firstOrCreate(
            [
                'campaign_draft_id' => $draft->id,
                'item_type' => 'campaign_structure',
            ],
            [
                'id' => (string) Str::uuid(),
                'title' => 'Campaign Structure',
                'content' => [
                    'proposed_campaign_name' => 'LEADS_TR_'.now()->format('Ymd'),
                    'ad_sets' => ['Core', 'Lookalike'],
                ],
                'sort_order' => 1,
            ]
        );

        Approval::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'approvable_type' => CampaignDraft::class,
                'approvable_id' => $draft->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'status' => 'pending_review',
                'created_by' => $manager?->id,
                'submitted_at' => now(),
                'reviewed_by' => $reviewer?->id,
            ]
        );
    }
}
