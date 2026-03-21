<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class ReportShareLink extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'report_snapshot_id',
        'label',
        'token_hash',
        'token_encrypted',
        'allow_csv_download',
        'expires_at',
        'revoked_at',
        'last_accessed_at',
        'access_count',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'allow_csv_download' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'report_snapshot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decryptToken(): ?string
    {
        try {
            return Crypt::decryptString($this->token_encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
