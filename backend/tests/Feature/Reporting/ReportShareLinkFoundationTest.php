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
use App\Models\ReportShareLink;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportShareLinkFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_share_link_can_be_created_opened_and_revoked(): void
    {
        [$workspace, $token, $account] = $this->seedShareFixture();

        $snapshotResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/snapshots', [
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'report_type' => 'client_account_summary_v1',
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-16',
            ]);

        $snapshotId = $snapshotResponse->json('data.id');

        $shareResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/snapshots/{$snapshotId}/share-links", [
                'label' => 'Mart musteri paylasimi',
                'expires_in_days' => 7,
                'allow_csv_download' => true,
            ]);

        $shareUrl = $shareResponse->json('data.share_url');
        $shareId = $shareResponse->json('data.id');
        parse_str((string) parse_url((string) $shareUrl, PHP_URL_QUERY), $query);
        $publicToken = $query['token'] ?? null;

        $shareResponse->assertCreated()
            ->assertJsonPath('data.label', 'Mart musteri paylasimi')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.allow_csv_download', true);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.share_summary.total_links', 1)
            ->assertJsonPath('data.share_summary.active_links', 1);

        $detailResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/reports/snapshots/{$snapshotId}");

        $detailResponse->assertOk()
            ->assertJsonPath('data.snapshot.share_links.0.id', $shareId)
            ->assertJsonPath('data.snapshot.share_links.0.status', 'active');

        $publicResponse = $this->getJson("/api/v1/public/report-shares/{$publicToken}");

        $publicResponse->assertOk()
            ->assertJsonPath('data.share_link.label', 'Mart musteri paylasimi')
            ->assertJsonPath('data.share_link.allow_csv_download', true)
            ->assertJsonPath('data.report.operator_summary', 'Paylasim gorunumu icin operator notlari gizlendi.');

        $csvResponse = $this->get("/api/v1/public/report-shares/{$publicToken}/export.csv");

        $csvResponse->assertOk();
        $this->assertStringContainsString('section,label,value,secondary', $csvResponse->streamedContent());

        $revokeResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/share-links/{$shareId}/revoke");

        $revokeResponse->assertOk();
        $this->assertDatabaseHas('report_share_links', [
            'id' => $shareId,
            'workspace_id' => $workspace->id,
        ]);
        $this->assertNotNull(ReportShareLink::query()->findOrFail($shareId)->revoked_at);

        $this->getJson("/api/v1/public/report-shares/{$publicToken}")
            ->assertStatus(410);
    }

    /**
     * @return array{0: Workspace, 1: string, 2: MetaAdAccount}
     */
    private function seedShareFixture(): array
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'agency.admin@adscast.test',
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
            'account_id' => 'act_share',
            'name' => 'Castintech Share Account',
            'status' => 'active',
            'is_active' => true,
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_share',
            'name' => 'Share Engine Campaign',
            'objective' => 'LEADS',
            'status' => 'active',
            'is_active' => true,
            'daily_budget' => 250,
        ]);

        $adSet = AdSet::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'meta_ad_set_id' => 'adset_share',
            'name' => 'Share Prospecting',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'optimization_goal' => 'LEAD_GENERATION',
            'billing_event' => 'IMPRESSIONS',
            'daily_budget' => 125,
            'targeting' => [
                'geo_locations' => ['countries' => ['TR']],
                'publisher_platforms' => ['facebook', 'instagram'],
            ],
            'last_synced_at' => now(),
        ]);

        $creative = Creative::query()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_creative_id' => 'crt_share',
            'name' => 'Share Creative',
            'asset_type' => 'image',
            'headline' => 'Musteriyle paylasilabilir rapor hazir',
            'body' => 'Snapshot tabanli rapor paylasimi test edilir.',
            'call_to_action' => 'LEARN_MORE',
            'destination_url' => 'https://example.com/share',
            'last_synced_at' => now(),
        ]);

        Ad::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'ad_set_id' => $adSet->id,
            'creative_id' => $creative->id,
            'meta_ad_id' => 'ad_share',
            'name' => 'Share Ad',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'preview_url' => 'https://example.com/preview/share',
            'last_synced_at' => now(),
        ]);

        foreach ([
            ['level' => 'campaign', 'external' => 'cmp_share', 'spend' => 730, 'results' => 15, 'ctr' => 2.4, 'cpm' => 23.1, 'frequency' => 1.5],
            ['level' => 'adset', 'external' => 'adset_share', 'spend' => 490, 'results' => 10, 'ctr' => 2.1, 'cpm' => 19.2, 'frequency' => 1.3],
            ['level' => 'ad', 'external' => 'ad_share', 'spend' => 340, 'results' => 7, 'ctr' => 2.7, 'cpm' => 16.4, 'frequency' => 1.2],
        ] as $item) {
            \App\Models\InsightDaily::factory()->create([
                'workspace_id' => $workspace->id,
                'level' => $item['level'],
                'entity_external_id' => $item['external'],
                'date' => '2026-03-10',
                'spend' => $item['spend'],
                'results' => $item['results'],
                'leads' => $item['results'],
                'conversions' => $item['results'],
                'cost_per_result' => round($item['spend'] / $item['results'], 4),
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
            'severity' => 'medium',
            'summary' => 'Share Engine Campaign izlenmeli.',
            'explanation' => 'Raporlama gorunumu client-facing hale getiriliyor.',
            'recommended_action' => 'Musteri icin snapshot paylasim linki cikarin.',
            'status' => 'open',
            'date_detected' => '2026-03-11',
        ]);

        Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'campaign',
            'target_id' => $campaign->id,
            'summary' => 'Musteriyle paylasim icin snapshot linki uretin.',
            'details' => 'Kaydedilmis raporu tokenli link ile dis paylasima hazirlayin.',
            'action_type' => 'ai_guidance',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => '2026-03-12 09:00:00',
            'metadata' => [
                'client_friendly_summary' => 'Rapor duzenli ve paylasilabilir hale getiriliyor.',
                'operator_notes' => 'Snapshot uzerinden tokenli public link olustur.',
                'what_to_test_next' => 'Client share link',
                'budget_note' => 'Butce etkisi yok.',
            ],
        ]);

        return [$workspace, $token, $account];
    }
}
