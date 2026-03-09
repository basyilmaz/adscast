<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncRun extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'meta_connection_id',
        'type',
        'status',
        'request_fingerprint',
        'cursor',
        'summary',
        'error_message',
        'started_at',
        'finished_at',
        'attempts',
        'initiated_by',
    ];

    protected function casts(): array
    {
        return [
            'cursor' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaConnection::class, 'meta_connection_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function rawPayloads(): HasMany
    {
        return $this->hasMany(RawApiPayload::class);
    }
}
