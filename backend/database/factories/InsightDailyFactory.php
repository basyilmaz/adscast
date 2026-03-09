<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsightDaily>
 */
class InsightDailyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $results = fake()->numberBetween(0, 30);
        $spend = fake()->randomFloat(2, 10, 500);

        return [
            'workspace_id' => \App\Models\Workspace::factory(),
            'level' => 'campaign',
            'entity_id' => null,
            'entity_external_id' => 'cmp_'.fake()->numerify('#######'),
            'date' => fake()->dateTimeBetween('-14 days', 'now')->format('Y-m-d'),
            'spend' => $spend,
            'impressions' => fake()->numberBetween(500, 50000),
            'reach' => fake()->numberBetween(500, 30000),
            'frequency' => fake()->randomFloat(3, 1, 5),
            'clicks' => fake()->numberBetween(20, 1000),
            'link_clicks' => fake()->numberBetween(20, 700),
            'ctr' => fake()->randomFloat(4, 0.5, 6),
            'cpc' => fake()->randomFloat(4, 0.1, 3),
            'cpm' => fake()->randomFloat(4, 5, 80),
            'results' => $results,
            'cost_per_result' => $results > 0 ? round($spend / $results, 4) : null,
            'leads' => $results,
            'purchases' => fake()->numberBetween(0, 10),
            'roas' => fake()->randomFloat(4, 0.4, 5),
            'conversions' => $results,
            'actions' => [
                ['type' => 'lead', 'value' => $results],
            ],
            'attribution_setting' => '7d_click',
            'source' => 'meta',
            'synced_at' => now(),
        ];
    }
}
