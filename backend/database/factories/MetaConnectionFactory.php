<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MetaConnection>
 */
class MetaConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'provider' => 'meta',
            'api_version' => 'v20.0',
            'status' => 'active',
            'external_user_id' => (string) fake()->randomNumber(7, true),
            'access_token_encrypted' => 'encrypted_access_token',
            'refresh_token_encrypted' => 'encrypted_refresh_token',
            'token_expires_at' => now()->addDays(45),
            'scopes' => ['ads_read', 'business_management'],
            'connected_at' => now()->subDays(15),
            'last_synced_at' => now()->subHour(),
            'metadata' => ['source' => 'factory'],
        ];
    }
}
