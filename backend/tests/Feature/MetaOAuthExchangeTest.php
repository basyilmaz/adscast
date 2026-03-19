<?php

namespace Tests\Feature;

use App\Models\MetaConnection;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaOAuthExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_oauth_exchange_creates_live_connection(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        config()->set('services.meta.mode', 'live');
        config()->set('services.meta.app_id', '949256190921445');
        config()->set('services.meta.app_secret', 'secret');
        config()->set('services.meta.redirect_uri', 'https://adscast.castintech.com/settings/meta/callback');
        config()->set('services.meta.graph_base_url', 'https://graph.facebook.com');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'agency.admin@adscast.test',
            'password' => 'Password123!',
        ]);

        $token = $login->json('token');
        $workspaceId = collect($login->json('workspaces'))
            ->firstWhere('slug', 'operations-main')['id'] ?? null;

        $start = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/meta/oauth/start');

        $state = $start->json('data.state');

        Http::fake([
            'https://graph.facebook.com/v20.0/oauth/access_token*' => Http::sequence()
                ->push([
                    'access_token' => 'short_lived_token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ])
                ->push([
                    'access_token' => 'long_lived_token',
                    'token_type' => 'bearer',
                    'expires_in' => 5183944,
                ]),
            'https://graph.facebook.com/v20.0/me/permissions*' => Http::response([
                'data' => [
                    ['permission' => 'ads_read', 'status' => 'granted'],
                    ['permission' => 'business_management', 'status' => 'granted'],
                ],
            ]),
            'https://graph.facebook.com/v20.0/me*' => Http::response([
                'id' => 'usr_789',
                'name' => 'OAuth Operator',
            ]),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->postJson('/api/v1/meta/oauth/exchange', [
                'code' => 'meta_auth_code_12345',
                'state' => $state,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.connection_mode', 'live')
            ->assertJsonPath('data.connected_user_name', 'OAuth Operator')
            ->assertJsonPath('data.token_status', 'active');

        /** @var MetaConnection $connection */
        $connection = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        $this->assertSame('usr_789', $connection->external_user_id);
        $this->assertSame('oauth', data_get($connection->metadata, 'connection_source'));
        $this->assertSame('OAuth Operator', data_get($connection->metadata, 'connected_user_name'));
        $this->assertSame(['ads_read', 'business_management'], $connection->scopes);
    }
}
