<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_viewer_cannot_manage_meta_connection(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'client@adscast.local',
            'password' => 'Password123!',
        ]);

        $token = $loginResponse->json('token');
        $workspaceId = collect($loginResponse->json('workspaces'))
            ->firstWhere('slug', 'demo-workspace')['id'] ?? null;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/meta/connections', [
                'access_token' => 'fake_access_token_that_is_long_enough',
                'api_version' => 'v20.0',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'permission_denied');
    }
}
