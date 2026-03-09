<?php

namespace App\Domain\Meta\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MetaConnection
 */
class MetaConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'provider' => $this->provider,
            'api_version' => $this->api_version,
            'status' => $this->status,
            'external_user_id' => $this->external_user_id,
            'token_expires_at' => $this->token_expires_at,
            'scopes' => $this->scopes,
            'connected_at' => $this->connected_at,
            'last_synced_at' => $this->last_synced_at,
            'revoked_at' => $this->revoked_at,
            'metadata' => $this->metadata,
        ];
    }
}
