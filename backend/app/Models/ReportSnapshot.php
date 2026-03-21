<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSnapshot extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'entity_type',
        'entity_id',
        'report_type',
        'title',
        'start_date',
        'end_date',
        'payload',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'payload' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ReportShareLink::class);
    }
}
