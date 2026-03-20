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
            'Base your commentary on the supplied normalized context and rule outputs only.',
            'Do not invent metrics, campaign names, or results that are not present in the context.',
            'Keep recommendations operational, approval-safe, and specific.',
            'Return only data that fits the provided JSON schema.',
        ]);

        $input = implode("\n\n", [
            'AdsCast workspace context JSON:',
            json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'Generate an operator-grade weekly summary and recommendation pack for this workspace.',
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
