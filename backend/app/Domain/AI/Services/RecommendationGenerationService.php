<?php

namespace App\Domain\AI\Services;

use App\Models\AIGeneration;
use App\Models\Alert;
use App\Models\Recommendation;
use App\Models\Workspace;
use Illuminate\Support\Str;

class RecommendationGenerationService
{
    public function __construct(
        private readonly AIProviderFactory $providerFactory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generateForWorkspace(Workspace $workspace, ?string $generatedBy = null): array
    {
        $openAlerts = Alert::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'open')
            ->orderByDesc('date_detected')
            ->limit(10)
            ->get([
                'id',
                'code',
                'severity',
                'summary',
                'recommended_action',
                'date_detected',
            ])
            ->toArray();

        $context = [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'alerts' => $openAlerts,
            'generated_at' => now()->toIso8601String(),
        ];

        $template = 'workspace_weekly_summary_v1';
        $provider = $this->providerFactory->resolve();
        $output = $provider->generate($template, $context);

        $generation = AIGeneration::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'entity_type' => 'workspace',
            'entity_id' => $workspace->id,
            'provider' => (string) ($output['provider'] ?? config('services.ai.provider', 'mock')),
            'model' => (string) ($output['model'] ?? config('services.ai.model', 'unknown')),
            'prompt_template' => $template,
            'prompt_context' => $context,
            'prompt_text' => 'Generated via template: '.$template,
            'output' => $output,
            'status' => 'succeeded',
            'token_usage' => null,
            'generated_by' => $generatedBy,
            'generated_at' => now(),
        ]);

        $recommendation = Recommendation::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'alert_id' => null,
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
            'summary' => (string) ($output['biggest_opportunity'] ?? 'AI onerisi uretildi.'),
            'details' => (string) ($output['operator_notes'] ?? ''),
            'action_type' => 'ai_guidance',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'ai',
            'generated_at' => now(),
            'metadata' => [
                'ai_generation_id' => $generation->id,
            ],
        ]);

        return [
            'generation' => $generation,
            'recommendation' => $recommendation,
            'output' => $output,
        ];
    }
}
