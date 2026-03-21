<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Reporting\Mail\ScheduledReportDeliveryMail;
use App\Models\ReportDeliveryRun;
use App\Models\ReportDeliverySchedule;
use App\Models\ReportSnapshot;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Operations\EntityContextResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportDeliveryScheduleService
{
    public function __construct(
        private readonly EntityContextResolver $entityContextResolver,
        private readonly ReportContactService $reportContactService,
        private readonly ReportRecipientGroupSelectionService $reportRecipientGroupSelectionService,
        private readonly ReportRecipientPresetService $reportRecipientPresetService,
        private readonly ReportSnapshotService $reportSnapshotService,
        private readonly ReportShareLinkService $reportShareLinkService,
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
                'configuration',
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

        $recentRuns = $this->recentRunsForSchedules($workspaceId, $schedules->pluck('id'));
        $deliveryRunIndex = $this->deliveryRunIndex($workspaceId);

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
            'delivery_capabilities' => $this->deliveryCapabilities(),
            'run_summary' => $deliveryRunIndex['summary'],
            'delivery_runs' => $deliveryRunIndex['items'],
            'items' => $schedules->map(function (ReportDeliverySchedule $schedule) use ($contexts, $recentRuns): array {
                $template = $schedule->template;
                $recipientResolution = $this->resolveScheduleRecipients($schedule->workspace_id, $schedule);
                $recipientGroupSelection = $this->reportRecipientGroupSelectionService->fromSchedule(
                    $schedule,
                    $recipientResolution['recipient_group_summary'],
                );
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
                    'recipient_preset_id' => $recipientResolution['recipient_preset_id'],
                    'recipient_preset_name' => $recipientResolution['recipient_preset_name'],
                    'contact_tags' => $recipientResolution['contact_tags'],
                    'tagged_contacts' => $recipientResolution['tagged_contacts'],
                    'tagged_contacts_count' => count($recipientResolution['tagged_contacts']),
                    'resolved_recipients' => $recipientResolution['resolved_recipients'],
                    'resolved_recipients_count' => count($recipientResolution['resolved_recipients']),
                    'recipient_group_selection' => $recipientGroupSelection,
                    'recipient_group_summary' => $recipientResolution['recipient_group_summary'],
                    'share_delivery' => $this->shareDeliveryConfiguration($schedule),
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

    /**
     * @return array<string, mixed>
     */
    public function retryRun(
        Workspace $workspace,
        string $runId,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $run = ReportDeliveryRun::query()
            ->where('workspace_id', $workspace->id)
            ->with([
                'schedule.template',
                'schedule.workspace',
            ])
            ->findOrFail($runId);

        if ($run->status !== 'failed') {
            throw ValidationException::withMessages([
                'run_id' => 'Sadece basarisiz teslim kayitlari tekrar denenebilir.',
            ]);
        }

        if (data_get($run->metadata, 'retry.next_run_id')) {
            throw ValidationException::withMessages([
                'run_id' => 'Bu teslim kaydi zaten tekrar denenmis.',
            ]);
        }

        $schedule = $run->schedule;

        if (! $schedule || ! $schedule->template || ! $schedule->workspace) {
            throw ValidationException::withMessages([
                'run_id' => 'Retry icin gerekli schedule baglami bulunamadi.',
            ]);
        }

        $retryRun = $this->runSchedule(
            schedule: $schedule,
            triggerMode: 'retry',
            triggeredBy: $actor?->id,
            request: $request,
            extraMetadata: [
                'retry' => [
                    'source_run_id' => $run->id,
                ],
            ],
        );

        $runMetadata = $run->metadata ?? [];
        $runMetadata['retry'] = array_merge(
            is_array(data_get($runMetadata, 'retry')) ? data_get($runMetadata, 'retry') : [],
            [
                'attempted_at' => now()->toDateTimeString(),
                'next_run_id' => $retryRun->id,
                'requested_by' => $actor?->id,
            ],
        );

        $run->forceFill([
            'metadata' => $runMetadata,
        ])->save();

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_delivery_run_retried',
            targetType: 'report_delivery_run',
            targetId: $retryRun->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'source_run_id' => $run->id,
                'schedule_id' => $schedule->id,
                'status' => $retryRun->status,
            ],
            request: $request,
        );

        $snapshot = $retryRun->snapshot;

        return [
            'run_id' => $retryRun->id,
            'status' => $retryRun->status,
            'snapshot_id' => $snapshot?->id,
            'snapshot_url' => $snapshot ? sprintf('/reports/snapshots/detail?id=%s', $snapshot->id) : null,
            'export_csv_url' => $snapshot ? sprintf('/api/v1/reports/snapshots/%s/export.csv', $snapshot->id) : null,
            'retry_of_run_id' => $run->id,
            'share_link' => data_get($retryRun->metadata, 'share_link'),
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
        $preset = null;

        if (isset($payload['recipient_preset_id']) && $payload['recipient_preset_id'] !== '') {
            $preset = $this->reportRecipientPresetService->find($workspace->id, (string) $payload['recipient_preset_id']);

            if (! $preset || ! ($preset['is_active'] ?? true)) {
                throw ValidationException::withMessages([
                    'recipient_preset_id' => 'Secilen alici grubu bulunamadi veya aktif degil.',
                ]);
            }
        }

        $recipientGroup = $this->reportRecipientPresetService->resolveRecipientGroup(
            workspaceId: $workspace->id,
            preset: $preset,
            manualRecipients: is_array($payload['recipients'] ?? null) ? $payload['recipients'] : [],
            manualContactTags: is_array($payload['contact_tags'] ?? null) ? $payload['contact_tags'] : [],
        );

        $schedule = ReportDeliverySchedule::query()->create([
            'workspace_id' => $workspace->id,
            'report_template_id' => $template->id,
            'delivery_channel' => (string) ($payload['delivery_channel'] ?? 'email_stub'),
            'cadence' => (string) $payload['cadence'],
            'weekday' => $payload['weekday'] ?? null,
            'month_day' => $payload['month_day'] ?? null,
            'send_time' => (string) $payload['send_time'],
            'timezone' => (string) ($payload['timezone'] ?? $workspace->timezone),
            'recipients' => $recipientGroup['manual_recipients'],
            'configuration' => $this->buildScheduleConfiguration($payload, $preset, $recipientGroup),
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
                'recipient_preset_id' => $preset['id'] ?? data_get($schedule->configuration, 'recipient_preset_id'),
                'contact_tags' => $this->contactTagsFromSchedule($schedule),
                'recipient_group_selection' => data_get($schedule->configuration, 'recipient_group_selection'),
                'auto_share_enabled' => $this->shareDeliveryConfiguration($schedule)['enabled'],
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
            'share_link' => data_get($run->metadata, 'share_link'),
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
                    'share_link' => data_get($run->metadata, 'share_link'),
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
        array $extraMetadata = [],
    ): ReportDeliveryRun {
        $schedule->loadMissing(['template', 'workspace']);
        $template = $schedule->template;
        $workspace = $schedule->workspace;
        $actor = $triggeredBy ? User::query()->find($triggeredBy) : null;

        if (! $template || ! $workspace) {
            throw new \RuntimeException('Rapor schedule baglami eksik.');
        }

        [$startDate, $endDate] = $this->resolveDateRange($template, $schedule->timezone);
        $recipientResolution = $this->resolveScheduleRecipients($workspace->id, $schedule);
        $recipientGroupSelection = $this->reportRecipientGroupSelectionService->fromSchedule(
            $schedule,
            $recipientResolution['recipient_group_summary'],
        );

        $run = ReportDeliveryRun::query()->create([
            'workspace_id' => $workspace->id,
            'report_delivery_schedule_id' => $schedule->id,
            'delivery_channel' => $schedule->delivery_channel,
            'status' => 'processing',
            'recipients' => $recipientResolution['resolved_recipients'],
            'prepared_at' => now(),
            'triggered_by' => $triggeredBy,
            'trigger_mode' => $triggerMode,
            'metadata' => array_merge([
                'template_id' => $template->id,
                'template_name' => $template->name,
                'report_type' => $template->report_type,
                'recipient_preset_id' => $recipientResolution['recipient_preset_id'],
                'recipient_preset_name' => $recipientResolution['recipient_preset_name'],
                'contact_tags' => $recipientResolution['contact_tags'],
                'tagged_contacts' => $recipientResolution['tagged_contacts'],
                'recipient_group_summary' => $recipientResolution['recipient_group_summary'],
                'recipient_group_selection' => $recipientGroupSelection,
                'range' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ], $extraMetadata),
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

            $shareLink = $this->createAutomatedShareLink(
                workspace: $workspace,
                snapshot: $snapshot,
                schedule: $schedule,
                run: $run,
                actor: $actor,
                request: $request,
                triggerMode: $triggerMode,
            );

            $delivery = $this->deliverReport(
                workspace: $workspace,
                template: $template,
                schedule: $schedule,
                snapshot: $snapshot,
                shareLink: $shareLink,
                resolvedRecipients: $recipientResolution['resolved_recipients'],
            );

            $this->reportContactService->touchLastUsedByEmails($workspace->id, $recipientResolution['resolved_recipients']);

            $run->forceFill([
                'report_snapshot_id' => $snapshot->id,
                'status' => $delivery['status'],
                'delivered_at' => $delivery['delivered_at'],
                'metadata' => array_merge($run->metadata ?? [], [
                    'snapshot_title' => $snapshot->title,
                    'snapshot_url' => sprintf('/reports/snapshots/detail?id=%s', $snapshot->id),
                    'share_link' => $shareLink,
                    'delivery' => $delivery['metadata'],
                ]),
            ])->save();

            $schedule->forceFill([
                'last_run_at' => $delivery['delivered_at'],
                'last_status' => $delivery['status'],
                'last_report_snapshot_id' => $snapshot->id,
                'next_run_at' => $schedule->is_active
                    ? $this->calculateNextRunAtFromSchedule($schedule, $delivery['delivered_at'])
                    : $schedule->next_run_at,
            ])->save();

            $this->auditLogService->log(
                actor: $actor,
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
                    'recipient_group_selection' => $recipientGroupSelection,
                    'share_link_id' => data_get($shareLink, 'id'),
                    'delivery_status' => $delivery['status'],
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
                actor: $actor,
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
    private function recentRunsForSchedules(string $workspaceId, Collection $scheduleIds): Collection
    {
        if ($scheduleIds->isEmpty()) {
            return collect();
        }

        $runs = ReportDeliveryRun::query()
            ->whereIn('report_delivery_schedule_id', $scheduleIds->all())
            ->with([
                'snapshot:id,title',
                'schedule:id,workspace_id,report_template_id,delivery_channel,cadence,weekday,month_day,send_time,timezone,recipients,configuration,is_active,next_run_at',
                'schedule.template:id,name,entity_type,entity_id,report_type',
            ])
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
                'metadata',
            ]);

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $runs->map(function (ReportDeliveryRun $run): ?array {
                $template = $run->schedule?->template;

                if (! $template || ! $template->entity_type || ! $template->entity_id) {
                    return null;
                }

                return [
                    'type' => $template->entity_type,
                    'id' => $template->entity_id,
                ];
            })->filter()->values()->all(),
        );

        return $runs
            ->groupBy('report_delivery_schedule_id')
            ->map(fn (Collection $runs): array => $runs
                ->take(3)
                ->map(fn (ReportDeliveryRun $run): array => $this->serializeRun(
                    $run,
                    $contexts[$this->entityContextResolver->key(
                        $run->schedule?->template?->entity_type,
                        $run->schedule?->template?->entity_id,
                    )] ?? null,
                ))
                ->values()
                ->all());
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    private function deliveryRunIndex(string $workspaceId): array
    {
        $runs = ReportDeliveryRun::query()
            ->where('workspace_id', $workspaceId)
            ->with([
                'snapshot:id,title',
                'schedule:id,workspace_id,report_template_id,delivery_channel,cadence,weekday,month_day,send_time,timezone,recipients,configuration,is_active,next_run_at',
                'schedule.template:id,name,entity_type,entity_id,report_type',
            ])
            ->latest('prepared_at')
            ->limit(20)
            ->get([
                'id',
                'workspace_id',
                'report_delivery_schedule_id',
                'report_snapshot_id',
                'status',
                'trigger_mode',
                'prepared_at',
                'delivered_at',
                'error_message',
                'metadata',
            ]);

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $runs->map(function (ReportDeliveryRun $run): ?array {
                $template = $run->schedule?->template;

                if (! $template || ! $template->entity_type || ! $template->entity_id) {
                    return null;
                }

                return [
                    'type' => $template->entity_type,
                    'id' => $template->entity_id,
                ];
            })->filter()->values()->all(),
        );

        $items = $runs->map(fn (ReportDeliveryRun $run): array => $this->serializeRun(
            $run,
            $contexts[$this->entityContextResolver->key(
                $run->schedule?->template?->entity_type,
                $run->schedule?->template?->entity_id,
            )] ?? null,
        ))->values()->all();

        return [
            'summary' => [
                'total_runs' => ReportDeliveryRun::query()
                    ->where('workspace_id', $workspaceId)
                    ->count(),
                'failed_runs' => ReportDeliveryRun::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('status', 'failed')
                    ->count(),
                'delivered_runs' => ReportDeliveryRun::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('status', ['delivered_stub', 'delivered_email'])
                    ->count(),
                'retryable_runs' => collect($items)->where('can_retry', true)->count(),
                'latest_failed_at' => ReportDeliveryRun::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('status', 'failed')
                    ->latest('prepared_at')
                    ->value('prepared_at'),
            ],
            'items' => $items,
        ];
    }

    /**
     * @param  array{entity_label?: string|null, context_label?: string|null}|null  $context
     * @return array<string, mixed>
     */
    private function serializeRun(ReportDeliveryRun $run, ?array $context = null): array
    {
        $schedule = $run->schedule;
        $template = $schedule?->template;
        $retryMetadata = is_array(data_get($run->metadata, 'retry')) ? data_get($run->metadata, 'retry') : [];
        $recipientResolution = $schedule
            ? $this->resolveScheduleRecipients($schedule->workspace_id, $schedule)
            : [
                'contact_tags' => $this->reportContactService->normalizeTags(
                    is_array(data_get($run->metadata, 'contact_tags')) ? data_get($run->metadata, 'contact_tags') : [],
                ),
                'tagged_contacts' => is_array(data_get($run->metadata, 'tagged_contacts')) ? data_get($run->metadata, 'tagged_contacts') : [],
                'resolved_recipients' => $this->normalizeRecipients($run->recipients ?? []),
            ];

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
            'share_link' => data_get($run->metadata, 'share_link'),
            'delivery' => data_get($run->metadata, 'delivery'),
            'error_message' => $run->error_message,
            'can_retry' => $run->status === 'failed'
                && ! data_get($retryMetadata, 'next_run_id')
                && $run->report_delivery_schedule_id !== null
                && $schedule !== null
                && $template !== null,
            'retry_of_run_id' => data_get($retryMetadata, 'source_run_id'),
            'retried_by_run_id' => data_get($retryMetadata, 'next_run_id'),
            'contact_tags' => $recipientResolution['contact_tags'],
            'tagged_contacts' => $recipientResolution['tagged_contacts'],
            'tagged_contacts_count' => count($recipientResolution['tagged_contacts']),
            'resolved_recipients' => $recipientResolution['resolved_recipients'],
            'resolved_recipients_count' => count($recipientResolution['resolved_recipients']),
            'recipient_group_selection' => $this->reportRecipientGroupSelectionService->fromRun($run, $schedule),
            'recipient_group_summary' => $schedule
                ? $recipientResolution['recipient_group_summary']
                : (is_array(data_get($run->metadata, 'recipient_group_summary'))
                    ? data_get($run->metadata, 'recipient_group_summary')
                    : null),
            'schedule' => $schedule ? [
                'id' => $schedule->id,
                'cadence' => $schedule->cadence,
                'cadence_label' => $this->cadenceLabel($schedule),
                'delivery_channel' => $schedule->delivery_channel,
                'delivery_channel_label' => $this->deliveryChannelLabel($schedule->delivery_channel),
                'is_active' => $schedule->is_active,
                'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
                'template' => [
                    'id' => $template?->id,
                    'name' => $template?->name,
                    'entity_type' => $template?->entity_type,
                    'entity_id' => $template?->entity_id,
                    'entity_label' => $context['entity_label'] ?? null,
                    'context_label' => $context['context_label'] ?? null,
                    'report_url' => ($template && $template->entity_type && $template->entity_id)
                        ? $this->reportUrl($template->entity_type, $template->entity_id)
                        : null,
                ],
            ] : null,
        ];
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
            'email' => 'Gercek Email',
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

    /**
     * @return array<string, mixed>
     */
    private function buildScheduleConfiguration(
        array $payload,
        ?array $preset = null,
        ?array $recipientGroup = null,
    ): array
    {
        $configuration = $payload['configuration'] ?? [];

        if (! is_array($configuration)) {
            $configuration = [];
        }

        $configuration['auto_share'] = [
            'enabled' => (bool) ($payload['auto_share_enabled'] ?? false),
            'label_template' => $payload['share_label_template'] ?? null,
            'expires_in_days' => isset($payload['share_expires_in_days'])
                ? (int) $payload['share_expires_in_days']
                : null,
            'allow_csv_download' => (bool) ($payload['share_allow_csv_download'] ?? false),
        ];

        $configuration['contact_tags'] = $this->reportContactService->normalizeTags(
            is_array($payload['contact_tags'] ?? null) ? $payload['contact_tags'] : [],
        );
        $configuration['recipient_preset_id'] = isset($payload['recipient_preset_id']) && $payload['recipient_preset_id'] !== ''
            ? (string) $payload['recipient_preset_id']
            : (is_string(data_get($configuration, 'recipient_preset_id')) && data_get($configuration, 'recipient_preset_id') !== ''
                ? (string) data_get($configuration, 'recipient_preset_id')
                : null);
        $resolvedRecipientGroup = is_array($recipientGroup) ? $recipientGroup : [
            'recipient_group_summary' => is_array(data_get($configuration, 'recipient_group_summary'))
                ? data_get($configuration, 'recipient_group_summary')
                : [],
        ];
        $configuration['recipient_group_summary'] = is_array($resolvedRecipientGroup['recipient_group_summary'] ?? null)
            ? $resolvedRecipientGroup['recipient_group_summary']
            : [];
        $configuration['recipient_group_selection'] = $this->reportRecipientGroupSelectionService->fromPayload(
            payload: $payload,
            recipientGroupSummary: $configuration['recipient_group_summary'],
            preset: $preset,
        );

        return $configuration;
    }

    /**
     * @return array{enabled: bool, label_template: ?string, expires_in_days: ?int, allow_csv_download: bool}
     */
    private function shareDeliveryConfiguration(ReportDeliverySchedule $schedule): array
    {
        $configuration = is_array($schedule->configuration) ? $schedule->configuration : [];
        $config = is_array($configuration['auto_share'] ?? null) ? $configuration['auto_share'] : [];

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'label_template' => isset($config['label_template']) && $config['label_template'] !== ''
                ? (string) $config['label_template']
                : null,
            'expires_in_days' => isset($config['expires_in_days']) ? (int) $config['expires_in_days'] : null,
            'allow_csv_download' => (bool) ($config['allow_csv_download'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function createAutomatedShareLink(
        Workspace $workspace,
        \App\Models\ReportSnapshot $snapshot,
        ReportDeliverySchedule $schedule,
        ReportDeliveryRun $run,
        ?User $actor,
        ?Request $request,
        string $triggerMode,
    ): ?array {
        $shareConfig = $this->shareDeliveryConfiguration($schedule);

        if (! $shareConfig['enabled']) {
            return null;
        }

        return $this->reportShareLinkService->createForDeliveryRun(
            workspace: $workspace,
            snapshot: $snapshot,
            payload: [
                'label' => $this->resolveShareLabel($schedule, $snapshot, $shareConfig['label_template']),
                'expires_in_days' => $shareConfig['expires_in_days'],
                'allow_csv_download' => $shareConfig['allow_csv_download'],
            ],
            context: [
                'schedule_id' => $schedule->id,
                'report_delivery_run_id' => $run->id,
                'trigger_mode' => $triggerMode,
            ],
            actor: $actor,
            request: $request,
        );
    }

    private function resolveShareLabel(
        ReportDeliverySchedule $schedule,
        ReportSnapshot $snapshot,
        ?string $labelTemplate,
    ): string {
        $template = trim((string) $labelTemplate);

        if ($template === '') {
            return $snapshot->title;
        }

        $replacements = [
            '{snapshot_title}' => $snapshot->title,
            '{template_name}' => $schedule->template?->name ?? $snapshot->title,
            '{start_date}' => $snapshot->start_date?->toDateString() ?? '-',
            '{end_date}' => $snapshot->end_date?->toDateString() ?? '-',
        ];

        return strtr($template, $replacements);
    }

    /**
     * @return array{default_mailer: string, real_email_available: bool, from_address: ?string, from_name: ?string, note: string}
     */
    public function deliveryCapabilities(): array
    {
        $defaultMailer = (string) config('mail.default', 'log');
        $realEmailAvailable = ! in_array($defaultMailer, ['log', 'array'], true);

        return [
            'default_mailer' => $defaultMailer,
            'real_email_available' => $realEmailAvailable,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'note' => $realEmailAvailable
                ? 'Gercek email gonderimi hazir. Schedule kanalini email olarak secip canli teslim kullanabilirsiniz.'
                : 'MAIL_MAILER log/array modunda. Gercek dis e-posta gonderimi icin SMTP veya desteklenen bir mail provider tanimlanmali.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $shareLink
     * @return array{status: string, delivered_at: CarbonInterface, metadata: array<string, mixed>}
     */
    private function deliverReport(
        Workspace $workspace,
        ReportTemplate $template,
        ReportDeliverySchedule $schedule,
        ReportSnapshot $snapshot,
        ?array $shareLink,
        array $resolvedRecipients,
    ): array {
        return match ($schedule->delivery_channel) {
            'email' => $this->deliverByEmail($workspace, $template, $schedule, $snapshot, $shareLink, $resolvedRecipients),
            'email_stub' => $this->deliverByStub($schedule, $shareLink, $resolvedRecipients),
            default => throw new \InvalidArgumentException('Desteklenmeyen delivery channel.'),
        };
    }

    /**
     * @param  array<string, mixed>|null  $shareLink
     * @return array{status: string, delivered_at: CarbonInterface, metadata: array<string, mixed>}
     */
    private function deliverByStub(
        ReportDeliverySchedule $schedule,
        ?array $shareLink,
        array $resolvedRecipients,
    ): array {
        $deliveredAt = now();

        return [
            'status' => 'delivered_stub',
            'delivered_at' => $deliveredAt,
            'metadata' => [
                'channel' => 'email_stub',
                'channel_label' => $this->deliveryChannelLabel('email_stub'),
                'mailer' => config('mail.default'),
                'recipients' => $resolvedRecipients,
                'recipients_count' => count($resolvedRecipients),
                'share_link_used' => $shareLink !== null,
                'outbound' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $shareLink
     * @return array{status: string, delivered_at: CarbonInterface, metadata: array<string, mixed>}
     */
    private function deliverByEmail(
        Workspace $workspace,
        ReportTemplate $template,
        ReportDeliverySchedule $schedule,
        ReportSnapshot $snapshot,
        ?array $shareLink,
        array $resolvedRecipients,
    ): array {
        $recipients = array_values(array_filter($resolvedRecipients));

        if ($recipients === []) {
            throw new \RuntimeException('Email delivery icin en az bir alici gereklidir.');
        }

        $reportPayload = $this->reportSnapshotService->snapshotDetail($snapshot);

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(
                new ScheduledReportDeliveryMail(
                    workspace: $workspace,
                    template: $template,
                    snapshot: $snapshot,
                    reportPayload: $reportPayload,
                    shareLink: $shareLink,
                ),
            );
        }

        $deliveredAt = now();

        return [
            'status' => 'delivered_email',
            'delivered_at' => $deliveredAt,
            'metadata' => [
                'channel' => 'email',
                'channel_label' => $this->deliveryChannelLabel('email'),
                'mailer' => config('mail.default'),
                'recipients' => $recipients,
                'recipients_count' => count($recipients),
                'share_link_used' => $shareLink !== null,
                'outbound' => ! in_array((string) config('mail.default'), ['log', 'array'], true),
            ],
        ];
    }

    /**
     * @return array{
     *   recipient_preset_id: string|null,
     *   recipient_preset_name: string|null,
     *   contact_tags: array<int, string>,
     *   tagged_contacts: array<int, array<string, mixed>>,
     *   resolved_recipients: array<int, string>,
     *   recipient_group_summary: array<string, mixed>
     * }
     */
    private function resolveScheduleRecipients(string $workspaceId, ReportDeliverySchedule $schedule): array
    {
        $presetId = $this->recipientPresetIdFromSchedule($schedule);
        $preset = $presetId ? $this->reportRecipientPresetService->find($workspaceId, $presetId) : null;
        $recipientGroup = $this->reportRecipientPresetService->resolveRecipientGroup(
            workspaceId: $workspaceId,
            preset: $preset,
            manualRecipients: $schedule->recipients ?? [],
            manualContactTags: $this->contactTagsFromSchedule($schedule),
        );

        return [
            'recipient_preset_id' => $preset['id'] ?? null,
            'recipient_preset_name' => $preset['name'] ?? null,
            'contact_tags' => $recipientGroup['contact_tags'],
            'tagged_contacts' => $recipientGroup['tagged_contacts'],
            'resolved_recipients' => $recipientGroup['resolved_recipients'],
            'recipient_group_summary' => $recipientGroup['recipient_group_summary'],
        ];
    }

    private function recipientPresetIdFromSchedule(ReportDeliverySchedule $schedule): ?string
    {
        $configuration = is_array($schedule->configuration ?? null) ? $schedule->configuration : [];
        $presetId = data_get($configuration, 'recipient_preset_id');

        return is_string($presetId) && $presetId !== '' ? $presetId : null;
    }

    /**
     * @return array<int, string>
     */
    private function contactTagsFromSchedule(ReportDeliverySchedule $schedule): array
    {
        $configuration = is_array($schedule->configuration ?? null) ? $schedule->configuration : [];

        return $this->reportContactService->normalizeTags(
            is_array($configuration['contact_tags'] ?? null) ? $configuration['contact_tags'] : [],
        );
    }

    /**
     * @param  array<int, mixed>  $recipients
     * @return array<int, string>
     */
    private function normalizeRecipients(array $recipients): array
    {
        return collect($recipients)
            ->map(fn (mixed $recipient): string => mb_strtolower(trim((string) $recipient)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
