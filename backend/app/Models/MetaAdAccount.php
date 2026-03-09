<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAdAccount extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'meta_connection_id',
        'meta_business_id',
        'account_id',
        'name',
        'currency',
        'timezone_name',
        'status',
        'is_active',
        'metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function business(): BelongsTo
    {
        return $this->belongsTo(MetaBusiness::class, 'meta_business_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class);
    }
}
