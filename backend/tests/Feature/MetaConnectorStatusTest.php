<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaConnectorStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_admin_can_fetch_meta_connector_status(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        config()->set('services.meta.mode', 'live');
        config()->set('services.meta.app_id', '123456');
        config()->set('services.meta.app_secret', 'secret');
        config()->set('services.meta.redirect_uri', 'https://adscast.castintech.com/api/v1/meta/callback');
        config()->set('services.meta.raw_payload_retention_days', 45);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'agency.admin@adscast.test',
            'password' => 'Password123!',
        ]);

        $token = $login->json('token');
        $workspaceId = collect($login->json('workspaces'))
            ->firstWhere('slug', 'operations-main')['id'] ?? null;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspaceId)
            ->getJson('/api/v1/meta/connector-status');

        $response->assertOk()
            ->assertJsonPath('data.mode', 'live')
            ->assertJsonPath('data.oauth_ready', true)
            ->assertJsonPath('data.raw_payload_retention_days', 45);
    }
}
