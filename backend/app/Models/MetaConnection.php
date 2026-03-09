<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetaConnection extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'provider',
        'api_version',
        'status',
        'external_user_id',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'token_expires_at',
        'scopes',
        'connected_at',
        'last_synced_at',
        'revoked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'revoked_at' => 'datetime',
            'scopes' => 'array',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(MetaBusiness::class);
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(MetaAdAccount::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(MetaPage::class);
    }

    public function pixels(): HasMany
    {
        return $this->hasMany(MetaPixel::class);
    }
}
