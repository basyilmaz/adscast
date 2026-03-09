<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsightDaily extends BaseModel
{
    use HasFactory;

    protected $table = 'insight_daily';

    protected $fillable = [
        'workspace_id',
        'level',
        'entity_id',
        'entity_external_id',
        'date',
        'spend',
        'impressions',
        'reach',
        'frequency',
        'clicks',
        'link_clicks',
        'ctr',
        'cpc',
        'cpm',
        'results',
        'cost_per_result',
        'leads',
        'purchases',
        'roas',
        'conversions',
        'actions',
        'attribution_setting',
        'source',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spend' => 'decimal:2',
            'frequency' => 'decimal:3',
            'ctr' => 'decimal:4',
            'cpc' => 'decimal:4',
            'cpm' => 'decimal:4',
            'results' => 'decimal:2',
            'cost_per_result' => 'decimal:4',
            'leads' => 'decimal:2',
            'purchases' => 'decimal:2',
            'roas' => 'decimal:4',
            'conversions' => 'decimal:2',
            'actions' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
