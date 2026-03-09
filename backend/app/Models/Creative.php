<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creative extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'meta_ad_account_id',
        'meta_creative_id',
        'name',
        'asset_type',
        'body',
        'headline',
        'description',
        'call_to_action',
        'destination_url',
        'metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
