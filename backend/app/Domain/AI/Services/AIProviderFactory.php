<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Contracts\AIProvider;
use App\Domain\AI\Providers\MockAIProvider;
use App\Domain\AI\Providers\OpenAIProvider;

class AIProviderFactory
{
    public function resolve(): AIProvider
    {
        return match (config('services.ai.provider', 'mock')) {
            'openai' => app(OpenAIProvider::class),
            default => app(MockAIProvider::class),
        };
    }
}
