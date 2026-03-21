<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\ReportDeliveryRun;
use App\Models\ReportDeliverySchedule;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Operations\EntityContextResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportDeliveryScheduleService
{
    public function __construct(
        private readonly EntityContextResolver $entityContextResolver,
        private readonly ReportSnapshotService $reportSnapshotService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(string $workspaceId): array
    {
        $schedules = ReportDeliverySchedule::query()
            ->where('workspace_id', $workspaceId)
            ->with([
                'template:id,name,entity_type,entity_id,report_type',
                'lastSnapshot:id,title',
            ])
            ->latest()
            ->limit(20)
            ->get([
                'id',
                'workspace_id',
                'report_template_id',
                'delivery_channel',
                'cadence',
                'weekday',
                'month_day',
                'send_time',
                'timezone',
                'recipients',
                'is_active',
                'next_run_at',
                'last_run_at',
                'last_status',
                'last_report_snapshot_id',
                'created_at',
            ]);

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $schedules->map(function (ReportDeliverySchedule $schedule): array {
                return [
                    'type' => $schedule->template?->entity_type,
                    'id' => $schedule->template?->entity_id,
                ];
            })->all(),
        );

        $recentRuns = $this->recentRunsForSchedules($schedules->pluck('id'));

        return [
            'summary' => [
                'total_schedules' => $schedules->count(),
                'active_schedules' => $schedules->where('is_active', true)->count(),
                'due_schedules' => $schedules->filter(fn (ReportDeliverySchedule $schedule): bool => $schedule->is_active && $schedule->next_run_at !== null && $schedule->next_run_at->lte(now()))->count(),
                'runs_last_7_days' => ReportDeliveryRun::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ],
            'items' => $schedules->map(function (ReportDeliverySchedule $schedule) use ($contexts, $recentRuns): array {
                $template = $schedule->template;
                $context = $template
                    ? ($contexts[$this->entityContextResolver->key($template->entity_type, $template->entity_id)] ?? [
                        'entity_label' => 'Bilinmeyen varlik',
                        'context_label' => null,
                    ])
                    : [
                        'entity_label' => 'Silinmis sablon',
                        'context_label' => null,
                    ];

                return [
                    'id' => $schedule->id,
                    'delivery_channel' => $schedule->delivery_channel,
                    'delivery_channel_label' => $this->deliveryChannelLabel($schedule->delivery_channel),
                    'cadence' => $schedule->cadence,
                    'cadence_label' => $this->cadenceLabel($schedule),
                    'send_time' => $schedule->send_time,
                    'timezone' => $schedule->timezone,
                    'weekday' => $schedule->weekday,
                    'month_day' => $schedule->month_day,
                    'recipients' => $schedule->recipients ?? [],
                    'recipients_count' => count($schedule->recipients ?? []),
                    'is_active' => $schedule->is_active,
                    'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
                    'last_run_at' => $schedule->last_run_at?->toDateTimeString(),
                    'last_status' => $schedule->last_status,
                    'last_report_snapshot_id' => $schedule->last_report_snapshot_id,
                    'last_report_snapshot_title' => $schedule->lastSnapshot?->title,
                    'last_report_snapshot_url' => $schedule->last_report_snapshot_id
                        ? sprintf('/reports/snapshots/detail?id=%s', $schedule->last_report_snapshot_id)
                        : null,
                    'created_at' => $schedule->created_at?->toDateTimeString(),
                    'template' => [
                        'id' => $template?->id,
                        'name' => $template?->name,
                        'entity_type' => $template?->entity_type,
                        'entity_id' => $template?->entity_id,
                        'entity_label' => $context['entity_label'],
                        'context_label' => $context['context_label'],
                        'report_type' => $template?->report_type,
                        'report_url' => $template ? $this->reportUrl($template->entity_type, $template->entity_id) : null,
                    ],
                    'recent_runs' => $recentRuns->get($schedule->id, []),
                ];
            })->values()->all(),
        ];
    }

    public function store(
        Workspace $workspace,
        array $payload,
        ?User $actor = null,
        ?Request $request = null,
    ): ReportDeliverySchedule {
        $template = ReportTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail((string) $payload['report_template_id']);

        $schedule = ReportDeliverySchedule::query()->create([
            'workspace_id' => $workspace->id,
            'report_template_id' => $template->id,
            'delivery_channel' => (string) ($payload['delivery_channel'] ?? 'email_stub'),
            'cadence' => (string) $payload['cadence'],
            'weekday' => $payload['weekday'] ?? null,
            'month_day' => $payload['month_day'] ?? null,
            'send_time' => (string) $payload['send_time'],
            'timezone' => (string) ($payload['timezone'] ?? $workspace->timezone),
            'recipients' => array_values($payload['recipients'] ?? []),
            'configuration' => $payload['configuration'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'next_run_at' => $this->calculateNextRunAt(
                cadence: (string) $payload['cadence'],
                sendTime: (string) $payload['send_time'],
                timezone: (string) ($payload['timezone'] ?? $workspace->timezone),
                weekday: isset($payload['weekday']) ? (int) $payload['weekday'] : null,
                monthDay: isset($payload['month_day']) ? (int) $payload['month_day'] : null,
            ),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_delivery_schedule_created',
            targetType: 'report_delivery_schedule',
            targetId: $schedule->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'template_id' => $template->id,
                'cadence' => $schedule->cadence,
                'send_time' => $schedule->send_time,
                'timezone' => $schedule->timezone,
                'recipients_count' => count($schedule->recipients ?? []),
            ],
            request: $request,
        );

        return $schedule->fresh(['template', 'lastSnapshot']);
    }

    public function toggle(
        Workspace $workspace,
        string $scheduleId,
        ?bool $isActive,
        ?User $actor = null,
        ?Request $request = null,
    ): ReportDeliverySchedule {
        $schedule = ReportDeliverySchedule::query()
            ->where('workspace_id', $workspace->id)
            ->with('template')
            ->findOrFail($scheduleId);

        $nextState = $isActive ?? ! $schedule->is_active;
        $schedule->is_active = $nextState;
        $schedule->updated_by = $actor?->id;

        if ($nextState && (! $schedule->next_run_at || $schedule->next_run_at->lte(now()))) {
            $schedule->next_run_at = $this->calculateNextRunAtFromSchedule($schedule);
        }

        $schedule->save();

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_delivery_schedule_toggled',
            targetType: 'report_delivery_schedule',
            targetId: $schedule->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'is_active' => $schedule->is_active,
                'template_id' => $schedule->report_template_id,
            ],
            request: $request,
        );

        return $schedule->fresh(['template', 'lastSnapshot']);
    }

    /**
     * @return array<string, mixed>
     */
    public function runNow(
        Workspace $workspace,
        string $scheduleId,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $schedule = ReportDeliverySchedule::query()
            ->where('workspace_id', $workspace->id)
            ->with(['template', 'workspace'])
            ->findOrFail($scheduleId);

        $run = $this->runSchedule($schedule, 'manual', $actor?->id, $request);
        $snapshot = $run->snapshot;

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'snapshot_id' => $snapshot?->id,
            'snapshot_url' => $snapshot ? sprintf('/reports/snapshots/detail?id=%s', $snapshot->id) : null,
            'export_csv_url' => $snapshot ? sprintf('/api/v1/reports/snapshots/%s/export.csv', $snapshot->id) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runDueSchedules(
        ?string $workspaceId = null,
        ?string $scheduleId = null,
        bool $force = false,
    ): array {
        $query = ReportDeliverySchedule::query()
            ->with(['template', 'workspace'])
            ->where('is_active', true);

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        if ($scheduleId) {
            $query->where('id', $scheduleId);
        }

        if (! $force) {
            $query->whereNotNull('next_run_at')
                ->where('next_run_at', '<=', now());
        }

        $schedules = $query
            ->orderBy('next_run_at')
            ->get();

        $summary = [
            'schedules_considered' => $schedules->count(),
            'schedules_processed' => 0,
            'schedules_failed' => 0,
            'snapshots_created' => 0,
            'results' => [],
        ];

        foreach ($schedules as $schedule) {
            try {
                $run = $this->runSchedule($schedule, 'scheduled');

                $summary['schedules_processed']++;
                if ($run->report_snapshot_id) {
                    $summary['snapshots_created']++;
                }

                $summary['results'][] = [
                    'schedule_id' => $schedule->id,
                    'workspace_id' => $schedule->workspace_id,
                    'template_name' => $schedule->template?->name,
                    'status' => $run->status,
                    'snapshot_id' => $run->report_snapshot_id,
                    'next_run_at' => $schedule->fresh()->next_run_at?->toDateTimeString(),
                ];
            } catch (\Throwable $exception) {
                $summary['schedules_failed']++;
                $summary['results'][] = [
                    'schedule_id' => $schedule->id,
                    'workspace_id' => $schedule->workspace_id,
                    'template_name' => $schedule->template?->name,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    private function runSchedule(
        ReportDeliverySchedule $schedule,
        string $triggerMode,
        ?string $triggeredBy = null,
        ?Request $request = null,
    ): ReportDeliveryRun {
        $schedule->loadMissing(['template', 'workspace']);
        $template = $schedule->template;
        $workspace = $schedule->workspace;

        if (! $template || ! $workspace) {
            throw new \RuntimeException('Rapor schedule baglami eksik.');
        }

        [$startDate, $endDate] = $this->resolveDateRange($template, $schedule->timezone);

        $run = ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $schedule->id,
            'delivery_channel' => $schedule->delivery_channel,
            'status' => 'processing',
            'recipients' => $schedule->recipients ?? [],
            'prepared_at' => now(),
            'triggered_by' => $triggeredBy,
            'trigger_mode' => $triggerMode,
            'metadata' => [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'report_type' => $template->report_type,
                'range' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ],
        ]);

        try {
            $snapshot = $this->reportSnapshotService->storeSnapshot(
                workspace: $workspace,
                entityType: $template->entity_type,
                entityId: $template->entity_id,
                startDate: $startDate,
                endDate: $endDate,
                generatedBy: $triggeredBy,
                reportType: $template->report_type,
            );

            $deliveredAt = now();

            $run->forceFill([
                'report_snapshot_id' => $snapshot->id,
                'status' => 'delivered_stub',
                'delivered_at' => $deliveredAt,
                'metadata' => array_merge($run->metadata ?? [], [
                    'snapshot_title' => $snapshot->title,
                    'snapshot_url' => sprintf('/reports/snapshots/detail?id=%s', $snapshot->id),
                ]),
            ])->save();

            $schedule->forceFill([
                'last_run_at' => $deliveredAt,
                'last_status' => 'delivered_stub',
                'last_report_snapshot_id' => $snapshot->id,
                'next_run_at' => $schedule->is_active
                    ? $this->calculateNextRunAtFromSchedule($schedule, $deliveredAt)
                    : $schedule->next_run_at,
            ])->save();

            $this->auditLogService->log(
                actor: $triggeredBy ? User::query()->find($triggeredBy) : null,
                action: 'report_delivery_run_prepared',
                targetType: 'report_delivery_run',
                targetId: $run->id,
                organizationId: $workspace->organization_id,
                workspaceId: $workspace->id,
                metadata: [
                    'schedule_id' => $schedule->id,
                    'template_id' => $template->id,
                    'snapshot_id' => $snapshot->id,
                    'trigger_mode' => $triggerMode,
                    'delivery_channel' => $schedule->delivery_channel,
                ],
                request: $request,
            );

            return $run->fresh(['snapshot']);
        } catch (\Throwable $exception) {
            $failedAt = now();

            $run->forceFill([
                'status' => 'failed',
                'error_message' => Str::limit($exception->getMessage(), 2000),
                'metadata' => array_merge($run->metadata ?? [], [
                    'error_class' => $exception::class,
                ]),
            ])->save();

            $schedule->forceFill([
                'last_run_at' => $failedAt,
                'last_status' => 'failed',
                'next_run_at' => $schedule->is_active
                    ? $this->calculateNextRunAtFromSchedule($schedule, $failedAt)
                    : $schedule->next_run_at,
            ])->save();

            $this->auditLogService->log(
                actor: $triggeredBy ? User::query()->find($triggeredBy) : null,
                action: 'report_delivery_run_failed',
                targetType: 'report_delivery_run',
                targetId: $run->id,
                organizationId: $workspace->organization_id,
                workspaceId: $workspace->id,
                metadata: [
                    'schedule_id' => $schedule->id,
                    'template_id' => $template->id,
                    'trigger_mode' => $triggerMode,
                    'error' => $exception->getMessage(),
                ],
                request: $request,
            );

            throw $exception;
        }
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function resolveDateRange(ReportTemplate $template, string $timezone): array
    {
        $reference = Carbon::now($timezone);
        $startDate = $reference->copy()->subDays(max($template->default_range_days - 1, 0))->startOfDay();
        $endDate = $reference->copy()->endOfDay();

        return [$startDate, $endDate];
    }

    private function calculateNextRunAtFromSchedule(
        ReportDeliverySchedule $schedule,
        ?CarbonInterface $reference = null,
    ): CarbonInterface {
        return $this->calculateNextRunAt(
            cadence: $schedule->cadence,
            sendTime: $schedule->send_time,
            timezone: $schedule->timezone,
            weekday: $schedule->weekday,
            monthDay: $schedule->month_day,
            reference: $reference,
        );
    }

    private function calculateNextRunAt(
        string $cadence,
        string $sendTime,
        string $timezone,
        ?int $weekday = null,
        ?int $monthDay = null,
        ?CarbonInterface $reference = null,
    ): CarbonInterface {
        [$hour, $minute] = array_map('intval', explode(':', $sendTime));
        $localReference = ($reference ? Carbon::parse($reference) : Carbon::now())->setTimezone($timezone);

        $candidate = match ($cadence) {
            'daily' => $this->dailyCandidate($localReference, $hour, $minute),
            'weekly' => $this->weeklyCandidate($localReference, $hour, $minute, $weekday ?? 1),
            'monthly' => $this->monthlyCandidate($localReference, $hour, $minute, $monthDay ?? 1),
            default => throw new \InvalidArgumentException('Desteklenmeyen delivery cadence.'),
        };

        return $candidate->utc();
    }

    private function dailyCandidate(CarbonInterface $reference, int $hour, int $minute): CarbonInterface
    {
        $candidate = Carbon::parse($reference)->setTime($hour, $minute, 0);

        if (! $candidate->gt($reference)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    private function weeklyCandidate(CarbonInterface $reference, int $hour, int $minute, int $weekday): CarbonInterface
    {
        $daysUntil = ($weekday - (int) $reference->dayOfWeekIso + 7) % 7;
        $candidate = Carbon::parse($reference)
            ->startOfDay()
            ->addDays($daysUntil)
            ->setTime($hour, $minute, 0);

        if (! $candidate->gt($reference)) {
            $candidate->addWeek();
        }

        return $candidate;
    }

    private function monthlyCandidate(CarbonInterface $reference, int $hour, int $minute, int $monthDay): CarbonInterface
    {
        $candidate = Carbon::parse($reference)
            ->startOfDay()
            ->day(min($monthDay, (int) $reference->copy()->endOfMonth()->day))
            ->setTime($hour, $minute, 0);

        if (! $candidate->gt($reference)) {
            $nextMonth = Carbon::parse($reference)->addMonthNoOverflow()->startOfDay();
            $candidate = $nextMonth
                ->day(min($monthDay, (int) $nextMonth->copy()->endOfMonth()->day))
                ->setTime($hour, $minute, 0);
        }

        return $candidate;
    }

    /**
     * @param  Collection<int, string>  $scheduleIds
     * @return Collection<string, array<int, array<string, string|null>>>
     */
    private function recentRunsForSchedules(Collection $scheduleIds): Collection
    {
        if ($scheduleIds->isEmpty()) {
            return collect();
        }

        return ReportDeliveryRun::query()
            ->whereIn('report_delivery_schedule_id', $scheduleIds->all())
            ->with('snapshot:id,title')
            ->latest('prepared_at')
            ->get([
                'id',
                'report_delivery_schedule_id',
                'report_snapshot_id',
                'status',
                'trigger_mode',
                'prepared_at',
                'delivered_at',
                'error_message',
            ])
            ->groupBy('report_delivery_schedule_id')
            ->map(fn (Collection $runs): array => $runs
                ->take(3)
                ->map(function (ReportDeliveryRun $run): array {
                    return [
                        'id' => $run->id,
                        'status' => $run->status,
                        'trigger_mode' => $run->trigger_mode,
                        'prepared_at' => $run->prepared_at?->toDateTimeString(),
                        'delivered_at' => $run->delivered_at?->toDateTimeString(),
                        'snapshot_title' => $run->snapshot?->title,
                        'snapshot_url' => $run->report_snapshot_id
                            ? sprintf('/reports/snapshots/detail?id=%s', $run->report_snapshot_id)
                            : null,
                        'error_message' => $run->error_message,
                    ];
                })
                ->values()
                ->all());
    }

    private function cadenceLabel(ReportDeliverySchedule $schedule): string
    {
        return match ($schedule->cadence) {
            'daily' => sprintf('Her gun %s', $schedule->send_time),
            'weekly' => sprintf('Her %s %s', $this->weekdayLabel((int) ($schedule->weekday ?? 1)), $schedule->send_time),
            'monthly' => sprintf('Her ay %d. gun %s', (int) ($schedule->month_day ?? 1), $schedule->send_time),
            default => $schedule->cadence,
        };
    }

    private function deliveryChannelLabel(string $channel): string
    {
        return match ($channel) {
            'email_stub' => 'Email Stub',
            default => $channel,
        };
    }

    private function weekdayLabel(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Pazartesi',
            2 => 'Sali',
            3 => 'Carsamba',
            4 => 'Persembe',
            5 => 'Cuma',
            6 => 'Cumartesi',
            7 => 'Pazar',
            default => 'Gun',
        };
    }

    private function reportUrl(string $entityType, string $entityId): string
    {
        return match ($entityType) {
            'account' => sprintf('/reports/account?id=%s', $entityId),
            'campaign' => sprintf('/reports/campaign?id=%s', $entityId),
            default => '/reports',
        };
    }
}
