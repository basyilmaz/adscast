<?php

namespace App\Domain\Meta\Services;

use App\Models\MetaConnection;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;

class MetaConnectionService
{
    public function __construct(
        private readonly MetaGraphClient $graphClient,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsertConnection(Workspace $workspace, array $payload): MetaConnection
    {
        /** @var MetaConnection $connection */
        $connection = MetaConnection::query()
            ->firstOrNew([
                'workspace_id' => $workspace->id,
                'provider' => 'meta',
            ]);

        $apiVersion = (string) ($payload['api_version'] ?? 'v20.0');
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        if (
            config('services.meta.mode', 'stub') === 'live'
            && ! empty($payload['access_token'])
        ) {
            try {
                $inspection = $this->graphClient->inspectAccessToken($apiVersion, (string) $payload['access_token']);
            } catch (Throwable $throwable) {
                throw ValidationException::withMessages([
                    'access_token' => 'Meta access token dogrulanamadi: '.$throwable->getMessage(),
                ]);
            }

            $payload['external_user_id'] ??= $inspection['external_user_id'] ?? null;
            $payload['scopes'] ??= $inspection['scopes'] ?? [];

            $metadata = array_merge($metadata, [
                'connection_mode' => 'live',
                'connected_user_name' => $inspection['connected_user_name'] ?? null,
                'token_validated_at' => now()->toISOString(),
                'api_usage' => $inspection['usage'] ?? [],
            ]);
        } else {
            $metadata = array_merge($metadata, [
                'connection_mode' => config('services.meta.mode', 'stub'),
            ]);
        }

        $connection->fill([
            'api_version' => $apiVersion,
            'status' => 'active',
            'external_user_id' => $payload['external_user_id'] ?? null,
            'scopes' => $payload['scopes'] ?? null,
            'metadata' => $metadata,
        ]);

        if (! empty($payload['access_token'])) {
            $connection->access_token_encrypted = Crypt::encryptString((string) $payload['access_token']);
            $connection->connected_at ??= Carbon::now();
        }

        if (! empty($payload['refresh_token'])) {
            $connection->refresh_token_encrypted = Crypt::encryptString((string) $payload['refresh_token']);
        }

        if (! empty($payload['token_expires_at'])) {
            $connection->token_expires_at = Carbon::parse((string) $payload['token_expires_at']);
        }

        $connection->save();

        return $connection->fresh();
    }

    public function revoke(MetaConnection $connection): MetaConnection
    {
        $connection->forceFill([
            'status' => 'revoked',
            'revoked_at' => Carbon::now(),
        ])->save();

        return $connection->fresh();
    }

    public function markSynced(MetaConnection $connection): void
    {
        $connection->forceFill([
            'last_synced_at' => Carbon::now(),
        ])->save();
    }
}
