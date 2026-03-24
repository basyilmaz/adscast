<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\ReportDeliveryRun;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportFailureResolutionActionService
{
    public function __construct(
        private readonly ReportDeliveryFailureReasonClassifier $reportDeliveryFailureReasonClassifier,
        private readonly ReportDeliveryRetryRecommendationService $reportDeliveryRetryRecommendationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function forEntity(string $workspaceId, string $entityType, string $entityId): array
    {
        $failedRuns = $this->entityRunQuery($workspaceId, $entityType, $entityId)
            ->where('status', 'failed')
            ->with(['schedule.template'])
            ->latest('prepared_at')
            ->get([
                'id',
                'workspace_id',
                'report_delivery_schedule_id',
                'delivery_channel',
                'status',
                'prepared_at',
                'metadata',
                'error_message',
            ]);

        $classifiedRuns = $this->classifiedRuns($failedRuns);
        $reasonCodes = $classifiedRuns
            ->pluck('failure_reason.code')
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->values();

        $topReason = $classifiedRuns
            ->pluck('failure_reason')
            ->groupBy('code')
            ->sortByDesc(fn (Collection $items): int => $items->count())
            ->map(fn (Collection $items): array => $items->first())
            ->first();

        $retryableRuns = $this->retryEligibleRuns($classifiedRuns);
        $items = collect();

        if ($retryableRuns->isNotEmpty()) {
            $items->push([
                'id' => 'retry_failed_runs',
                'code' => 'retry_failed_runs',
                'label' => 'Basarisiz teslimleri tekrar dene',
                'detail' => sprintf(
                    'Bu kayit icin retry politikasi uygun %d basarisiz teslim var.',
                    $retryableRuns->count(),
                ),
                'severity' => $retryableRuns->count() > 1 ? 'critical' : 'warning',
                'action_kind' => 'api',
                'button_label' => 'Toplu Retry Calistir',
                'is_available' => true,
                'route' => null,
                'target_tab' => 'reports',
                'metadata' => [
                    'retryable_runs' => $retryableRuns->count(),
                    'affected_reason_codes' => $retryableRuns
                        ->map(fn (ReportDeliveryRun $run): array => $this->reportDeliveryFailureReasonClassifier->classify(
                            $run->error_message,
                            is_array($run->metadata ?? null) ? $run->metadata : [],
                            $run->delivery_channel,
                        ))
                        ->pluck('code')
                        ->unique()
                        ->values()
                        ->all(),
                    'latest_failed_at' => $retryableRuns->max(
                        fn (ReportDeliveryRun $run): ?string => $run->prepared_at?->toDateTimeString(),
                    ),
                ],
            ]);
        }

        if ($this->hasPrimaryAction($classifiedRuns, 'focus_delivery_profile')) {
            $items->push([
                'id' => 'focus_delivery_profile',
                'code' => 'focus_delivery_profile',
                'label' => 'Teslim profilini duzelt',
                'detail' => 'Paylasim, export, kanal veya konfigurasyon kaynakli sorunlar icin teslim profilini gozden gecirin.',
                'severity' => 'warning',
                'action_kind' => 'focus_tab',
                'button_label' => 'Teslim Profiline Git',
                'is_available' => true,
                'route' => null,
                'target_tab' => 'overview',
                'metadata' => [
                    'affected_reason_codes' => $classifiedRuns
                        ->filter(fn (array $item): bool => ($item['recommendation']['primary_action_code'] ?? null) === 'focus_delivery_profile')
                        ->pluck('failure_reason.code')
                        ->filter(fn ($value): bool => is_string($value) && $value !== '')
                        ->unique()
                        ->values()
                        ->all(),
                ],
            ]);
        }

        if ($this->hasPrimaryAction($classifiedRuns, 'review_contact_book')) {
            $items->push([
                'id' => 'review_contact_book',
                'code' => 'review_contact_book',
                'label' => 'Alici listesini temizle',
                'detail' => 'Reddedilen e-posta adreslerini contact book ve alici gruplarindan temizleyin.',
                'severity' => 'warning',
                'action_kind' => 'route',
                'button_label' => 'Rapor Merkezini Ac',
                'is_available' => true,
                'route' => '/reports',
                'target_tab' => null,
                'metadata' => [
                    'affected_reason_codes' => $classifiedRuns
                        ->filter(fn (array $item): bool => ($item['recommendation']['primary_action_code'] ?? null) === 'review_contact_book')
                        ->pluck('failure_reason.code')
                        ->filter(fn ($value): bool => is_string($value) && $value !== '')
                        ->unique()
                        ->values()
                        ->all(),
                ],
            ]);
        }

        return [
            'summary' => [
                'total_actions' => $items->count(),
                'retryable_runs' => $retryableRuns->count(),
                'reason_types' => $reasonCodes->unique()->count(),
                'top_reason_label' => is_array($topReason) ? ($topReason['label'] ?? null) : null,
            ],
            'items' => $items->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(
        Workspace $workspace,
        string $entityType,
        string $entityId,
        string $actionCode,
        ReportDeliveryScheduleService $reportDeliveryScheduleService,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        return match ($actionCode) {
            'retry_failed_runs' => $this->retryFailedRunsForEntity(
                workspace: $workspace,
                entityType: $entityType,
                entityId: $entityId,
                reportDeliveryScheduleService: $reportDeliveryScheduleService,
                actor: $actor,
                request: $request,
            ),
            default => throw ValidationException::withMessages([
                'action_code' => 'Desteklenmeyen failure resolution aksiyonu.',
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function retryFailedRunsForEntity(
        Workspace $workspace,
        string $entityType,
        string $entityId,
        ReportDeliveryScheduleService $reportDeliveryScheduleService,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $failedRuns = $this->entityRunQuery($workspace->id, $entityType, $entityId)
            ->where('status', 'failed')
            ->with(['schedule.template', 'schedule.workspace'])
            ->latest('prepared_at')
            ->get([
                'id',
                'workspace_id',
                'report_delivery_schedule_id',
                'delivery_channel',
                'status',
                'prepared_at',
                'metadata',
                'error_message',
            ])
            ->values();

        $retryableRuns = $this->retryEligibleRuns($this->classifiedRuns($failedRuns));

        if ($retryableRuns->isEmpty()) {
            throw ValidationException::withMessages([
                'action_code' => 'Tekrar denenebilecek basarisiz teslim kaydi bulunamadi.',
            ]);
        }

        $results = [];
        $errors = [];

        foreach ($retryableRuns as $run) {
            try {
                $retryResult = $reportDeliveryScheduleService->retryRun(
                    workspace: $workspace,
                    runId: $run->id,
                    actor: $actor,
                    request: $request,
                );

                $results[] = array_merge($retryResult, [
                    'source_run_id' => $run->id,
                ]);
            } catch (\Throwable $exception) {
                $errors[] = [
                    'source_run_id' => $run->id,
                    'message' => Str::limit($exception->getMessage(), 500),
                ];
            }
        }

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_failure_resolution_action_executed',
            targetType: $entityType,
            targetId: $entityId,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'action_code' => 'retry_failed_runs',
                'requested_runs' => $retryableRuns->count(),
                'retried_runs' => count($results),
                'failed_retries' => count($errors),
            ],
            request: $request,
        );

        return [
            'action_code' => 'retry_failed_runs',
            'requested_runs' => $retryableRuns->count(),
            'retried_runs' => count($results),
            'failed_retries' => count($errors),
            'results' => $results,
            'errors' => $errors,
        ];
    }

    private function canRetry(ReportDeliveryRun $run): bool
    {
        return $run->status === 'failed'
            && ! data_get($run->metadata, 'retry.next_run_id')
            && $run->report_delivery_schedule_id !== null
            && $run->schedule !== null
            && $run->schedule->template !== null;
    }

    /**
     * @param  Collection<int, ReportDeliveryRun>  $failedRuns
     * @return Collection<int, array{run: ReportDeliveryRun, failure_reason: array<string, mixed>, recommendation: array<string, mixed>}>
     */
    private function classifiedRuns(Collection $failedRuns): Collection
    {
        return $failedRuns->map(function (ReportDeliveryRun $run): array {
            $failureReason = $this->reportDeliveryFailureReasonClassifier->classify(
                $run->error_message,
                is_array($run->metadata ?? null) ? $run->metadata : [],
                $run->delivery_channel,
            );

            return [
                'run' => $run,
                'failure_reason' => $failureReason,
                'recommendation' => $this->reportDeliveryRetryRecommendationService->recommendationForFailureReason($failureReason),
            ];
        });
    }

    /**
     * @param  Collection<int, array{run: ReportDeliveryRun, failure_reason: array<string, mixed>, recommendation: array<string, mixed>}>  $classifiedRuns
     * @return Collection<int, ReportDeliveryRun>
     */
    private function retryEligibleRuns(Collection $classifiedRuns): Collection
    {
        return $classifiedRuns
            ->filter(function (array $item): bool {
                $retryPolicy = $item['recommendation']['retry_policy'] ?? null;
                $primaryActionCode = $item['recommendation']['primary_action_code'] ?? null;

                return $this->canRetry($item['run'])
                    && in_array($retryPolicy, ['auto_retry', 'manual_retry'], true)
                    && $primaryActionCode === 'retry_failed_runs';
            })
            ->pluck('run')
            ->values();
    }

    /**
     * @param  Collection<int, array{run: ReportDeliveryRun, failure_reason: array<string, mixed>, recommendation: array<string, mixed>}>  $classifiedRuns
     */
    private function hasPrimaryAction(Collection $classifiedRuns, string $actionCode): bool
    {
        return $classifiedRuns->contains(
            fn (array $item): bool => ($item['recommendation']['primary_action_code'] ?? null) === $actionCode,
        );
    }

    private function entityRunQuery(string $workspaceId, string $entityType, string $entityId)
    {
        return ReportDeliveryRun::query()
            ->where('workspace_id', $workspaceId)
            ->whereHas('schedule.template', function ($query) use ($entityType, $entityId): void {
                $query
                    ->where('entity_type', $entityType)
                    ->where('entity_id', $entityId);
            });
    }
}
