<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'approvable_type',
        'approvable_id',
        'status',
        'created_by',
        'reviewed_by',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'published_at',
        'rejection_reason',
        'publish_response_metadata',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'published_at' => 'datetime',
            'publish_response_metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }
}
