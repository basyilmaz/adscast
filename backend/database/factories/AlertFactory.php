<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Alert>
 */
class AlertFactory extends Factory
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
            'entity_type' => 'campaign',
            'entity_id' => null,
            'code' => fake()->randomElement(['spend_no_result', 'rising_cpa', 'falling_ctr']),
            'severity' => fake()->randomElement(['low', 'medium', 'high']),
            'summary' => fake()->sentence(6),
            'explanation' => fake()->paragraph(),
            'recommended_action' => fake()->sentence(10),
            'confidence' => fake()->randomFloat(2, 0.5, 0.95),
            'status' => fake()->randomElement(['open', 'resolved']),
            'date_detected' => fake()->dateTimeBetween('-7 days', 'now')->format('Y-m-d'),
            'source_rule_version' => 'v1',
            'metadata' => ['source' => 'factory'],
            'resolved_at' => null,
        ];
    }
}
