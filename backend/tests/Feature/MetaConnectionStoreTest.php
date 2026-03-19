<?php

namespace Tests\Feature;

use App\Models\MetaConnection;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaConnectionStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_mode_connection_store_validates_token_and_persists_profile_metadata(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        config()->set('services.meta.mode', 'live');
        config()->set('services.meta.graph_base_url', 'https://graph.facebook.com');

        Http::fake([
            'https://graph.facebook.com/v20.0/me/permissions*' => Http::response([
                'data' => [
                    ['permission' => 'ads_read', 'status' => 'granted'],
                    ['permission' => 'business_management', 'status' => 'granted'],
                    ['permission' => 'pages_show_list', 'status' => 'declined'],
                ],
            ]),
            'https://graph.facebook.com/v20.0/me*' => Http::response([
                'id' => 'usr_123',
                'name' => 'Castintech Operator',
            ], 200, [
                'x-app-usage' => '{"call_count":1,"total_cputime":2,"total_time":3}',
            ]),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'agency.admin@adscast.test',
            'password' => 'Password123!',
        ]);

        $token = $login->json('token');
        $workspaceId = collect($login->json('workspaces'))
            ->firstWhere('slug', 'operations-main')['id'] ?? null;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/meta/connections', [
                'access_token' => 'long_lived_meta_access_token_value',
                'api_version' => 'v20.0',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.connected_user_name', 'Castintech Operator')
            ->assertJsonPath('data.connection_mode', 'live')
            ->assertJsonPath('data.token_status', 'active');

        /** @var MetaConnection $connection */
        $connection = MetaConnection::query()->where('workspace_id', $workspaceId)->firstOrFail();

        $this->assertSame('usr_123', $connection->external_user_id);
        $this->assertSame(['ads_read', 'business_management'], $connection->scopes);
        $this->assertSame('Castintech Operator', data_get($connection->metadata, 'connected_user_name'));
        $this->assertSame('live', data_get($connection->metadata, 'connection_mode'));
    }
}
