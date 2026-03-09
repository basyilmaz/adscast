<?php

namespace Database\Factories;

use App\Models\MetaAdAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
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
            'meta_ad_account_id' => MetaAdAccount::factory(),
            'meta_campaign_id' => 'cmp_'.fake()->unique()->numerify('#######'),
            'name' => fake()->sentence(3),
            'objective' => fake()->randomElement(['LEADS', 'CONVERSIONS', 'TRAFFIC']),
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'buying_type' => 'AUCTION',
            'daily_budget' => fake()->randomFloat(2, 20, 300),
            'lifetime_budget' => null,
            'start_time' => now()->subDays(14),
            'stop_time' => null,
            'is_active' => true,
            'metadata' => ['source' => 'factory'],
            'last_synced_at' => now()->subHour(),
        ];
    }
}
