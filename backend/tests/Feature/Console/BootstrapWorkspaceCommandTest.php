<?php

namespace Tests\Feature\Console;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserWorkspaceRole;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapWorkspaceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bootstraps_admin_organization_and_workspace(): void
    {
        $this->seed([RolePermissionSeeder::class]);

        $this->artisan('adscast:bootstrap-workspace', [
            '--admin-email' => 'admin@castintech.com',
            '--admin-password' => 'Password123!',
            '--organization-name' => 'Castintech',
            '--organization-slug' => 'castintech',
            '--workspace-name' => 'Castintech Main',
            '--workspace-slug' => 'castintech-main',
            '--currency' => 'TRY',
            '--force' => true,
        ])->assertExitCode(0);

        $admin = User::query()->where('email', 'admin@castintech.com')->first();
        $organization = Organization::query()->where('slug', 'castintech')->first();
        $workspace = Workspace::query()->where('slug', 'castintech-main')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($organization);
        $this->assertNotNull($workspace);
        $this->assertTrue((bool) $admin->is_active);
        $this->assertDatabaseHas('user_workspace_roles', [
            'user_id' => $admin->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_it_is_idempotent_for_existing_bootstrap_records(): void
    {
        $this->seed([RolePermissionSeeder::class]);

        $command = [
            '--admin-email' => 'admin@castintech.com',
            '--admin-password' => 'Password123!',
            '--organization-slug' => 'castintech',
            '--workspace-slug' => 'castintech-main',
            '--force' => true,
        ];

        $this->artisan('adscast:bootstrap-workspace', $command)->assertExitCode(0);
        $this->artisan('adscast:bootstrap-workspace', $command)->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertSame(1, UserWorkspaceRole::query()->count());
    }
}
