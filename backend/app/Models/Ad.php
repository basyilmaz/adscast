<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ad extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'campaign_id',
        'ad_set_id',
        'creative_id',
        'meta_ad_id',
        'name',
        'status',
        'effective_status',
        'preview_url',
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

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class);
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }
}
