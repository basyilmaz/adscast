<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaBusiness extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'meta_connection_id',
        'business_id',
        'name',
        'verification_status',
        'raw_snapshot_id',
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

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaConnection::class, 'meta_connection_id');
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(MetaAdAccount::class);
    }
}
