<?php

namespace Tests\Feature\Tenants;

use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\Organization;
use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_unassigned_workspace_context(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'account.manager@adscast.test',
            'password' => 'Password123!',
        ]);

        $token = $loginResponse->json('token');

        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'isolated-org'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Isolated Org',
                'status' => 'active',
            ]
        );

        $isolatedWorkspace = Workspace::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'name' => 'Isolated Workspace',
            'slug' => 'isolated-workspace',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $connection = MetaConnection::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $isolatedWorkspace->id,
            'provider' => 'meta',
            'api_version' => 'v20.0',
            'status' => 'active',
            'access_token_encrypted' => 'encrypted_token',
        ]);

        $account = MetaAdAccount::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $isolatedWorkspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_9999',
            'name' => 'Isolated Account',
            'status' => 'active',
            'is_active' => true,
        ]);

        Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $isolatedWorkspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_isolated_1',
            'name' => 'Isolated Campaign',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $isolatedWorkspace->id)
            ->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'workspace_membership_missing');
    }
}
