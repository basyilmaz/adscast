<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIProvider;
use App\Domain\AI\Services\PromptTemplateRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements AIProvider
{
    public function __construct(
        private readonly PromptTemplateRegistry $promptTemplateRegistry,
        private readonly MockAIProvider $mockAIProvider,
    ) {
    }

    public function generate(string $template, array $context): array
    {
        $apiKey = (string) config('services.ai.api_key', '');

        if ($apiKey === '') {
            $fallback = $this->mockAIProvider->generate($template, $context);
            $fallback['provider'] = 'mock';
            $fallback['requested_provider'] = 'openai';
            $fallback['fallback_reason'] = 'missing_api_key';

            return $fallback;
        }

        $prompt = $this->promptTemplateRegistry->build($template, $context);
        $response = Http::baseUrl(rtrim((string) config('services.ai.base_url', 'https://api.openai.com/v1'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.ai.timeout_seconds', 45))
            ->withToken($apiKey)
            ->withHeaders([
                'User-Agent' => (string) config('services.ai.user_agent', 'AdsCast/0.2.0'),
            ])
            ->post('/responses', [
                'model' => config('services.ai.model', 'gpt-4.1-mini'),
                'instructions' => $prompt['instructions'],
                'input' => $prompt['input'],
                'store' => false,
                'temperature' => (float) config('services.ai.temperature', 0.2),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'adscast_workspace_summary',
                        'strict' => true,
                        'schema' => $prompt['schema'],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'OpenAI API istegi basarisiz oldu (%d): %s',
                $response->status(),
                (string) ($response->json('error.message') ?? $response->body())
            ));
        }

        $payload = $response->json();
        $content = $this->extractStructuredOutput($payload);
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI structured output JSON olarak parse edilemedi.');
        }

        return array_merge($decoded, [
            'provider' => 'openai',
            'requested_provider' => 'openai',
            'model' => (string) ($payload['model'] ?? config('services.ai.model', 'gpt-4.1-mini')),
            'template' => $template,
            'context_snapshot' => $context,
            'response_id' => $payload['id'] ?? null,
            'token_usage' => $this->mapUsage($payload['usage'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractStructuredOutput(array $payload): string
    {
        $outputText = Arr::get($payload, 'output_text');

        if (is_string($outputText) && $outputText !== '') {
            return $this->normalizeJsonText($outputText);
        }

        $output = Arr::get($payload, 'output', []);

        if (! is_array($output)) {
            throw new RuntimeException('OpenAI response output alani beklenen formatta degil.');
        }

        foreach ($output as $item) {
            $contents = Arr::get($item, 'content', []);

            if (! is_array($contents)) {
                continue;
            }

            foreach ($contents as $content) {
                $text = Arr::get($content, 'text');

                if (is_string($text) && $text !== '') {
                    return $this->normalizeJsonText($text);
                }
            }
        }

        throw new RuntimeException('OpenAI response icinde parse edilebilir structured output bulunamadi.');
    }

    private function normalizeJsonText(string $text): string
    {
        return trim(preg_replace('/^```json|```$/m', '', $text) ?? $text);
    }

    /**
     * @param mixed $usage
     * @return array<string, int>|null
     */
    private function mapUsage(mixed $usage): ?array
    {
        if (! is_array($usage)) {
            return null;
        }

        return [
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        ];
    }
}
