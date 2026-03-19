<?php

namespace App\Domain\Meta\Services;

use App\Models\MetaConnection;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

class MetaOAuthService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly MetaOAuthStateStore $stateStore,
        private readonly MetaConnectionService $connectionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function start(User $user, Workspace $workspace): array
    {
        $this->ensureOAuthConfigured(requireSecret: false);

        $state = $this->stateStore->issue($user->id, $workspace->id);
        $apiVersion = (string) config('services.meta.default_api_version', 'v20.0');
        $scopes = config('services.meta.scopes', []);
        $dialogBaseUrl = rtrim((string) config('services.meta.dialog_base_url', 'https://www.facebook.com'), '/');
        $redirectUri = (string) config('services.meta.redirect_uri');

        $query = http_build_query([
            'client_id' => (string) config('services.meta.app_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state['state'],
            'scope' => implode(',', is_array($scopes) ? $scopes : []),
            'response_type' => 'code',
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'auth_url' => "{$dialogBaseUrl}/{$apiVersion}/dialog/oauth?{$query}",
            'state' => $state['state'],
            'redirect_uri' => $redirectUri,
            'scopes' => is_array($scopes) ? $scopes : [],
            'api_version' => $apiVersion,
        ];
    }

    public function exchangeAuthorizationCode(
        User $user,
        Workspace $workspace,
        string $code,
        string $state,
    ): MetaConnection {
        $this->ensureOAuthConfigured(requireSecret: true);
        $this->stateStore->consume($state, $user->id, $workspace->id);

        $apiVersion = (string) config('services.meta.default_api_version', 'v20.0');
        $shortLived = $this->exchangeCodeForAccessToken($apiVersion, $code);
        $longLived = $this->exchangeForLongLivedToken($apiVersion, (string) $shortLived['access_token']);

        $payload = [
            'access_token' => (string) $longLived['access_token'],
            'token_expires_at' => isset($longLived['expires_in'])
                ? now()->addSeconds((int) $longLived['expires_in'])->toISOString()
                : null,
            'api_version' => $apiVersion,
            'metadata' => [
                'connection_source' => 'oauth',
                'oauth_completed_at' => now()->toISOString(),
            ],
        ];

        return $this->connectionService->upsertConnection($workspace, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCodeForAccessToken(string $apiVersion, string $code): array
    {
        return $this->requestToken("{$apiVersion}/oauth/access_token", [
            'client_id' => (string) config('services.meta.app_id'),
            'redirect_uri' => (string) config('services.meta.redirect_uri'),
            'client_secret' => (string) config('services.meta.app_secret'),
            'code' => $code,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeForLongLivedToken(string $apiVersion, string $shortLivedToken): array
    {
        return $this->requestToken("{$apiVersion}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => (string) config('services.meta.app_id'),
            'client_secret' => (string) config('services.meta.app_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestToken(string $path, array $query): array
    {
        $baseUrl = rtrim((string) config('services.meta.graph_base_url', 'https://graph.facebook.com'), '/');

        try {
            $response = $this->http
                ->acceptJson()
                ->retry(2, 250)
                ->timeout(20)
                ->get("{$baseUrl}/{$path}", $query);
        } catch (Throwable $throwable) {
            throw ValidationException::withMessages([
                'code' => 'Meta token exchange baglanti hatasi: '.$throwable->getMessage(),
            ]);
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ! filled(Arr::get($body, 'access_token'))) {
            $message = is_array($body)
                ? (string) Arr::get($body, 'error.message', 'Meta token exchange basarisiz.')
                : 'Meta token exchange basarisiz.';

            throw ValidationException::withMessages([
                'code' => $message,
            ]);
        }

        return $body;
    }

    private function ensureOAuthConfigured(bool $requireSecret): void
    {
        $appIdConfigured = filled(config('services.meta.app_id'));
        $redirectUriConfigured = filled(config('services.meta.redirect_uri'));
        $appSecretConfigured = filled(config('services.meta.app_secret'));

        if (! $appIdConfigured || ! $redirectUriConfigured || ($requireSecret && ! $appSecretConfigured)) {
            throw ValidationException::withMessages([
                'meta_oauth' => 'Meta OAuth konfigurasyonu eksik.',
            ]);
        }
    }
}
