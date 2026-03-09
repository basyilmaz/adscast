<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIGeneration extends BaseModel
{
    use HasFactory;

    protected $table = 'ai_generations';

    protected $fillable = [
        'workspace_id',
        'entity_type',
        'entity_id',
        'provider',
        'model',
        'prompt_template',
        'prompt_context',
        'prompt_text',
        'output',
        'status',
        'token_usage',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_context' => 'array',
            'output' => 'array',
            'token_usage' => 'array',
            'generated_at' => 'datetime',
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
}
