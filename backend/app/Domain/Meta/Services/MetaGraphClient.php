<?php

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Exceptions\MetaApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Throwable;

class MetaGraphClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @return array{body: array<string, mixed>, usage: array<string, mixed>}
     */
    public function getObject(
        string $apiVersion,
        string $path,
        string $accessToken,
        array $query = [],
    ): array {
        $response = $this->sendRequest(
            apiVersion: $apiVersion,
            path: $path,
            accessToken: $accessToken,
            query: $query,
        );

        $body = $response->json();

        if (! is_array($body)) {
            throw new MetaApiException('Meta API gecersiz response dondu.', [
                'path' => $path,
                'api_version' => $apiVersion,
                'response' => $body,
            ]);
        }

        return [
            'body' => $body,
            'usage' => $this->parseUsageHeaders($response),
        ];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, usage: array<string, mixed>}
     */
    public function getPaginatedList(
        string $apiVersion,
        string $path,
        string $accessToken,
        array $query = [],
    ): array {
        $data = [];
        $usage = [];
        $nextUrl = null;
        $page = 0;

        do {
            $response = $nextUrl
                ? $this->sendAbsoluteRequest($nextUrl)
                : $this->sendRequest(
                    apiVersion: $apiVersion,
                    path: $path,
                    accessToken: $accessToken,
                    query: $query,
                );

            $body = $response->json();

            if (! is_array($body)) {
                throw new MetaApiException('Meta API gecersiz liste response dondu.', [
                    'path' => $path,
                    'api_version' => $apiVersion,
                    'response' => $body,
                ]);
            }

            $pageData = Arr::get($body, 'data', []);

            if (! is_array($pageData)) {
                throw new MetaApiException('Meta API data alani beklenen formatta degil.', [
                    'path' => $path,
                    'api_version' => $apiVersion,
                    'response' => $body,
                ]);
            }

            $data = [...$data, ...array_values($pageData)];
            $usage = $this->mergeUsage($usage, $this->parseUsageHeaders($response));
            $nextUrl = Arr::get($body, 'paging.next');
            $page++;
        } while (is_string($nextUrl) && $nextUrl !== '' && $page < 20);

        return [
            'data' => $data,
            'usage' => $usage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectAccessToken(string $apiVersion, string $accessToken): array
    {
        $profile = $this->getObject(
            apiVersion: $apiVersion,
            path: 'me',
            accessToken: $accessToken,
            query: [
                'fields' => 'id,name',
            ],
        );

        $permissions = $this->getPaginatedList(
            apiVersion: $apiVersion,
            path: 'me/permissions',
            accessToken: $accessToken,
            query: [
                'fields' => 'permission,status',
                'limit' => 200,
            ],
        );

        $grantedScopes = collect($permissions['data'])
            ->filter(fn (array $item): bool => ($item['status'] ?? null) === 'granted')
            ->pluck('permission')
            ->filter(fn ($scope): bool => is_string($scope) && $scope !== '')
            ->values()
            ->all();

        return [
            'external_user_id' => Arr::get($profile['body'], 'id'),
            'connected_user_name' => Arr::get($profile['body'], 'name'),
            'scopes' => $grantedScopes,
            'usage' => $this->mergeUsage($profile['usage'], $permissions['usage']),
        ];
    }

    private function sendRequest(
        string $apiVersion,
        string $path,
        string $accessToken,
        array $query = [],
    ): Response {
        $baseUrl = rtrim((string) config('services.meta.graph_base_url', 'https://graph.facebook.com'), '/');
        $normalizedPath = ltrim($path, '/');

        try {
            $response = $this->http
                ->acceptJson()
                ->retry(3, 250)
                ->timeout(20)
                ->get("{$baseUrl}/{$apiVersion}/{$normalizedPath}", array_merge($query, [
                    'access_token' => $accessToken,
                ]));
        } catch (ConnectionException $exception) {
            throw new MetaApiException('Meta API baglanti hatasi.', [
                'path' => $normalizedPath,
                'api_version' => $apiVersion,
            ], previous: $exception);
        }

        $this->ensureSuccessfulResponse($response, $normalizedPath, $apiVersion);

        return $response;
    }

    private function sendAbsoluteRequest(string $url): Response
    {
        try {
            $response = $this->http
                ->acceptJson()
                ->retry(3, 250)
                ->timeout(20)
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new MetaApiException('Meta API paginated request baglanti hatasi.', [
                'url' => $url,
            ], previous: $exception);
        }

        $this->ensureSuccessfulResponse($response, $url, null);

        return $response;
    }

    private function ensureSuccessfulResponse(Response $response, string $path, ?string $apiVersion): void
    {
        if ($response->successful()) {
            return;
        }

        $body = $response->json();
        $error = is_array($body) ? Arr::get($body, 'error', []) : [];
        $message = is_array($error) ? (string) Arr::get($error, 'message', 'Meta API istegi basarisiz.') : 'Meta API istegi basarisiz.';

        throw new MetaApiException($message, [
            'path' => $path,
            'api_version' => $apiVersion,
            'status' => $response->status(),
            'error' => $error,
        ], $response->status());
    }

    /**
     * @return array<string, mixed>
     */
    private function parseUsageHeaders(Response $response): array
    {
        $headers = [
            'app' => $response->header('x-app-usage'),
            'business_use_case' => $response->header('x-business-use-case-usage'),
            'ad_account' => $response->header('x-ad-account-usage'),
        ];

        $usage = [];

        foreach ($headers as $key => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $decoded = ['raw' => $value];
            }

            if (is_array($decoded)) {
                $usage[$key] = $decoded;
            }
        }

        return $usage;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>
     */
    private function mergeUsage(array $left, array $right): array
    {
        if ($left === []) {
            return $right;
        }

        if ($right === []) {
            return $left;
        }

        return array_replace_recursive($left, $right);
    }
}
