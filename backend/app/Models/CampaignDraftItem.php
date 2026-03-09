<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDraftItem extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'campaign_draft_id',
        'item_type',
        'title',
        'content',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function campaignDraft(): BelongsTo
    {
        return $this->belongsTo(CampaignDraft::class);
    }
}
