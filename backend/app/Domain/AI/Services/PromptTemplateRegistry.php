<?php

namespace App\Domain\AI\Services;

use InvalidArgumentException;

class PromptTemplateRegistry
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function build(string $template, array $context): array
    {
        return match ($template) {
            'workspace_weekly_summary_v1' => $this->buildWorkspaceWeeklySummary($context),
            default => throw new InvalidArgumentException("Bilinmeyen AI prompt template: {$template}"),
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildWorkspaceWeeklySummary(array $context): array
    {
        $instructions = implode("\n", [
            'You are AdsCast, a senior performance marketing operator for Meta ads.',
            'Always answer in Turkish.',
            'You will receive a JSON context containing:',
            '- alerts: Active performance alerts from the rules engine',
            '- campaigns: Top campaigns with spend, results, CPA, CTR, CPM, frequency',
            '- ad_sets: Top ad sets with the same metrics plus optimization_goal',
            '- ads: Top ads with metrics plus creative details (headline, body, CTA, asset_type)',
            '',
            'Analyze the data and provide:',
            '1. Which campaigns/ad sets/ads are performing well and which are underperforming',
            '2. Which creatives (headlines, CTAs) are working best based on CPA and CTR',
            '3. Concrete budget reallocation suggestions (move budget from X to Y)',
            '4. Specific creative test ideas based on what is currently working',
            '5. Targeting improvements based on ad set performance differences',
            '',
            'Base your commentary on the supplied data only.',
            'Do not invent metrics, campaign names, or results that are not present in the context.',
            'Keep recommendations operational, approval-safe, and specific.',
            'When suggesting actions, reference actual campaign/ad set/ad names from the data.',
            'Return only data that fits the provided JSON schema.',
        ]);

        $input = implode("\n\n", [
            'AdsCast workspace context JSON:',
            json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'Generate an operator-grade weekly summary and recommendation pack for this workspace. Reference specific campaigns, ad sets, and ads by name.',
        ]);

        return [
            'instructions' => $instructions,
            'input' => $input,
            'prompt_text' => $instructions."\n\n".$input,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'performance_summary' => ['type' => 'string'],
                    'biggest_risk' => ['type' => 'string'],
                    'biggest_opportunity' => ['type' => 'string'],
                    'what_to_test_next' => ['type' => 'string'],
                    'budget_note' => ['type' => 'string'],
                    'creative_note' => ['type' => 'string'],
                    'targeting_note' => ['type' => 'string'],
                    'landing_page_note' => ['type' => 'string'],
                    'client_friendly_summary' => ['type' => 'string'],
                    'operator_notes' => ['type' => 'string'],
                ],
                'required' => [
                    'performance_summary',
                    'biggest_risk',
                    'biggest_opportunity',
                    'what_to_test_next',
                    'budget_note',
                    'creative_note',
                    'targeting_note',
                    'landing_page_note',
                    'client_friendly_summary',
                    'operator_notes',
                ],
                'additionalProperties' => false,
            ],
        ];
    }
}
