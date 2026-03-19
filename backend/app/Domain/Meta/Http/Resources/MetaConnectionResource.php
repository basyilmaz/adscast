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
        $tokenStatus = 'missing';
        $daysUntilExpiry = $this->token_expires_at ? now()->diffInDays($this->token_expires_at, false) : null;

        if ($this->revoked_at) {
            $tokenStatus = 'revoked';
        } elseif ($this->token_expires_at && $this->token_expires_at->isPast()) {
            $tokenStatus = 'expired';
        } elseif ($this->token_expires_at && $daysUntilExpiry !== null && $daysUntilExpiry >= 0 && $daysUntilExpiry <= 7) {
            $tokenStatus = 'expiring_soon';
        } elseif ($this->access_token_encrypted) {
            $tokenStatus = 'active';
        }

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
            'connected_user_name' => data_get($this->metadata, 'connected_user_name'),
            'connection_mode' => data_get($this->metadata, 'connection_mode'),
            'token_status' => $tokenStatus,
            'has_refresh_token' => filled($this->refresh_token_encrypted),
            'ad_accounts_count' => $this->whenCounted('adAccounts'),
            'pages_count' => $this->whenCounted('pages'),
            'pixels_count' => $this->whenCounted('pixels'),
            'businesses_count' => $this->whenCounted('businesses'),
            'metadata' => $this->metadata,
        ];
    }
}
