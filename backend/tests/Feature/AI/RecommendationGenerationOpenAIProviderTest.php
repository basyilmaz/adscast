<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Services\RecommendationGenerationService;
use App\Models\Alert;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationGenerationOpenAIProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_provider_generates_structured_output_and_persists_usage(): void
    {
        config()->set('services.ai.provider', 'openai');
        config()->set('services.ai.api_key', 'test-openai-key');
        config()->set('services.ai.base_url', 'https://api.openai.com/v1');
        config()->set('services.ai.model', 'gpt-4.1-mini');
        config()->set('services.ai.temperature', 0.2);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_123',
                'model' => 'gpt-4.1-mini',
                'output_text' => json_encode([
                    'performance_summary' => 'Performans stabil, ama sonuc veren kampanya sayisi dusuk.',
                    'biggest_risk' => 'Sonuc alamayan kampanyalarda harcama devam ediyor.',
                    'biggest_opportunity' => 'Sonuc veren kampanyalarda kontrollu butce artisi denenebilir.',
                    'what_to_test_next' => 'Yeni aci ve teklif varyasyonlari',
                    'budget_note' => 'Butceyi kademeli artirin.',
                    'creative_note' => 'Creative rotation uygulayin.',
                    'targeting_note' => 'Dar niyet segmentlerini test edin.',
                    'landing_page_note' => 'Landing teklif netligini guclendirin.',
                    'client_friendly_summary' => 'Genel gorunum stabil, ama daha fazla sonuc icin test gerekiyor.',
                    'operator_notes' => 'Rules engine ile uyumlu sekilde sonuc vermeyen kampanyalari kisitlayin.',
                ], JSON_THROW_ON_ERROR),
                'usage' => [
                    'input_tokens' => 111,
                    'output_tokens' => 222,
                    'total_tokens' => 333,
                ],
            ], 200),
        ]);

        $workspace = Workspace::factory()->create();
        Alert::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => 'open',
            'code' => 'spend_no_result',
            'summary' => 'Harcama var ancak sonuc yok.',
            'recommended_action' => 'Butceyi kisitlayin.',
        ]);

        $result = app(RecommendationGenerationService::class)->generateForWorkspace($workspace);

        $this->assertSame('openai', $result['generation']->provider);
        $this->assertSame('gpt-4.1-mini', $result['generation']->model);
        $this->assertSame(333, $result['generation']->token_usage['total_tokens']);
        $this->assertSame('openai', $result['output']['provider']);
        $this->assertSame('resp_123', $result['output']['response_id']);

        Http::assertSent(function ($request): bool {
            $payload = json_decode($request->body(), true);

            return $request->url() === 'https://api.openai.com/v1/responses'
                && ($payload['store'] ?? null) === false
                && ($payload['text']['format']['type'] ?? null) === 'json_schema'
                && ($payload['model'] ?? null) === 'gpt-4.1-mini'
                && is_string($payload['instructions'] ?? null)
                && is_string($payload['input'] ?? null);
        });
    }

    public function test_openai_provider_falls_back_to_mock_when_api_key_missing(): void
    {
        config()->set('services.ai.provider', 'openai');
        config()->set('services.ai.api_key', null);

        Http::fake();

        $workspace = Workspace::factory()->create();

        $result = app(RecommendationGenerationService::class)->generateForWorkspace($workspace);

        $this->assertSame('mock', $result['generation']->provider);
        $this->assertSame('openai', $result['output']['requested_provider']);
        $this->assertSame('missing_api_key', $result['output']['fallback_reason']);

        Http::assertNothingSent();
    }
}
