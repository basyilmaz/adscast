<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserWorkspaceRole;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_purges_legacy_demo_records(): void
    {
        $this->seed([RolePermissionSeeder::class]);

        $roleId = Role::query()->where('code', 'agency_admin')->value('id');

        $demoUser = User::factory()->create([
            'email' => 'legacy-user@adscast.local',
        ]);

        $organization = Organization::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo Agency',
            'slug' => 'demo-agency',
            'status' => 'active',
            'created_by' => $demoUser->id,
        ]);

        $workspace = Workspace::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'name' => 'Demo Workspace',
            'slug' => 'demo-workspace',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'USD',
            'is_active' => true,
            'created_by' => $demoUser->id,
        ]);

        UserWorkspaceRole::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $demoUser->id,
            'workspace_id' => $workspace->id,
            'role_id' => $roleId,
            'assigned_by' => $demoUser->id,
        ]);

        $demoUser->createToken('legacy-demo-token');

        AuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'actor_id' => $demoUser->id,
            'action' => 'legacy.demo.action',
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
            'metadata' => ['legacy' => true],
            'occurred_at' => now(),
        ]);

        Setting::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'key' => 'legacy.demo.setting',
            'value' => ['legacy' => true],
            'is_encrypted' => false,
            'created_by' => $demoUser->id,
        ]);

        $this->artisan('adscast:purge-demo-data --force')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseMissing('users', ['id' => $demoUser->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('settings', 0);
    }
}
