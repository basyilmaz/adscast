<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Http\Requests\StoreReportSnapshotRequest;
use App\Domain\Reporting\Services\ReportBuilderService;
use App\Domain\Reporting\Services\ReportSnapshotService;
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
    ) {
    }

    public function index(): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        return new JsonResponse([
            'data' => $this->reportSnapshotService->index($workspaceId),
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

    public function showSnapshot(string $snapshotId): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $snapshot = ReportSnapshot::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($snapshotId);

        return new JsonResponse([
            'data' => $this->reportSnapshotService->snapshotDetail($snapshot),
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
