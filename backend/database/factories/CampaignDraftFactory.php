<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CampaignDraft>
 */
class CampaignDraftFactory extends Factory
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
            'meta_ad_account_id' => \App\Models\MetaAdAccount::factory(),
            'objective' => fake()->randomElement(['LEADS', 'CONVERSIONS']),
            'product_service' => fake()->sentence(8),
            'target_audience' => fake()->sentence(8),
            'location' => fake()->randomElement(['TR', 'US', 'DE']),
            'budget_min' => fake()->randomFloat(2, 20, 100),
            'budget_max' => fake()->randomFloat(2, 101, 300),
            'offer' => fake()->sentence(5),
            'landing_page_url' => fake()->url(),
            'tone_style' => fake()->randomElement(['resmi', 'samimi', 'performans']),
            'existing_creative_availability' => fake()->randomElement(['var', 'yok']),
            'notes' => fake()->sentence(10),
            'status' => 'draft',
            'created_by' => \App\Models\User::factory(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejected_reason' => null,
            'publish_response_metadata' => null,
            'published_at' => null,
        ];
    }
}
