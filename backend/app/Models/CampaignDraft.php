<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CampaignDraft extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'meta_ad_account_id',
        'objective',
        'product_service',
        'target_audience',
        'location',
        'budget_min',
        'budget_max',
        'offer',
        'landing_page_url',
        'tone_style',
        'existing_creative_availability',
        'notes',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'rejected_reason',
        'publish_response_metadata',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'publish_response_metadata' => 'array',
            'published_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CampaignDraftItem::class);
    }

    public function approval(): MorphOne
    {
        return $this->morphOne(Approval::class, 'approvable');
    }
}
