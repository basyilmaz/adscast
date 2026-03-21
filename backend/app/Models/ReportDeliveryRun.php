<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDeliveryRun extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'report_delivery_schedule_id',
        'report_snapshot_id',
        'delivery_channel',
        'status',
        'recipients',
        'prepared_at',
        'delivered_at',
        'triggered_by',
        'trigger_mode',
        'metadata',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'metadata' => 'array',
            'prepared_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportDeliverySchedule::class, 'report_delivery_schedule_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'report_snapshot_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
