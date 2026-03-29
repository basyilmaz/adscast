<?php

namespace App\Domain\AI\Services;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\AIGeneration;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\InsightDaily;
use App\Models\Recommendation;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecommendationGenerationService
{
    public function __construct(
        private readonly AIProviderFactory $providerFactory,
        private readonly PromptTemplateRegistry $promptTemplateRegistry,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generateForWorkspace(Workspace $workspace, ?string $generatedBy = null): array
    {
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays(6);

        $openAlerts = Alert::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'open')
            ->orderByDesc('date_detected')
            ->limit(15)
            ->get([
                'id', 'entity_type', 'entity_id', 'code', 'severity',
                'summary', 'recommended_action', 'date_detected',
            ])
            ->toArray();

        $campaignMetrics = $this->topMetrics($workspace->id, 'campaign', $startDate, $endDate, 10);
        $adSetMetrics = $this->topMetrics($workspace->id, 'adset', $startDate, $endDate, 10);
        $adMetrics = $this->topMetrics($workspace->id, 'ad', $startDate, $endDate, 10);

        $campaignNames = Campaign::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('meta_campaign_id', $campaignMetrics->pluck('entity_external_id'))
            ->pluck('name', 'meta_campaign_id')
            ->all();

        $adSetInfo = AdSet::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('meta_ad_set_id', $adSetMetrics->pluck('entity_external_id'))
            ->get(['meta_ad_set_id', 'name', 'optimization_goal', 'targeting'])
            ->keyBy('meta_ad_set_id');

        $adInfo = Ad::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('meta_ad_id', $adMetrics->pluck('entity_external_id'))
            ->with('creative:id,headline,body,call_to_action,asset_type')
            ->get(['id', 'meta_ad_id', 'name', 'creative_id'])
            ->keyBy('meta_ad_id');

        $context = [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'alerts' => $openAlerts,
            'campaigns' => $campaignMetrics->map(function (array $row) use ($campaignNames): array {
                return [
                    'name' => $campaignNames[$row['entity_external_id']] ?? $row['entity_external_id'],
                    'spend' => $row['spend'],
                    'results' => $row['results'],
                    'cpa' => $row['cpa_cpl'],
                    'ctr' => $row['ctr'],
                    'cpm' => $row['cpm'],
                    'frequency' => $row['frequency'],
                ];
            })->values()->all(),
            'ad_sets' => $adSetMetrics->map(function (array $row) use ($adSetInfo): array {
                $info = $adSetInfo[$row['entity_external_id']] ?? null;
                return [
                    'name' => $info?->name ?? $row['entity_external_id'],
                    'optimization_goal' => $info?->optimization_goal,
                    'spend' => $row['spend'],
                    'results' => $row['results'],
                    'cpa' => $row['cpa_cpl'],
                    'ctr' => $row['ctr'],
                    'cpm' => $row['cpm'],
                    'frequency' => $row['frequency'],
                ];
            })->values()->all(),
            'ads' => $adMetrics->map(function (array $row) use ($adInfo): array {
                $info = $adInfo[$row['entity_external_id']] ?? null;
                $creative = $info?->creative;
                return [
                    'name' => $info?->name ?? $row['entity_external_id'],
                    'headline' => $creative?->headline,
                    'body' => $creative?->body ? Str::limit($creative->body, 120) : null,
                    'cta' => $creative?->call_to_action,
                    'asset_type' => $creative?->asset_type,
                    'spend' => $row['spend'],
                    'results' => $row['results'],
                    'cpa' => $row['cpa_cpl'],
                    'ctr' => $row['ctr'],
                    'cpm' => $row['cpm'],
                ];
            })->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];

        $template = 'workspace_weekly_summary_v1';
        $prompt = $this->promptTemplateRegistry->build($template, $context);
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
            'prompt_text' => (string) $prompt['prompt_text'],
            'output' => $output,
            'status' => 'succeeded',
            'token_usage' => $output['token_usage'] ?? null,
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
                'performance_summary' => $output['performance_summary'] ?? null,
                'biggest_risk' => $output['biggest_risk'] ?? null,
                'biggest_opportunity' => $output['biggest_opportunity'] ?? null,
                'what_to_test_next' => $output['what_to_test_next'] ?? null,
                'budget_note' => $output['budget_note'] ?? null,
                'creative_note' => $output['creative_note'] ?? null,
                'targeting_note' => $output['targeting_note'] ?? null,
                'landing_page_note' => $output['landing_page_note'] ?? null,
                'client_friendly_summary' => $output['client_friendly_summary'] ?? null,
                'operator_notes' => $output['operator_notes'] ?? null,
            ],
        ]);

        return [
            'generation' => $generation,
            'recommendation' => $recommendation,
            'output' => $output,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function topMetrics(string $workspaceId, string $level, Carbon $startDate, Carbon $endDate, int $limit): Collection
    {
        return InsightDaily::query()
            ->where('workspace_id', $workspaceId)
            ->where('level', $level)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('entity_external_id')
            ->select([
                'entity_external_id',
                DB::raw('SUM(spend) as spend'),
                DB::raw('SUM(results) as results'),
                DB::raw('AVG(ctr) as ctr'),
                DB::raw('AVG(cpm) as cpm'),
                DB::raw('AVG(frequency) as frequency'),
            ])
            ->orderByDesc(DB::raw('SUM(spend)'))
            ->limit($limit)
            ->get()
            ->map(function ($row): array {
                $spend = round((float) $row->spend, 2);
                $results = round((float) $row->results, 2);

                return [
                    'entity_external_id' => $row->entity_external_id,
                    'spend' => $spend,
                    'results' => $results,
                    'cpa_cpl' => $results > 0 ? round($spend / $results, 2) : null,
                    'ctr' => round((float) ($row->ctr ?? 0), 2),
                    'cpm' => round((float) ($row->cpm ?? 0), 2),
                    'frequency' => round((float) ($row->frequency ?? 0), 2),
                ];
            });
    }
}
