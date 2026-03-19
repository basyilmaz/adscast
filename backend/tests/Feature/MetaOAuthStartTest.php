<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaOAuthStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_oauth_start_returns_authorization_url_and_state(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        config()->set('services.meta.app_id', '949256190921445');
        config()->set('services.meta.redirect_uri', 'https://adscast.castintech.com/settings/meta/callback');
        config()->set('services.meta.default_api_version', 'v20.0');
        config()->set('services.meta.scopes', ['ads_read', 'business_management', 'pages_show_list']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'agency.admin@adscast.test',
            'password' => 'Password123!',
        ]);

        $token = $login->json('token');
        $workspaceId = collect($login->json('workspaces'))
            ->firstWhere('slug', 'operations-main')['id'] ?? null;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/meta/oauth/start');

        $response->assertOk()
            ->assertJsonPath('data.redirect_uri', 'https://adscast.castintech.com/settings/meta/callback')
            ->assertJsonPath('data.api_version', 'v20.0');

        $authUrl = $response->json('data.auth_url');
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('client_id=949256190921445', $authUrl);
        $this->assertStringContainsString('response_type=code', $authUrl);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fadscast.castintech.com%2Fsettings%2Fmeta%2Fcallback', $authUrl);
        $this->assertSame(64, strlen((string) $response->json('data.state')));
    }
}
