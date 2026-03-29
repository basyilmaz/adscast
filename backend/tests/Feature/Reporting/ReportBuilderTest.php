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
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_and_campaign_report_endpoints_return_client_report_payload(): void
    {
        [$workspace, $token, $account, $campaign] = $this->seedReportFixture();

        $accountResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/reports/account/{$account->id}?start_date=2026-03-10&end_date=2026-03-16");

        $accountResponse->assertOk()
            ->assertJsonPath('data.entity.type', 'account')
            ->assertJsonPath('data.entity.name', 'Castintech Reporting Account')
            ->assertJsonPath('data.report.type', 'client_account_summary_v1')
            ->assertJsonPath('data.what_we_tested.0.type', 'campaign')
            ->assertJsonPath('data.export_options.live_csv_url', "/api/v1/reports/account/{$account->id}/export.csv?start_date=2026-03-10&end_date=2026-03-16")
            ->assertJsonPath('data.next_best_actions.0.source', 'alert');

        $campaignResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/reports/campaign/{$campaign->id}?start_date=2026-03-10&end_date=2026-03-16");

        $campaignResponse->assertOk()
            ->assertJsonPath('data.entity.type', 'campaign')
            ->assertJsonPath('data.entity.name', 'Lead Engine Campaign')
            ->assertJsonPath('data.report.type', 'client_campaign_summary_v1')
            ->assertJsonPath('data.what_we_tested.0.type', 'ad_set')
            ->assertJsonCount(2, 'data.creative_performance')
            ->assertJsonPath('data.export_options.pdf_foundation.mode', 'browser_print')
            ->assertJsonPath('data.creative_performance.0.ad_name', 'Primary Ad')
            ->assertJsonPath('data.creative_performance.0.rank_label', 'En Iyi Performans')
            ->assertJsonPath('data.creative_performance.1.ad_name', 'Secondary Ad')
            ->assertJsonPath('data.creative_performance.1.rank_label', 'En Dusuk Performans')
            ->assertJsonPath('data.next_best_actions.0.source', 'alert');

        $campaignCsvResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->get("/api/v1/reports/campaign/{$campaign->id}/export.csv?start_date=2026-03-10&end_date=2026-03-16");

        $campaignCsvResponse->assertOk();
        $campaignCsv = $campaignCsvResponse->streamedContent();
        $creativeRows = collect(preg_split('/\r\n|\r|\n/', $campaignCsv))
            ->filter(fn (?string $line): bool => is_string($line) && str_starts_with($line, 'creative_performance,'))
            ->values();
        $this->assertCount(3, $creativeRows);
        $this->assertFalse($creativeRows->contains(fn (string $line): bool => str_contains($line, 'No Result Ad')));
    }

    public function test_report_snapshot_can_be_created_listed_and_exported(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture();

        $storeResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/snapshots', [
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'report_type' => 'client_account_summary_v1',
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-16',
            ]);

        $snapshotId = $storeResponse->json('data.id');

        $storeResponse->assertCreated()
            ->assertJsonPath('data.title', 'Castintech Reporting Account hesap raporu');

        $listResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $listResponse->assertOk()
            ->assertJsonPath('data.summary.total_snapshots', 1)
            ->assertJsonPath('data.items.0.id', $snapshotId)
            ->assertJsonPath('data.items.0.entity_type', 'account');

        $snapshotResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/reports/snapshots/{$snapshotId}");

        $snapshotResponse->assertOk()
            ->assertJsonPath('data.snapshot.id', $snapshotId)
            ->assertJsonPath('data.report.type', 'client_account_summary_v1');

        $csvResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->get("/api/v1/reports/snapshots/{$snapshotId}/export.csv");

        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('section,label,value,secondary', $csvContent);
        $this->assertStringContainsString('Castintech Reporting Account hesap raporu', $csvContent);
    }

    /**
     * @return array{0: Workspace, 1: string, 2: MetaAdAccount, 3: Campaign}
     */
    private function seedReportFixture(): array
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
            'account_id' => 'act_report',
            'name' => 'Castintech Reporting Account',
            'status' => 'active',
            'is_active' => true,
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_report',
            'name' => 'Lead Engine Campaign',
            'objective' => 'LEADS',
            'status' => 'active',
            'is_active' => true,
            'daily_budget' => 250,
        ]);

        $adSet = AdSet::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'meta_ad_set_id' => 'adset_report',
            'name' => 'Prospecting Ad Set',
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
            'meta_creative_id' => 'crt_report',
            'name' => 'Creative Report',
            'asset_type' => 'video',
            'headline' => 'Ucretsiz denemeyi hemen baslat',
            'body' => 'Ekibiniz icin performans odakli cozumleri deneyin.',
            'call_to_action' => 'LEARN_MORE',
            'destination_url' => 'https://example.com/report',
            'last_synced_at' => now(),
        ]);

        Ad::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'ad_set_id' => $adSet->id,
            'creative_id' => $creative->id,
            'meta_ad_id' => 'ad_report',
            'name' => 'Primary Ad',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'preview_url' => 'https://example.com/preview/report',
            'last_synced_at' => now(),
        ]);

        $secondaryCreative = Creative::query()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_creative_id' => 'crt_report_secondary',
            'name' => 'Creative Secondary',
            'asset_type' => 'image',
            'headline' => 'Ikinci kreatif varyasyonu',
            'body' => 'Ikincil kreatif varyasyonu ile yeni aci test edin.',
            'call_to_action' => 'LEARN_MORE',
            'destination_url' => 'https://example.com/report-secondary',
            'last_synced_at' => now(),
        ]);

        $noResultCreative = Creative::query()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_creative_id' => 'crt_report_no_result',
            'name' => 'Creative No Result',
            'asset_type' => 'image',
            'headline' => 'Sonuc almayan kreatif',
            'body' => 'Sonuc almayan kreatif testi.',
            'call_to_action' => 'LEARN_MORE',
            'destination_url' => 'https://example.com/report-no-result',
            'last_synced_at' => now(),
        ]);

        Ad::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'ad_set_id' => $adSet->id,
            'creative_id' => $secondaryCreative->id,
            'meta_ad_id' => 'ad_report_secondary',
            'name' => 'Secondary Ad',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'preview_url' => 'https://example.com/preview/report-secondary',
            'last_synced_at' => now(),
        ]);

        Ad::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'ad_set_id' => $adSet->id,
            'creative_id' => $noResultCreative->id,
            'meta_ad_id' => 'ad_report_no_result',
            'name' => 'No Result Ad',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'preview_url' => 'https://example.com/preview/report-no-result',
            'last_synced_at' => now(),
        ]);

        foreach ([
            ['level' => 'campaign', 'external' => 'cmp_report', 'spend' => 840, 'results' => 18, 'ctr' => 2.8, 'cpm' => 24.5, 'frequency' => 1.4],
            ['level' => 'adset', 'external' => 'adset_report', 'spend' => 540, 'results' => 11, 'ctr' => 2.5, 'cpm' => 21.2, 'frequency' => 1.2],
            ['level' => 'ad', 'external' => 'ad_report', 'spend' => 390, 'results' => 8, 'ctr' => 2.9, 'cpm' => 18.4, 'frequency' => 1.1],
            ['level' => 'ad', 'external' => 'ad_report_secondary', 'spend' => 200, 'results' => 4, 'ctr' => 2.2, 'cpm' => 20.1, 'frequency' => 1.0],
            ['level' => 'ad', 'external' => 'ad_report_no_result', 'spend' => 150, 'results' => 0, 'ctr' => 1.1, 'cpm' => 22.3, 'frequency' => 1.5],
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
            'summary' => 'Lead Engine Campaign yakindan izlenmeli.',
            'explanation' => 'Frekans artisina ragmen yeni kreatif acisi test edilmedi.',
            'recommended_action' => 'Yeni kreatif ve teklif acisini test edin.',
            'status' => 'open',
            'date_detected' => '2026-03-11',
        ]);

        Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'campaign',
            'target_id' => $campaign->id,
            'summary' => 'Kampanyada ikinci kreatif varyasyonu acin.',
            'details' => 'Kazanan aciyi bozmadan ikinci kreatif testini canliya alin.',
            'action_type' => 'ai_guidance',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => '2026-03-12 09:00:00',
            'metadata' => [
                'client_friendly_summary' => 'Kampanya iyi gidiyor; yeni testlerle performans artirilmaya calisilacak.',
                'operator_notes' => 'Yeni kreatif varyasyonunu mevcut kazanan acinin yanina acin.',
                'what_to_test_next' => 'Yeni kreatif varyasyonu',
                'budget_note' => 'Butceyi sabit tutup once kreatifi test edin.',
            ],
        ]);

        return [$workspace, $token, $account, $campaign];
    }
}
