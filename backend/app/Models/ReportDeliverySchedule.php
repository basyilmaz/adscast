<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDeliverySchedule extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'report_template_id',
        'delivery_channel',
        'cadence',
        'weekday',
        'month_day',
        'send_time',
        'timezone',
        'recipients',
        'configuration',
        'is_active',
        'next_run_at',
        'last_run_at',
        'last_status',
        'last_report_snapshot_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'configuration' => 'array',
            'is_active' => 'boolean',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function lastSnapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'last_report_snapshot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deliveryRuns(): HasMany
    {
        return $this->hasMany(ReportDeliveryRun::class);
    }
}
