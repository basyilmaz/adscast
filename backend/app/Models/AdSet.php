<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSet extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'campaign_id',
        'meta_ad_set_id',
        'name',
        'status',
        'effective_status',
        'optimization_goal',
        'billing_event',
        'bid_strategy',
        'daily_budget',
        'lifetime_budget',
        'start_time',
        'stop_time',
        'targeting',
        'metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'daily_budget' => 'decimal:2',
            'lifetime_budget' => 'decimal:2',
            'start_time' => 'datetime',
            'stop_time' => 'datetime',
            'targeting' => 'array',
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
