<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'entity_type',
        'entity_id',
        'code',
        'severity',
        'summary',
        'explanation',
        'recommended_action',
        'confidence',
        'status',
        'date_detected',
        'source_rule_version',
        'metadata',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'date_detected' => 'date',
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }
}
