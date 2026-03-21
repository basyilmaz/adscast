<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportContact extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'email',
        'company_name',
        'role_label',
        'tags',
        'notes',
        'is_primary',
        'is_active',
        'last_used_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
