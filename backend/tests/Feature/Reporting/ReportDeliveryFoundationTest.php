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
use App\Models\ReportContact;
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
            ->assertJsonPath('data.recipients_count', 2)
            ->assertJsonPath('data.resolved_recipients_count', 2);

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
            ->assertJsonPath('data.delivery_profiles.0.recipients_count', 0)
            ->assertJsonPath('data.delivery_profiles.0.resolved_recipients_count', 2)
            ->assertJsonPath('data.delivery_profiles.0.recipient_group_summary.mode', 'preset')
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
                'contact_tags' => ['brand-core'],
                'notes' => 'Guncel liste',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Guncel Preset')
            ->assertJsonPath('data.recipients_count', 2)
            ->assertJsonPath('data.contact_tags.0', 'brand-core');

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

    public function test_segment_backed_recipient_preset_can_drive_delivery_setup_and_schedule_resolution(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Core Client',
            'email' => 'core.client@castintech.com',
            'tags' => ['brand-core'],
            'is_primary' => true,
            'is_active' => true,
        ]);

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Core Stakeholder',
            'email' => 'core.stakeholder@castintech.com',
            'tags' => ['brand-core'],
            'is_primary' => false,
            'is_active' => true,
        ]);

        $presetResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/recipient-presets', [
                'name' => 'Brand Core Group',
                'recipients' => ['ops@castintech.com'],
                'contact_tags' => ['brand-core'],
            ]);

        $presetId = $presetResponse->json('data.id');

        $presetResponse->assertCreated()
            ->assertJsonPath('data.contact_tags.0', 'brand-core')
            ->assertJsonPath('data.tagged_contacts_count', 2)
            ->assertJsonPath('data.resolved_recipients_count', 3);

        $setupResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/delivery-setups', [
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'recipient_preset_id' => $presetId,
                'default_range_days' => 7,
                'layout_preset' => 'client_digest',
                'delivery_channel' => 'email_stub',
                'cadence' => 'weekly',
                'weekday' => 2,
                'send_time' => '09:30',
                'timezone' => 'Europe/Istanbul',
                'save_as_default_profile' => true,
                'auto_share_enabled' => false,
            ]);

        $scheduleId = $setupResponse->json('data.schedule_id');

        $setupResponse->assertCreated()
            ->assertJsonPath('data.recipient_preset_id', $presetId)
            ->assertJsonPath('data.recipient_group_summary.mode', 'preset_plus_segment')
            ->assertJsonPath('data.recipient_group_summary.resolved_recipients_count', 3);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.recipient_presets.0.recipient_group_summary.mode', 'manual_plus_segment')
            ->assertJsonPath('data.delivery_schedules.0.recipient_preset_id', $presetId)
            ->assertJsonPath('data.delivery_schedules.0.recipient_group_summary.mode', 'preset_plus_segment')
            ->assertJsonPath('data.delivery_schedules.0.resolved_recipients_count', 3)
            ->assertJsonPath('data.delivery_profiles.0.recipient_group_summary.mode', 'preset_plus_segment')
            ->assertJsonPath('data.delivery_profiles.0.resolved_recipients_count', 3);

        $runResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/delivery-schedules/{$scheduleId}/run-now");

        $runResponse->assertOk()
            ->assertJsonPath('data.status', 'delivered_stub');

        $run = ReportDeliveryRun::query()->where('report_delivery_schedule_id', $scheduleId)->latest()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['ops@castintech.com', 'core.client@castintech.com', 'core.stakeholder@castintech.com'],
            $run->recipients ?? [],
        );
    }

    public function test_reports_index_exposes_recipient_group_catalog(): void
    {
        [$workspace, $token] = $this->seedReportFixture('agency.admin@adscast.test');

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Primary Client',
            'email' => 'primary.client@castintech.com',
            'company_name' => 'Castintech',
            'tags' => ['growth-core'],
            'is_primary' => true,
            'is_active' => true,
        ]);

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Secondary Client',
            'email' => 'secondary.client@castintech.com',
            'company_name' => 'Castintech',
            'tags' => ['growth-core'],
            'is_primary' => false,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/recipient-presets', [
                'name' => 'Growth Core Group',
                'recipients' => ['ops@castintech.com'],
                'contact_tags' => ['growth-core'],
                'template_kind' => 'stakeholder_update',
                'target_entity_types' => ['account'],
                'matching_companies' => ['Castintech'],
                'priority' => 85,
                'is_recommended_default' => true,
            ])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $response->assertOk()
            ->assertJsonPath('data.recipient_group_catalog_summary.total_groups', 4)
            ->assertJsonPath('data.recipient_group_catalog_summary.preset_groups', 1)
            ->assertJsonPath('data.recipient_group_catalog_summary.segment_groups', 1)
            ->assertJsonPath('data.recipient_group_catalog_summary.smart_groups', 2)
            ->assertJsonPath('data.recipient_preset_summary.managed_templates', 1)
            ->assertJsonPath('data.recipient_preset_summary.recommended_default_presets', 1)
            ->assertJsonPath('data.recipient_presets.0.template_profile.kind', 'stakeholder_update')
            ->assertJsonPath('data.recipient_presets.0.template_profile.target_entity_scope_label', 'Reklam Hesabi')
            ->assertJsonPath('data.recipient_presets.0.template_profile.company_scope_label', 'Castintech')
            ->assertJsonPath('data.recipient_presets.0.template_profile.priority', 85)
            ->assertJsonPath('data.recipient_presets.0.template_profile.is_recommended_default', true)
            ->assertJsonPath('data.recipient_group_catalog.0.catalog_section', 'Alici Grubu Sablonlari')
            ->assertJsonPath('data.recipient_group_catalog.0.source_type', 'preset')
            ->assertJsonPath('data.recipient_group_catalog.0.recipient_group_summary.mode', 'preset_plus_segment')
            ->assertJsonPath('data.recipient_group_catalog.1.source_type', 'segment')
            ->assertJsonFragment([
                'source_type' => 'smart',
                'source_subtype' => 'company',
                'name' => 'Castintech Musteri Grubu',
                'resolved_recipients_count' => 2,
            ])
            ->assertJsonFragment([
                'source_type' => 'smart',
                'source_subtype' => 'primary',
                'name' => 'Primary Musteri Kisileri',
            ]);
    }

    public function test_recipient_group_suggestions_endpoint_returns_entity_scoped_suggestions(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Account Client Lead',
            'email' => 'account.client.lead@castintech.com',
            'company_name' => $account->name,
            'tags' => ['delivery-account-core'],
            'is_primary' => true,
            'is_active' => true,
        ]);

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Account Client Ops',
            'email' => 'account.client.ops@castintech.com',
            'company_name' => $account->name,
            'tags' => ['delivery-account-core'],
            'is_primary' => false,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/recipient-presets', [
                'name' => 'Delivery Account Core Group',
                'recipients' => ['ops@castintech.com'],
                'contact_tags' => ['delivery-account-core'],
                'template_kind' => 'client_reporting',
                'target_entity_types' => ['account'],
                'matching_companies' => [$account->name],
                'priority' => 90,
                'is_recommended_default' => true,
            ])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson(sprintf(
                '/api/v1/reports/recipient-group-suggestions?entity_type=account&entity_id=%s&limit=4',
                $account->id,
            ));

        $response->assertOk()
            ->assertJsonPath('data.entity_type', 'account')
            ->assertJsonPath('data.entity_id', $account->id)
            ->assertJsonPath('data.summary.total_suggestions', 4)
            ->assertJsonPath('data.summary.top_source_type', 'preset')
            ->assertJsonPath('data.suggested_groups.0.name', 'Delivery Account Core Group')
            ->assertJsonPath('data.suggested_groups.0.template_profile.priority', 90)
            ->assertJsonPath('data.suggested_groups.0.template_profile.is_recommended_default', true)
            ->assertJsonFragment([
                'source_type' => 'smart',
                'source_subtype' => 'company',
                'name' => sprintf('%s Musteri Grubu', $account->name),
                'resolved_recipients_count' => 2,
            ]);
    }

    public function test_delivery_profile_can_be_managed_from_entity_endpoints(): void
    {
        [$workspace, $token, $account, $campaign] = $this->seedReportFixture('agency.admin@adscast.test');

        $presetResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/recipient-presets', [
                'name' => 'Detail Yonetim Preseti',
                'recipients' => ['client@castintech.com', 'ops@castintech.com'],
            ]);

        $presetId = $presetResponse->json('data.id');

        $accountProfileResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->putJson("/api/v1/reports/delivery-profiles/account/{$account->id}", [
                'recipient_preset_id' => $presetId,
                'delivery_channel' => 'email_stub',
                'cadence' => 'weekly',
                'weekday' => 3,
                'send_time' => '09:30',
                'timezone' => 'Europe/Istanbul',
                'default_range_days' => 14,
                'layout_preset' => 'client_digest',
                'auto_share_enabled' => true,
                'share_label_template' => '{template_name} / {end_date}',
                'share_expires_in_days' => 7,
                'share_allow_csv_download' => false,
            ]);

        $accountProfileResponse->assertOk()
            ->assertJsonPath('data.entity_type', 'account')
            ->assertJsonPath('data.entity_id', $account->id)
            ->assertJsonPath('data.recipient_preset_id', $presetId)
            ->assertJsonPath('data.recipients_count', 0);

        $campaignProfileResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->putJson("/api/v1/reports/delivery-profiles/campaign/{$campaign->id}", [
                'delivery_channel' => 'email_stub',
                'cadence' => 'monthly',
                'month_day' => 5,
                'send_time' => '08:45',
                'timezone' => 'Europe/Istanbul',
                'default_range_days' => 7,
                'layout_preset' => 'client_digest',
                'recipients' => ['owner@castintech.com'],
                'auto_share_enabled' => true,
                'share_label_template' => '{template_name} / {end_date}',
                'share_expires_in_days' => 14,
                'share_allow_csv_download' => true,
            ]);

        $campaignProfileResponse->assertOk()
            ->assertJsonPath('data.entity_type', 'campaign')
            ->assertJsonPath('data.entity_id', $campaign->id)
            ->assertJsonPath('data.recipients_count', 1)
            ->assertJsonPath('data.share_delivery.allow_csv_download', true);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/delivery-profiles/account/{$account->id}/toggle", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $accountDetailResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/meta/ad-accounts/{$account->id}?start_date=2026-03-10&end_date=2026-03-16");

        $accountDetailResponse->assertOk()
            ->assertJsonPath('data.delivery_profile.entity_type', 'account')
            ->assertJsonPath('data.delivery_profile.is_active', false)
            ->assertJsonPath('data.delivery_profile.recipient_preset_name', 'Detail Yonetim Preseti');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->deleteJson("/api/v1/reports/delivery-profiles/campaign/{$campaign->id}")
            ->assertOk();

        $campaignDetailResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/campaigns/{$campaign->id}?start_date=2026-03-10&end_date=2026-03-16");

        $campaignDetailResponse->assertOk()
            ->assertJsonPath('data.delivery_profile', null);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_delivery_profile_upserted',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_delivery_profile_toggled',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_delivery_profile_deleted',
        ]);
    }

    public function test_suggested_delivery_profile_apply_payload_can_be_applied_directly(): void
    {
        [$workspace, $token, $account, $campaign] = $this->seedReportFixture('agency.admin@adscast.test');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/recipient-presets', [
                'name' => 'Managed Suggestion Apply Preset',
                'recipients' => ['client@castintech.com'],
                'template_kind' => 'stakeholder_update',
                'target_entity_types' => ['account'],
                'priority' => 95,
                'is_recommended_default' => true,
            ])
            ->assertCreated();

        $detailResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/meta/ad-accounts/{$account->id}?start_date=2026-03-10&end_date=2026-03-16");

        $payload = $detailResponse->json('data.suggested_delivery_profile.apply_payload');

        $this->assertIsArray($payload);
        $this->assertSame([], $payload['recipients']);
        $this->assertSame([], $payload['contact_tags']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->putJson("/api/v1/reports/delivery-profiles/account/{$account->id}", array_merge($payload, [
                'is_active' => true,
            ]))
            ->assertOk()
            ->assertJsonPath('data.entity_type', 'account')
            ->assertJsonPath('data.recipient_preset_name', 'Managed Suggestion Apply Preset');

        $afterResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson("/api/v1/meta/ad-accounts/{$account->id}?start_date=2026-03-10&end_date=2026-03-16");

        $afterResponse->assertOk()
            ->assertJsonPath('data.suggested_delivery_profile.status', 'already_applied')
            ->assertJsonPath('data.delivery_profile.recipient_preset_name', 'Managed Suggestion Apply Preset');
    }

    public function test_reports_index_includes_delivery_history_summary_and_retryable_failed_runs(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'History Account Delivery',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'report_type' => 'client_account_summary_v1',
            'default_range_days' => 7,
            'layout_preset' => 'client_digest',
            'is_active' => true,
        ]);

        $schedule = ReportDeliverySchedule::query()->create([
            'workspace_id' => $workspace->id,
            'report_template_id' => $template->id,
            'delivery_channel' => 'email_stub',
            'cadence' => 'weekly',
            'weekday' => 2,
            'send_time' => '09:00',
            'timezone' => 'Europe/Istanbul',
            'recipients' => ['client@castintech.com'],
            'configuration' => [],
            'is_active' => true,
            'next_run_at' => now()->addDay(),
        ]);

        ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $schedule->id,
            'delivery_channel' => 'email_stub',
            'status' => 'delivered_stub',
            'recipients' => ['client@castintech.com'],
            'prepared_at' => now()->subHour(),
            'delivered_at' => now()->subHour()->addMinute(),
            'trigger_mode' => 'manual',
            'metadata' => [
                'delivery' => [
                    'channel' => 'email_stub',
                    'channel_label' => 'Email Stub',
                    'mailer' => null,
                    'recipients' => ['client@castintech.com'],
                    'recipients_count' => 1,
                    'share_link_used' => false,
                    'outbound' => false,
                ],
            ],
        ]);

        $failedRun = ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $schedule->id,
            'delivery_channel' => 'email',
            'status' => 'failed',
            'recipients' => ['client@castintech.com'],
            'prepared_at' => now()->subMinutes(15),
            'trigger_mode' => 'scheduled',
            'error_message' => 'SMTP timeout',
            'metadata' => [
                'delivery' => [
                    'channel' => 'email',
                    'channel_label' => 'Gercek Email',
                    'mailer' => 'smtp',
                    'recipients' => ['client@castintech.com'],
                    'recipients_count' => 1,
                    'share_link_used' => false,
                    'outbound' => true,
                ],
            ],
        ]);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.delivery_run_summary.total_runs', 2)
            ->assertJsonPath('data.delivery_run_summary.failed_runs', 1)
            ->assertJsonPath('data.delivery_run_summary.delivered_runs', 1)
            ->assertJsonPath('data.delivery_run_summary.retryable_runs', 1)
            ->assertJsonPath('data.delivery_runs.0.id', $failedRun->id)
            ->assertJsonPath('data.delivery_runs.0.can_retry', true)
            ->assertJsonPath('data.delivery_runs.0.error_message', 'SMTP timeout')
            ->assertJsonPath('data.delivery_runs.0.schedule.template.name', 'History Account Delivery');
    }

    public function test_failed_delivery_run_can_be_retried_from_history_endpoint(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Retryable Delivery Template',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'report_type' => 'client_account_summary_v1',
            'default_range_days' => 7,
            'layout_preset' => 'client_digest',
            'is_active' => true,
        ]);

        $schedule = ReportDeliverySchedule::query()->create([
            'workspace_id' => $workspace->id,
            'report_template_id' => $template->id,
            'delivery_channel' => 'email_stub',
            'cadence' => 'weekly',
            'weekday' => 3,
            'send_time' => '09:00',
            'timezone' => 'Europe/Istanbul',
            'recipients' => ['client@castintech.com'],
            'configuration' => [],
            'is_active' => true,
            'next_run_at' => now()->addHour(),
        ]);

        $failedRun = ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $schedule->id,
            'delivery_channel' => 'email_stub',
            'status' => 'failed',
            'recipients' => ['client@castintech.com'],
            'prepared_at' => now()->subMinutes(5),
            'trigger_mode' => 'scheduled',
            'error_message' => 'Manual retry bekliyor',
            'metadata' => [],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/delivery-runs/{$failedRun->id}/retry");

        $retryRunId = $response->json('data.run_id');

        $response->assertOk()
            ->assertJsonPath('data.retry_of_run_id', $failedRun->id)
            ->assertJsonPath('data.status', 'delivered_stub');

        $this->assertDatabaseHas('report_delivery_runs', [
            'id' => $retryRunId,
            'report_delivery_schedule_id' => $schedule->id,
            'trigger_mode' => 'retry',
            'status' => 'delivered_stub',
        ]);

        $failedRun->refresh();

        $this->assertSame($retryRunId, data_get($failedRun->metadata, 'retry.next_run_id'));
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_delivery_run_retried',
            'target_id' => $retryRunId,
        ]);
    }

    public function test_reports_index_includes_recipient_group_analytics(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Recipient Group Analytics Template',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'report_type' => 'client_account_summary_v1',
            'default_range_days' => 14,
            'layout_preset' => 'client_digest',
            'is_active' => true,
        ]);

        $selectionPayload = [
            'id' => 'smart:company:castintech',
            'source_type' => 'smart',
            'source_subtype' => 'company',
            'source_id' => 'company:castintech',
            'name' => 'Castintech Musteri Grubu',
        ];

        $scheduleResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/delivery-schedules', [
                'report_template_id' => $template->id,
                'delivery_channel' => 'email_stub',
                'cadence' => 'weekly',
                'weekday' => 2,
                'send_time' => '09:30',
                'timezone' => 'Europe/Istanbul',
                'recipients' => ['client@castintech.com', 'ops@castintech.com'],
                'recipient_group_selection' => $selectionPayload,
            ]);

        $scheduleId = $scheduleResponse->json('data.id');

        $scheduleResponse->assertCreated()
            ->assertJsonPath('data.id', $scheduleId);

        $schedule = ReportDeliverySchedule::query()->findOrFail($scheduleId);

        $this->assertSame('smart', data_get($schedule->configuration, 'recipient_group_selection.source_type'));
        $this->assertSame('Castintech Musteri Grubu', data_get($schedule->configuration, 'recipient_group_selection.name'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/delivery-schedules/{$scheduleId}/run-now")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered_stub');

        $deliveredRun = ReportDeliveryRun::query()
            ->where('report_delivery_schedule_id', $scheduleId)
            ->latest('prepared_at')
            ->firstOrFail();

        $this->assertSame('smart', data_get($deliveredRun->metadata, 'recipient_group_selection.source_type'));
        $this->assertSame('Castintech Musteri Grubu', data_get($deliveredRun->metadata, 'recipient_group_selection.name'));

        ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $scheduleId,
            'delivery_channel' => 'email',
            'status' => 'failed',
            'recipients' => ['client@castintech.com', 'ops@castintech.com'],
            'prepared_at' => now()->subMinutes(5),
            'trigger_mode' => 'scheduled',
            'error_message' => 'SMTP timeout',
            'metadata' => [
                'recipient_group_selection' => $selectionPayload,
                'recipient_group_summary' => [
                    'mode' => 'manual',
                    'label' => 'Castintech Musteri Grubu',
                    'preset_name' => null,
                    'contact_tags' => [],
                    'static_recipients_count' => 2,
                    'manual_recipients_count' => 2,
                    'preset_recipients_count' => 0,
                    'dynamic_contacts_count' => 0,
                    'resolved_recipients_count' => 2,
                    'sample_contact_names' => [],
                ],
                'delivery' => [
                    'channel' => 'email',
                    'channel_label' => 'Gercek Email',
                    'mailer' => 'smtp',
                    'recipients' => ['client@castintech.com', 'ops@castintech.com'],
                    'recipients_count' => 2,
                    'share_link_used' => false,
                    'outbound' => true,
                ],
            ],
        ]);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.recipient_group_analytics_summary.total_groups', 1)
            ->assertJsonPath('data.recipient_group_analytics_summary.smart_groups', 1)
            ->assertJsonPath('data.recipient_group_analytics_summary.groups_with_failures', 1)
            ->assertJsonPath('data.recipient_group_analytics_summary.most_used_group_label', 'Castintech Musteri Grubu')
            ->assertJsonPath('data.recipient_group_analytics_summary.highest_failure_group_label', 'Castintech Musteri Grubu')
            ->assertJsonPath('data.recipient_group_analytics.0.source_type', 'smart')
            ->assertJsonPath('data.recipient_group_analytics.0.source_subtype', 'company')
            ->assertJsonPath('data.recipient_group_analytics.0.configured_schedules_count', 1)
            ->assertJsonPath('data.recipient_group_analytics.0.run_uses_count', 2)
            ->assertJsonPath('data.recipient_group_analytics.0.successful_runs', 1)
            ->assertJsonPath('data.recipient_group_analytics.0.failed_runs', 1)
            ->assertJsonPath('data.recipient_group_analytics.0.unique_entities_count', 1)
            ->assertJsonPath('data.recipient_group_analytics.0.entities.0.entity_type', 'account')
            ->assertJsonPath('data.recipient_group_analytics.0.sample_recipients.0', 'client@castintech.com');
    }

    public function test_reports_index_includes_recipient_group_alignment_summary(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Recipient Group Alignment Template',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'report_type' => 'client_account_summary_v1',
            'default_range_days' => 7,
            'layout_preset' => 'client_digest',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/delivery-schedules', [
                'report_template_id' => $template->id,
                'delivery_channel' => 'email_stub',
                'cadence' => 'weekly',
                'weekday' => 4,
                'send_time' => '10:15',
                'timezone' => 'Europe/Istanbul',
                'recipients' => ['override@castintech.com'],
                'recipient_group_selection' => [
                    'id' => 'manual:override-group',
                    'source_type' => 'manual',
                    'source_subtype' => null,
                    'source_id' => 'custom:override-group',
                    'name' => 'Operator Override Grubu',
                ],
                'recommended_recipient_group' => [
                    'id' => 'smart:company:castintech',
                    'source_type' => 'smart',
                    'source_subtype' => 'company',
                    'source_id' => 'company:castintech',
                    'name' => 'Castintech Onerilen Grup',
                ],
            ]);

        $response->assertCreated();

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.recipient_group_alignment_summary.tracked_decisions', 1)
            ->assertJsonPath('data.recipient_group_alignment_summary.aligned_decisions', 0)
            ->assertJsonPath('data.recipient_group_alignment_summary.overridden_decisions', 1)
            ->assertJsonPath('data.recipient_group_alignment_summary.top_overridden_recommended_group_label', 'Castintech Onerilen Grup')
            ->assertJsonPath('data.recipient_group_alignment_summary.top_selected_override_group_label', 'Operator Override Grubu')
            ->assertJsonPath('data.recipient_group_alignment.0.alignment.status', 'override')
            ->assertJsonPath('data.recipient_group_alignment.0.selected_group.name', 'Operator Override Grubu')
            ->assertJsonPath('data.recipient_group_alignment.0.recommended_group.name', 'Castintech Onerilen Grup')
            ->assertJsonPath('data.recipient_group_alignment.0.entity_type', 'account');
    }

    public function test_reports_index_includes_recipient_group_correlation_summary(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Recipient Group Correlation Template',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'report_type' => 'client_account_summary_v1',
            'default_range_days' => 7,
            'layout_preset' => 'client_digest',
            'is_active' => true,
        ]);

        $alignedSelection = [
            'id' => 'smart:company:castintech',
            'source_type' => 'smart',
            'source_subtype' => 'company',
            'source_id' => 'company:castintech',
            'name' => 'Castintech Onerilen Grup',
        ];
        $overrideSelection = [
            'id' => 'manual:override-group',
            'source_type' => 'manual',
            'source_subtype' => null,
            'source_id' => 'custom:override-group',
            'name' => 'Operator Override Grubu',
        ];

        $schedule = ReportDeliverySchedule::query()->create([
            'workspace_id' => $workspace->id,
            'report_template_id' => $template->id,
            'delivery_channel' => 'email_stub',
            'cadence' => 'weekly',
            'weekday' => 2,
            'send_time' => '10:00',
            'timezone' => 'Europe/Istanbul',
            'recipients' => ['client@castintech.com'],
            'configuration' => [
                'recipient_group_selection' => $overrideSelection,
                'recommended_recipient_group' => $alignedSelection,
            ],
            'is_active' => true,
            'next_run_at' => now()->addDay(),
        ]);

        ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $schedule->id,
            'delivery_channel' => 'email_stub',
            'status' => 'delivered_stub',
            'recipients' => ['client@castintech.com'],
            'prepared_at' => now()->subMinutes(10),
            'delivered_at' => now()->subMinutes(9),
            'trigger_mode' => 'scheduled',
            'metadata' => [
                'recipient_group_selection' => $alignedSelection,
                'recommended_recipient_group' => $alignedSelection,
            ],
        ]);

        ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $schedule->id,
            'delivery_channel' => 'email_stub',
            'status' => 'failed',
            'recipients' => ['client@castintech.com'],
            'prepared_at' => now()->subMinutes(5),
            'trigger_mode' => 'scheduled',
            'error_message' => 'SMTP timeout',
            'metadata' => [
                'recipient_group_selection' => $overrideSelection,
                'recommended_recipient_group' => $alignedSelection,
            ],
        ]);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.recipient_group_correlation_summary.tracked_runs', 2)
            ->assertJsonPath('data.recipient_group_correlation_summary.aligned_runs', 1)
            ->assertJsonPath('data.recipient_group_correlation_summary.overridden_runs', 1)
            ->assertJsonPath('data.recipient_group_correlation_summary.aligned_success_rate', 100)
            ->assertJsonPath('data.recipient_group_correlation_summary.override_success_rate', 0)
            ->assertJsonPath('data.recipient_group_correlation_summary.success_rate_gap', 100)
            ->assertJsonPath('data.recipient_group_correlation_summary.recommendation_outperforming_groups', 1)
            ->assertJsonPath('data.recipient_group_correlation_summary.top_positive_recommended_group_label', 'Castintech Onerilen Grup')
            ->assertJsonPath('data.recipient_group_correlation.0.label', 'Castintech Onerilen Grup')
            ->assertJsonPath('data.recipient_group_correlation.0.correlation_status', 'recommendation_outperforms')
            ->assertJsonPath('data.recipient_group_correlation.0.top_override_group_label', 'Operator Override Grubu')
            ->assertJsonPath('data.recipient_group_correlation.0.aligned_failed_runs', 0)
            ->assertJsonPath('data.recipient_group_correlation.0.override_failed_runs', 1)
            ->assertJsonPath('data.recipient_group_correlation.0.entities.0.entity_type', 'account');
    }

    public function test_only_failed_delivery_runs_can_be_retried(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Non Retry Delivery Template',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'report_type' => 'client_account_summary_v1',
            'default_range_days' => 7,
            'layout_preset' => 'client_digest',
            'is_active' => true,
        ]);

        $schedule = ReportDeliverySchedule::query()->create([
            'workspace_id' => $workspace->id,
            'report_template_id' => $template->id,
            'delivery_channel' => 'email_stub',
            'cadence' => 'weekly',
            'weekday' => 2,
            'send_time' => '09:00',
            'timezone' => 'Europe/Istanbul',
            'recipients' => ['client@castintech.com'],
            'configuration' => [],
            'is_active' => true,
            'next_run_at' => now()->addDay(),
        ]);

        $deliveredRun = ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $schedule->id,
            'delivery_channel' => 'email_stub',
            'status' => 'delivered_stub',
            'recipients' => ['client@castintech.com'],
            'prepared_at' => now()->subMinute(),
            'delivered_at' => now(),
            'trigger_mode' => 'manual',
            'metadata' => [],
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/delivery-runs/{$deliveredRun->id}/retry")
            ->assertUnprocessable()
            ->assertJsonPath('errors.run_id.0', 'Sadece basarisiz teslim kayitlari tekrar denenebilir.');
    }

    public function test_delivery_schedule_can_resolve_dynamic_recipients_from_contact_tags(): void
    {
        [$workspace, $token, $account] = $this->seedReportFixture('agency.admin@adscast.test');

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client Main',
            'email' => 'client.main@castintech.com',
            'tags' => ['client', 'weekly'],
            'is_primary' => true,
            'is_active' => true,
        ]);

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client Ops',
            'email' => 'client.ops@castintech.com',
            'tags' => ['client'],
            'is_primary' => false,
            'is_active' => true,
        ]);

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Inactive Client',
            'email' => 'inactive.client@castintech.com',
            'tags' => ['client'],
            'is_primary' => false,
            'is_active' => false,
        ]);

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Tag Delivery Template',
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
                'delivery_channel' => 'email_stub',
                'cadence' => 'weekly',
                'weekday' => 2,
                'send_time' => '10:00',
                'timezone' => 'Europe/Istanbul',
                'contact_tags' => ['client'],
                'auto_share_enabled' => false,
            ]);

        $scheduleId = $scheduleResponse->json('data.id');

        $scheduleResponse->assertCreated()
            ->assertJsonPath('data.id', $scheduleId);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.delivery_schedules.0.contact_tags.0', 'client')
            ->assertJsonPath('data.delivery_schedules.0.tagged_contacts_count', 2)
            ->assertJsonPath('data.delivery_schedules.0.resolved_recipients_count', 2)
            ->assertJsonPath('data.contact_segment_summary.total_segments', 2);

        $runResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/delivery-schedules/{$scheduleId}/run-now");

        $runResponse->assertOk()
            ->assertJsonPath('data.status', 'delivered_stub');

        $run = ReportDeliveryRun::query()->where('report_delivery_schedule_id', $scheduleId)->latest()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['client.main@castintech.com', 'client.ops@castintech.com'],
            $run->recipients ?? [],
        );

        $this->assertNotNull(ReportContact::query()->where('email', 'client.main@castintech.com')->value('last_used_at'));
        $this->assertNotNull(ReportContact::query()->where('email', 'client.ops@castintech.com')->value('last_used_at'));
        $this->assertNull(ReportContact::query()->where('email', 'inactive.client@castintech.com')->value('last_used_at'));
    }

    public function test_quick_delivery_setup_can_store_contact_tags_on_default_profile(): void
    {
        [$workspace, $token, , $campaign] = $this->seedReportFixture('agency.admin@adscast.test');

        ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Campaign Client',
            'email' => 'campaign.client@castintech.com',
            'tags' => ['campaign-client'],
            'is_primary' => true,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/delivery-setups', [
                'entity_type' => 'campaign',
                'entity_id' => $campaign->id,
                'default_range_days' => 7,
                'layout_preset' => 'client_digest',
                'delivery_channel' => 'email_stub',
                'cadence' => 'weekly',
                'weekday' => 4,
                'send_time' => '11:00',
                'timezone' => 'Europe/Istanbul',
                'contact_tags' => ['campaign-client'],
                'save_as_default_profile' => true,
                'auto_share_enabled' => true,
                'share_label_template' => '{template_name} / {end_date}',
                'share_expires_in_days' => 7,
                'share_allow_csv_download' => false,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.profile_saved', true)
            ->assertJsonPath('data.contact_tags.0', 'campaign-client');

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.delivery_profiles.0.contact_tags.0', 'campaign-client')
            ->assertJsonPath('data.delivery_profiles.0.resolved_recipients_count', 1)
            ->assertJsonPath('data.delivery_profiles.0.recipient_group_summary.mode', 'segment')
            ->assertJsonPath('data.delivery_profiles.0.recipient_group_summary.dynamic_contacts_count', 1);
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
