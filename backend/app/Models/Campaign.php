<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'meta_ad_account_id',
        'meta_campaign_id',
        'name',
        'objective',
        'status',
        'effective_status',
        'buying_type',
        'daily_budget',
        'lifetime_budget',
        'start_time',
        'stop_time',
        'is_active',
        'metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'daily_budget' => 'decimal:2',
            'lifetime_budget' => 'decimal:2',
            'start_time' => 'datetime',
            'stop_time' => 'datetime',
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

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
