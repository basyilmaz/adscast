<?php

namespace App\Domain\Meta\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetaOAuthStateStore
{
    private const CACHE_PREFIX = 'meta_oauth_state:';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function issue(string $userId, string $workspaceId): array
    {
        $state = Str::random(64);
        $payload = [
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'issued_at' => now()->toISOString(),
        ];

        $this->cache->put(
            $this->cacheKey($state),
            $payload,
            now()->addMinutes((int) config('services.meta.oauth_state_ttl_minutes', 10)),
        );

        return [
            'state' => $state,
            'payload' => $payload,
        ];
    }

    public function consume(string $state, string $userId, string $workspaceId): void
    {
        $payload = $this->cache->pull($this->cacheKey($state));

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'state' => 'Meta OAuth state gecersiz veya suresi dolmus.',
            ]);
        }

        if (($payload['user_id'] ?? null) !== $userId || ($payload['workspace_id'] ?? null) !== $workspaceId) {
            throw ValidationException::withMessages([
                'state' => 'Meta OAuth state workspace veya kullanici ile eslesmiyor.',
            ]);
        }
    }

    private function cacheKey(string $state): string
    {
        return self::CACHE_PREFIX.$state;
    }
}
