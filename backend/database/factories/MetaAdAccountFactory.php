<?php

namespace Database\Factories;

use App\Models\MetaConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MetaAdAccount>
 */
class MetaAdAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => \App\Models\Workspace::factory(),
            'meta_connection_id' => MetaConnection::factory(),
            'meta_business_id' => null,
            'account_id' => 'act_'.fake()->unique()->numerify('#######'),
            'name' => 'Mock Account '.fake()->word(),
            'currency' => 'USD',
            'timezone_name' => 'Europe/Istanbul',
            'status' => 'active',
            'is_active' => true,
            'metadata' => ['source' => 'factory'],
            'last_synced_at' => now()->subHour(),
        ];
    }
}
