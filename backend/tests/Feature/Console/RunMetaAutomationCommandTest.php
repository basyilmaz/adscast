<?php

namespace Tests\Feature\Console;

use App\Models\AIGeneration;
use App\Models\Alert;
use App\Models\MetaConnection;
use App\Models\Recommendation;
use App\Models\SyncRun;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunMetaAutomationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_scheduled_meta_automation_chain(): void
    {
        $this->seed([RolePermissionSeeder::class]);

        $workspace = Workspace::factory()->create();
        $connection = MetaConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'last_synced_at' => now()->subDays(2),
        ]);

        $this->artisan('adscast:run-meta-automation', [
            '--workspace-id' => $workspace->id,
            '--no-lock' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('sync_runs', [
            'meta_connection_id' => $connection->id,
            'type' => 'asset_sync',
            'status' => 'completed',
        ]);

        $this->assertGreaterThan(0, SyncRun::query()->where('meta_connection_id', $connection->id)->where('type', 'insights_daily_sync')->count());
        $this->assertGreaterThan(0, Alert::query()->where('workspace_id', $workspace->id)->count());
        $this->assertGreaterThan(0, Recommendation::query()->where('workspace_id', $workspace->id)->count());
        $this->assertGreaterThan(0, AIGeneration::query()->where('workspace_id', $workspace->id)->count());
    }

    public function test_it_skips_recent_steps_without_force(): void
    {
        $this->seed([RolePermissionSeeder::class]);

        $workspace = Workspace::factory()->create();
        $connection = MetaConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'last_synced_at' => now()->subMinutes(30),
        ]);

        SyncRun::query()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'type' => 'asset_sync',
            'status' => 'completed',
            'request_fingerprint' => 'recent-asset-sync',
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(29),
        ]);

        SyncRun::query()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'type' => 'insights_daily_sync',
            'status' => 'completed',
            'request_fingerprint' => 'recent-insights-sync',
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(29),
        ]);

        AIGeneration::query()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'workspace',
            'entity_id' => $workspace->id,
            'provider' => 'mock',
            'model' => 'gpt-4.1-mini',
            'prompt_template' => 'workspace_weekly_summary_v1',
            'prompt_context' => ['workspace_id' => $workspace->id],
            'prompt_text' => 'recent recommendation',
            'output' => ['provider' => 'mock'],
            'status' => 'succeeded',
            'generated_at' => now()->subMinutes(30),
        ]);

        $this->artisan('adscast:run-meta-automation', [
            '--workspace-id' => $workspace->id,
            '--no-lock' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, SyncRun::query()->where('meta_connection_id', $connection->id)->where('type', 'asset_sync')->count());
        $this->assertSame(1, SyncRun::query()->where('meta_connection_id', $connection->id)->where('type', 'insights_daily_sync')->count());
        $this->assertSame(1, AIGeneration::query()->where('workspace_id', $workspace->id)->count());
    }
}
