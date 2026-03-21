<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Http\Requests\StoreReportSnapshotRequest;
use App\Domain\Reporting\Http\Requests\StoreReportDeliverySetupRequest;
use App\Domain\Reporting\Http\Requests\StoreReportDeliveryScheduleRequest;
use App\Domain\Reporting\Http\Requests\StoreReportShareLinkRequest;
use App\Domain\Reporting\Http\Requests\StoreReportTemplateRequest;
use App\Domain\Reporting\Services\ReportBuilderService;
use App\Domain\Reporting\Services\ReportDeliveryScheduleService;
use App\Domain\Reporting\Services\ReportDeliverySetupService;
use App\Domain\Reporting\Services\ReportShareLinkService;
use App\Domain\Reporting\Services\ReportSnapshotService;
use App\Domain\Reporting\Services\ReportTemplateService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\ReportSnapshot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController
{
    public function __construct(
        private readonly ReportBuilderService $reportBuilderService,
        private readonly ReportSnapshotService $reportSnapshotService,
        private readonly ReportTemplateService $reportTemplateService,
        private readonly ReportDeliveryScheduleService $reportDeliveryScheduleService,
        private readonly ReportDeliverySetupService $reportDeliverySetupService,
        private readonly ReportShareLinkService $reportShareLinkService,
    ) {
    }

    public function index(): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        $snapshotIndex = $this->reportSnapshotService->index($workspaceId);
        $templateIndex = $this->reportTemplateService->index($workspaceId);
        $deliveryIndex = $this->reportDeliveryScheduleService->index($workspaceId);
        $shareSummary = $this->reportShareLinkService->summary($workspaceId);

        return new JsonResponse([
            'data' => array_merge(
                $snapshotIndex,
                [
                    'template_summary' => $templateIndex['summary'],
                    'templates' => $templateIndex['items'],
                    'delivery_summary' => $deliveryIndex['summary'],
                    'delivery_capabilities' => $deliveryIndex['delivery_capabilities'],
                    'delivery_schedules' => $deliveryIndex['items'],
                    'share_summary' => $shareSummary,
                ],
            ),
        ]);
    }

    public function account(Request $request, string $adAccountId): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveRange($request, 29);
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $account = MetaAdAccount::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($adAccountId);

        $payload = $this->reportBuilderService->buildAccountReport($account, $startDate, $endDate);
        unset($payload['export_rows']);

        return new JsonResponse([
            'data' => $payload,
        ]);
    }

    public function campaign(Request $request, string $campaignId): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveRange($request, 29);
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $campaign = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($campaignId);

        $payload = $this->reportBuilderService->buildCampaignReport($campaign, $startDate, $endDate);
        unset($payload['export_rows']);

        return new JsonResponse([
            'data' => $payload,
        ]);
    }

    public function storeSnapshot(StoreReportSnapshotRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        [$startDate, $endDate] = $this->resolveRange($request, 29);

        $snapshot = $this->reportSnapshotService->storeSnapshot(
            workspace: $workspace,
            entityType: $request->string('entity_type')->toString(),
            entityId: $request->string('entity_id')->toString(),
            startDate: $startDate,
            endDate: $endDate,
            generatedBy: $request->user()?->id,
            reportType: $request->string('report_type')->toString() ?: null,
        );

        return new JsonResponse([
            'message' => 'Rapor snapshot kaydedildi.',
            'data' => [
                'id' => $snapshot->id,
                'title' => $snapshot->title,
                'export_csv_url' => sprintf('/api/v1/reports/snapshots/%s/export.csv', $snapshot->id),
            ],
        ], 201);
    }

    public function storeTemplate(StoreReportTemplateRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $template = $this->reportTemplateService->store(
            workspace: $workspace,
            payload: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Rapor sablonu kaydedildi.',
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
            ],
        ], 201);
    }

    public function storeDeliverySchedule(StoreReportDeliveryScheduleRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $schedule = $this->reportDeliveryScheduleService->store(
            workspace: $workspace,
            payload: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Rapor teslim schedule kaydi olusturuldu.',
            'data' => [
                'id' => $schedule->id,
                'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
            ],
        ], 201);
    }

    public function storeDeliverySetup(StoreReportDeliverySetupRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $result = $this->reportDeliverySetupService->store(
            workspace: $workspace,
            payload: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Rapor teslim plani olusturuldu.',
            'data' => $result,
        ], 201);
    }

    public function toggleDeliverySchedule(Request $request, string $scheduleId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $schedule = $this->reportDeliveryScheduleService->toggle(
            workspace: $workspace,
            scheduleId: $scheduleId,
            isActive: $validated['is_active'] ?? null,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Rapor teslim schedule durumu guncellendi.',
            'data' => [
                'id' => $schedule->id,
                'is_active' => $schedule->is_active,
                'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
            ],
        ]);
    }

    public function runDeliveryScheduleNow(Request $request, string $scheduleId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $result = $this->reportDeliveryScheduleService->runNow(
            workspace: $workspace,
            scheduleId: $scheduleId,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Rapor teslim run kaydi hazirlandi.',
            'data' => $result,
        ]);
    }

    public function showSnapshot(string $snapshotId): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $snapshot = ReportSnapshot::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($snapshotId);

        $detail = $this->reportSnapshotService->snapshotDetail($snapshot);
        $shareLinks = $this->reportShareLinkService->groupedForSnapshots($workspaceId, [$snapshot->id]);
        $detail['snapshot']['share_links'] = $shareLinks[$snapshot->id] ?? [];

        return new JsonResponse([
            'data' => $detail,
        ]);
    }

    public function storeShareLink(StoreReportShareLinkRequest $request, string $snapshotId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $shareLink = $this->reportShareLinkService->create(
            workspace: $workspace,
            snapshotId: $snapshotId,
            payload: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Paylasim linki olusturuldu.',
            'data' => $shareLink,
        ], 201);
    }

    public function revokeShareLink(Request $request, string $shareLinkId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $shareLink = $this->reportShareLinkService->revoke(
            workspace: $workspace,
            shareLinkId: $shareLinkId,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Paylasim linki iptal edildi.',
            'data' => [
                'id' => $shareLink->id,
                'revoked_at' => $shareLink->revoked_at?->toDateTimeString(),
            ],
        ]);
    }

    public function exportAccountCsv(Request $request, string $adAccountId): StreamedResponse
    {
        [$startDate, $endDate] = $this->resolveRange($request, 29);
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $account = MetaAdAccount::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($adAccountId);

        $payload = $this->reportBuilderService->buildAccountReport($account, $startDate, $endDate);

        return $this->streamCsv(
            $payload['export_rows'],
            sprintf('account-report-%s-%s.csv', $startDate->toDateString(), $endDate->toDateString()),
        );
    }

    public function exportCampaignCsv(Request $request, string $campaignId): StreamedResponse
    {
        [$startDate, $endDate] = $this->resolveRange($request, 29);
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $campaign = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($campaignId);

        $payload = $this->reportBuilderService->buildCampaignReport($campaign, $startDate, $endDate);

        return $this->streamCsv(
            $payload['export_rows'],
            sprintf('campaign-report-%s-%s.csv', $startDate->toDateString(), $endDate->toDateString()),
        );
    }

    public function exportSnapshotCsv(string $snapshotId): StreamedResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $snapshot = ReportSnapshot::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($snapshotId);

        return $this->streamCsv(
            data_get($snapshot->payload, 'export_rows', []),
            sprintf('report-snapshot-%s.csv', $snapshot->id),
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request, int $fallbackDays): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now()->subDays($fallbackDays);
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();

        return [$startDate, $endDate];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function streamCsv(array $rows, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
