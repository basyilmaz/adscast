<?php

namespace App\Domain\Meta\Services;

use App\Models\MetaConnection;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class MetaConnectionService
{
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

        $connection->fill([
            'api_version' => $payload['api_version'] ?? 'v20.0',
            'status' => 'active',
            'external_user_id' => $payload['external_user_id'] ?? null,
            'scopes' => $payload['scopes'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        if (! empty($payload['access_token'])) {
            $connection->access_token_encrypted = Crypt::encryptString($payload['access_token']);
            $connection->connected_at ??= Carbon::now();
        }

        if (! empty($payload['refresh_token'])) {
            $connection->refresh_token_encrypted = Crypt::encryptString($payload['refresh_token']);
        }

        if (! empty($payload['token_expires_at'])) {
            $connection->token_expires_at = Carbon::parse($payload['token_expires_at']);
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
