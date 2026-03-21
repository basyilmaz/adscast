<?php

namespace Tests\Feature\Reporting;

use App\Domain\Reporting\Mail\ScheduledReportDeliveryMail;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Creative;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\Recommendation;
use App\Models\ReportDeliveryRun;
use App\Models\ReportDeliverySchedule;
use App\Models\ReportShareLink;
use App\Models\ReportSnapshot;
use App\Models\ReportTemplate;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportDeliveryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_and_schedules_can_be_created_listed_and_run(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $templateResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/templates', [
                'name' => 'Haftalik Musteri Ozeti',
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'report_type' => 'client_account_summary_v1',
                'default_range_days' => 14,
                'layout_preset' => 'client_digest',
                'notes' => 'Musteri toplantisi oncesi otomatik hazirlansin.',
            ]);

        $templateId = $templateResponse->json('data.id');

        $templateResponse->assertCreated()
            ->assertJsonPath('data.name', 'Haftalik Musteri Ozeti');

        $scheduleResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/delivery-schedules', [
                'report_template_id' => $templateId,
                'cadence' => 'weekly',
                'weekday' => 1,
                'send_time' => '09:15',
                'timezone' => 'Europe/Istanbul',
                'recipients' => ['client@castintech.com', 'ops@castintech.com'],
                'auto_share_enabled' => true,
                'share_label_template' => '{template_name} / {end_date}',
                'share_expires_in_days' => 14,
                'share_allow_csv_download' => true,
            ]);

        $scheduleId = $scheduleResponse->json('data.id');

        $scheduleResponse->assertCreated()
            ->assertJsonPath('data.id', $scheduleId);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.template_summary.total_templates', 1)
            ->assertJsonPath('data.delivery_summary.total_schedules', 1)
            ->assertJsonPath('data.templates.0.id', $templateId)
            ->assertJsonPath('data.delivery_schedules.0.id', $scheduleId)
            ->assertJsonPath('data.delivery_schedules.0.template.name', 'Haftalik Musteri Ozeti');

        $runResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/delivery-schedules/{$scheduleId}/run-now");

        $snapshotId = $runResponse->json('data.snapshot_id');
        $shareLinkId = $runResponse->json('data.share_link.id');

        $runResponse->assertOk()
            ->assertJsonPath('data.status', 'delivered_stub')
            ->assertJsonPath('data.share_link.id', $shareLinkId)
            ->assertJsonPath('data.share_link.allow_csv_download', true);

        $this->assertDatabaseHas('report_templates', [
            'id' => $templateId,
            'workspace_id' => $workspace->id,
            'report_type' => 'client_account_summary_v1',
        ]);

        $this->assertDatabaseHas('report_delivery_schedules', [
            'id' => $scheduleId,
            'workspace_id' => $workspace->id,
            'last_status' => 'delivered_stub',
            'last_report_snapshot_id' => $snapshotId,
        ]);

        $this->assertDatabaseHas('report_delivery_runs', [
            'report_delivery_schedule_id' => $scheduleId,
            'report_snapshot_id' => $snapshotId,
            'status' => 'delivered_stub',
            'trigger_mode' => 'manual',
        ]);

        $this->assertDatabaseHas('report_share_links', [
            'id' => $shareLinkId,
            'workspace_id' => $workspace->id,
            'report_snapshot_id' => $snapshotId,
            'allow_csv_download' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_template_created',
            'target_id' => $templateId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_delivery_schedule_created',
            'target_id' => $scheduleId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_delivery_run_prepared',
        ]);

        $indexAfterRun = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexAfterRun->assertOk()
            ->assertJsonPath('data.delivery_schedules.0.share_delivery.enabled', true)
            ->assertJsonPath('data.delivery_schedules.0.recent_runs.0.share_link.id', $shareLinkId);
    }

    public function test_report_delivery_command_processes_due_schedules(): void
    {
        [$workspace, , $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Komutla Calisan Rapor',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'report_type' => 'client_account_summary_v1',
            'default_range_days' => 30,
            'layout_preset' => 'standard',
            'is_active' => true,
        ]);

        $schedule = ReportDeliverySchedule::query()->create([
            'workspace_id' => $workspace->id,
            'report_template_id' => $template->id,
            'delivery_channel' => 'email_stub',
            'cadence' => 'daily',
            'send_time' => '08:00',
            'timezone' => 'Europe/Istanbul',
            'recipients' => ['client@castintech.com'],
            'configuration' => [
                'auto_share' => [
                    'enabled' => true,
                    'label_template' => '{template_name} / {end_date}',
                    'expires_in_days' => 7,
                    'allow_csv_download' => false,
                ],
            ],
            'is_active' => true,
            'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan('adscast:run-report-deliveries', [
            '--workspace-id' => $workspace->id,
            '--no-lock' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('report_delivery_runs', [
            'report_delivery_schedule_id' => $schedule->id,
            'status' => 'delivered_stub',
            'trigger_mode' => 'scheduled',
        ]);
        $this->assertSame(1, ReportShareLink::query()->where('workspace_id', $workspace->id)->count());

        $this->assertSame(1, ReportSnapshot::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(1, ReportDeliveryRun::query()->where('report_delivery_schedule_id', $schedule->id)->count());
        $this->assertGreaterThan(0, AuditLog::query()->where('workspace_id', $workspace->id)->where('action', 'report_delivery_run_prepared')->count());

        $run = ReportDeliveryRun::query()->where('report_delivery_schedule_id', $schedule->id)->firstOrFail();
        $this->assertNotNull(data_get($run->metadata, 'share_link.id'));
        $this->assertStringContainsString('Komutla Calisan Rapor', (string) data_get($run->metadata, 'share_link.label'));

        $schedule->refresh();
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->gt(now()));
    }

    public function test_email_channel_sends_real_delivery_mail_and_updates_run_status(): void
    {
        Mail::fake();

        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Gercek Email Teslimi',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'report_type' => 'client_account_summary_v1',
            'default_range_days' => 14,
            'layout_preset' => 'client_digest',
            'is_active' => true,
        ]);

        $scheduleResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/delivery-schedules', [
                'report_template_id' => $template->id,
                'delivery_channel' => 'email',
                'cadence' => 'weekly',
                'weekday' => 2,
                'send_time' => '10:00',
                'timezone' => 'Europe/Istanbul',
                'recipients' => ['client@castintech.com', 'ops@castintech.com'],
                'auto_share_enabled' => true,
                'share_label_template' => '{template_name} / {end_date}',
                'share_expires_in_days' => 7,
                'share_allow_csv_download' => false,
            ]);

        $scheduleId = $scheduleResponse->json('data.id');

        $runResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/delivery-schedules/{$scheduleId}/run-now");

        $runResponse->assertOk()
            ->assertJsonPath('data.status', 'delivered_email')
            ->assertJsonPath('data.share_link.status', 'active');

        Mail::assertSent(ScheduledReportDeliveryMail::class, 2);

        $this->assertDatabaseHas('report_delivery_runs', [
            'report_delivery_schedule_id' => $scheduleId,
            'status' => 'delivered_email',
            'delivery_channel' => 'email',
        ]);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.delivery_capabilities.default_mailer', config('mail.default'))
            ->assertJsonPath('data.delivery_schedules.0.delivery_channel', 'email')
            ->assertJsonPath('data.delivery_schedules.0.recent_runs.0.delivery.channel', 'email');
    }

    public function test_quick_delivery_setup_creates_campaign_scoped_schedule_with_recipients(): void
    {
        [$workspace, $token, , $campaign] = $this->seedReportFixture('agency.admin@adscast.test');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/delivery-setups', [
                'entity_type' => 'campaign',
                'entity_id' => $campaign->id,
                'template_name' => 'Kampanya Musteri Ritmi',
                'default_range_days' => 7,
                'layout_preset' => 'client_digest',
                'delivery_channel' => 'email_stub',
                'cadence' => 'monthly',
                'month_day' => 5,
                'send_time' => '08:45',
                'timezone' => 'Europe/Istanbul',
                'recipients' => ['musteri@castintech.com', 'marka@castintech.com'],
                'auto_share_enabled' => true,
                'share_label_template' => '{template_name} / {end_date}',
                'share_expires_in_days' => 14,
                'share_allow_csv_download' => true,
            ]);

        $scheduleId = $response->json('data.schedule_id');
        $templateId = $response->json('data.template_id');

        $response->assertCreated()
            ->assertJsonPath('data.template_created', true)
            ->assertJsonPath('data.entity_type', 'campaign')
            ->assertJsonPath('data.entity_id', $campaign->id)
            ->assertJsonPath('data.schedule_id', $scheduleId);

        $this->assertDatabaseHas('report_templates', [
            'id' => $templateId,
            'workspace_id' => $workspace->id,
            'entity_type' => 'campaign',
            'entity_id' => $campaign->id,
            'report_type' => 'client_campaign_summary_v1',
        ]);

        $this->assertDatabaseHas('report_delivery_schedules', [
            'id' => $scheduleId,
            'workspace_id' => $workspace->id,
            'report_template_id' => $templateId,
            'cadence' => 'monthly',
            'month_day' => 5,
            'delivery_channel' => 'email_stub',
        ]);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.delivery_schedules.0.template.entity_type', 'campaign')
            ->assertJsonPath('data.delivery_schedules.0.recipients_count', 2)
            ->assertJsonPath('data.delivery_schedules.0.share_delivery.enabled', true);
    }

    public function test_recipient_preset_can_drive_default_delivery_profile_creation(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $presetResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/recipient-presets', [
                'name' => 'Merva Musteri Dagitimi',
                'recipients' => ['merva@castintech.com', 'owner@castintech.com'],
                'notes' => 'Haftalik account ozeti',
            ]);

        $presetId = $presetResponse->json('data.id');

        $presetResponse->assertCreated()
            ->assertJsonPath('data.name', 'Merva Musteri Dagitimi')
            ->assertJsonPath('data.recipients_count', 2);

        $setupResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/delivery-setups', [
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'recipient_preset_id' => $presetId,
                'default_range_days' => 14,
                'layout_preset' => 'client_digest',
                'delivery_channel' => 'email_stub',
                'cadence' => 'weekly',
                'weekday' => 4,
                'send_time' => '11:30',
                'timezone' => 'Europe/Istanbul',
                'save_as_default_profile' => true,
                'auto_share_enabled' => true,
                'share_label_template' => '{template_name} / {end_date}',
                'share_expires_in_days' => 7,
                'share_allow_csv_download' => false,
            ]);

        $setupResponse->assertCreated()
            ->assertJsonPath('data.entity_type', 'account')
            ->assertJsonPath('data.recipient_preset_id', $presetId)
            ->assertJsonPath('data.profile_saved', true);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.recipient_preset_summary.total_presets', 1)
            ->assertJsonPath('data.recipient_presets.0.name', 'Merva Musteri Dagitimi')
            ->assertJsonPath('data.delivery_profile_summary.total_profiles', 1)
            ->assertJsonPath('data.delivery_profiles.0.entity_type', 'account')
            ->assertJsonPath('data.delivery_profiles.0.entity_id', $account->id)
            ->assertJsonPath('data.delivery_profiles.0.recipient_preset_id', $presetId)
            ->assertJsonPath('data.delivery_profiles.0.recipient_preset_name', 'Merva Musteri Dagitimi')
            ->assertJsonPath('data.delivery_profiles.0.recipients_count', 2)
            ->assertJsonPath('data.delivery_profiles.0.cadence', 'weekly')
            ->assertJsonPath('data.delivery_profiles.0.weekday', 4);
    }

    public function test_recipient_preset_can_be_updated_toggled_and_deleted(): void
    {
        [$workspace, $token] = $this->seedReportFixture('agency.admin@adscast.test');

        $createResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/recipient-presets', [
                'name' => 'Silinecek Preset',
                'recipients' => ['first@castintech.com'],
            ]);

        $presetId = $createResponse->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->putJson("/api/v1/reports/recipient-presets/{$presetId}", [
                'name' => 'Guncel Preset',
                'recipients' => ['first@castintech.com', 'second@castintech.com'],
                'notes' => 'Guncel liste',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Guncel Preset')
            ->assertJsonPath('data.recipients_count', 2);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/recipient-presets/{$presetId}/toggle", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->deleteJson("/api/v1/reports/recipient-presets/{$presetId}")
            ->assertOk();

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.recipient_preset_summary.total_presets', 0)
            ->assertJsonPath('data.recipient_presets', []);
    }

    /**
     * @return array{0: Workspace, 1: string, 2: MetaAdAccount, 3: Campaign}
     */
    private function seedReportFixture(string $email): array
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
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
            'account_id' => 'act_delivery',
            'name' => 'Castintech Delivery Account',
            'status' => 'active',
            'is_active' => true,
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_delivery',
            'name' => 'Delivery Engine Campaign',
            'objective' => 'LEADS',
            'status' => 'active',
            'is_active' => true,
            'daily_budget' => 250,
        ]);

        $adSet = AdSet::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'meta_ad_set_id' => 'adset_delivery',
            'name' => 'Delivery Prospecting',
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
            'meta_creative_id' => 'crt_delivery',
            'name' => 'Delivery Creative',
            'asset_type' => 'image',
            'headline' => 'Haftalik raporlar otomatik hazir',
            'body' => 'Musteri paylasimi icin rapor cikti foundation kaydi olusturulur.',
            'call_to_action' => 'LEARN_MORE',
            'destination_url' => 'https://example.com/delivery',
            'last_synced_at' => now(),
        ]);

        Ad::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'ad_set_id' => $adSet->id,
            'creative_id' => $creative->id,
            'meta_ad_id' => 'ad_delivery',
            'name' => 'Delivery Ad',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'preview_url' => 'https://example.com/preview/delivery',
            'last_synced_at' => now(),
        ]);

        foreach ([
            ['level' => 'campaign', 'external' => 'cmp_delivery', 'spend' => 960, 'results' => 21, 'ctr' => 3.1, 'cpm' => 22.5, 'frequency' => 1.6],
            ['level' => 'adset', 'external' => 'adset_delivery', 'spend' => 610, 'results' => 13, 'ctr' => 2.7, 'cpm' => 19.6, 'frequency' => 1.3],
            ['level' => 'ad', 'external' => 'ad_delivery', 'spend' => 420, 'results' => 9, 'ctr' => 3.4, 'cpm' => 17.9, 'frequency' => 1.1],
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
            'severity' => 'high',
            'summary' => 'Delivery Engine Campaign yakindan izlenmeli.',
            'explanation' => 'Yeni kreatif test cikti foundation ile rapora donusturulmeli.',
            'recommended_action' => 'Haftalik raporda kreatif test durumunu netlestirin.',
            'status' => 'open',
            'date_detected' => '2026-03-11',
        ]);

        Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'campaign',
            'target_id' => $campaign->id,
            'summary' => 'Musteri icin haftalik rapor sablonu olusturun.',
            'details' => 'Delivery foundation ile tekrar kullanilabilir rapor ritmi kurun.',
            'action_type' => 'ai_guidance',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => '2026-03-12 09:00:00',
            'metadata' => [
                'client_friendly_summary' => 'Raporlama ritmi duzenli hale geliyor.',
                'operator_notes' => 'Ayni account icin haftalik rapor teslimi tanimlayin.',
                'what_to_test_next' => 'Rapor sablonu + schedule',
                'budget_note' => 'Raporlama degisikligi butceyi etkilemez.',
            ],
        ]);

        return [$workspace, $token, $account, $campaign];
    }
}
