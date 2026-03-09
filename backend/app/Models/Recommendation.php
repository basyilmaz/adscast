<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'alert_id',
        'target_type',
        'target_id',
        'summary',
        'details',
        'action_type',
        'priority',
        'status',
        'source',
        'generated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }
}
