<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Http\Requests\StoreReportSnapshotRequest;
use App\Domain\Reporting\Http\Requests\StoreReportContactRequest;
use App\Domain\Reporting\Http\Requests\StoreReportDeliverySetupRequest;
use App\Domain\Reporting\Http\Requests\StoreReportRecipientPresetRequest;
use App\Domain\Reporting\Http\Requests\StoreReportDeliveryScheduleRequest;
use App\Domain\Reporting\Http\Requests\ResolveReportRecipientGroupSuggestionsRequest;
use App\Domain\Reporting\Http\Requests\StoreReportShareLinkRequest;
use App\Domain\Reporting\Http\Requests\StoreReportTemplateRequest;
use App\Domain\Reporting\Http\Requests\ToggleReportDeliveryProfileRequest;
use App\Domain\Reporting\Http\Requests\UpsertReportDeliveryProfileRequest;
use App\Domain\Reporting\Services\ReportBuilderService;
use App\Domain\Reporting\Services\ReportContactService;
use App\Domain\Reporting\Services\ReportRecipientGroupAdvisorService;
use App\Domain\Reporting\Services\ReportRecipientPresetService;
use App\Domain\Reporting\Services\ReportDeliveryScheduleService;
use App\Domain\Reporting\Services\ReportDeliverySetupService;
use App\Domain\Reporting\Services\ReportDeliveryProfileService;
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
        private readonly ReportContactService $reportContactService,
        private readonly ReportRecipientGroupAdvisorService $reportRecipientGroupAdvisorService,
        private readonly ReportRecipientPresetService $reportRecipientPresetService,
        private readonly ReportDeliveryProfileService $reportDeliveryProfileService,
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
        $contactIndex = $this->reportContactService->index($workspaceId);
        $recipientGroupCatalog = $this->reportRecipientGroupAdvisorService->catalog($workspaceId);
        $presetIndex = $this->reportRecipientPresetService->index($workspaceId);
        $profileIndex = $this->reportDeliveryProfileService->index($workspaceId);
        $deliveryIndex = $this->reportDeliveryScheduleService->index($workspaceId);
        $shareSummary = $this->reportShareLinkService->summary($workspaceId);

        return new JsonResponse([
            'data' => array_merge(
                $snapshotIndex,
                [
                    'template_summary' => $templateIndex['summary'],
                    'templates' => $templateIndex['items'],
                    'contact_summary' => $contactIndex['summary'],
                    'contact_segment_summary' => $contactIndex['segment_summary'],
                    'contacts' => $contactIndex['items'],
                    'contact_segments' => $contactIndex['segments'],
                    'recipient_group_catalog_summary' => $recipientGroupCatalog['summary'],
                    'recipient_group_catalog' => $recipientGroupCatalog['items'],
                    'recipient_preset_summary' => $presetIndex['summary'],
                    'recipient_presets' => $presetIndex['items'],
                    'delivery_profile_summary' => $profileIndex['summary'],
                    'delivery_profiles' => $profileIndex['items'],
                    'delivery_summary' => $deliveryIndex['summary'],
                    'delivery_capabilities' => $deliveryIndex['delivery_capabilities'],
                    'delivery_run_summary' => $deliveryIndex['run_summary'],
                    'delivery_runs' => $deliveryIndex['delivery_runs'],
                    'delivery_schedules' => $deliveryIndex['items'],
                    'share_summary' => $shareSummary,
                ],
            ),
        ]);
    }

    public function recipientGroupSuggestions(ResolveReportRecipientGroupSuggestionsRequest $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        $entityType = $request->string('entity_type')->toString();
        $entityId = $request->string('entity_id')->toString();
        $limit = (int) ($request->integer('limit') ?: 4);
        $profile = $this->reportDeliveryProfileService->findByEntity($workspaceId, $entityType, $entityId);
        $suggestedGroups = $this->reportRecipientGroupAdvisorService->suggestForEntity(
            workspaceId: $workspaceId,
            entityType: $entityType,
            entityId: $entityId,
            currentProfile: $profile,
            limit: $limit,
        );

        return new JsonResponse([
            'data' => [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'summary' => [
                    'total_suggestions' => count($suggestedGroups),
                    'top_source_type' => $suggestedGroups[0]['source_type'] ?? null,
                    'top_source_subtype' => $suggestedGroups[0]['source_subtype'] ?? null,
                ],
                'suggested_groups' => $suggestedGroups,
            ],
        ]);
    }

    public function storeRecipientPreset(StoreReportRecipientPresetRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $preset = $this->reportRecipientPresetService->store(
            workspace: $workspace,
            payload: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Kayitli alici listesi olusturuldu.',
            'data' => $preset,
        ], 201);
    }

    public function storeContact(StoreReportContactRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $contact = $this->reportContactService->store(
            workspace: $workspace,
            payload: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Kisi havuzu kaydi olusturuldu.',
            'data' => $contact,
        ], 201);
    }

    public function updateContact(StoreReportContactRequest $request, string $contactId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $contact = $this->reportContactService->update(
            workspace: $workspace,
            contactId: $contactId,
            payload: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Kisi havuzu kaydi guncellendi.',
            'data' => $contact,
        ]);
    }

    public function toggleContact(Request $request, string $contactId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $contact = $this->reportContactService->toggle(
            workspace: $workspace,
            contactId: $contactId,
            isActive: $validated['is_active'] ?? null,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Kisi havuzu durumu guncellendi.',
            'data' => $contact,
        ]);
    }

    public function deleteContact(Request $request, string $contactId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $this->reportContactService->delete(
            workspace: $workspace,
            contactId: $contactId,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Kisi havuzu kaydi silindi.',
        ]);
    }

    public function updateRecipientPreset(StoreReportRecipientPresetRequest $request, string $presetId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $preset = $this->reportRecipientPresetService->update(
            workspace: $workspace,
            presetId: $presetId,
            payload: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Kayitli alici listesi guncellendi.',
            'data' => $preset,
        ]);
    }

    public function toggleRecipientPreset(Request $request, string $presetId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        $preset = $this->reportRecipientPresetService->toggle(
            workspace: $workspace,
            presetId: $presetId,
            isActive: $validated['is_active'] ?? null,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Kayitli alici listesi durumu guncellendi.',
            'data' => $preset,
        ]);
    }

    public function deleteRecipientPreset(Request $request, string $presetId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $this->reportRecipientPresetService->delete(
            workspace: $workspace,
            presetId: $presetId,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Kayitli alici listesi silindi.',
        ]);
    }

    public function upsertDeliveryProfile(
        UpsertReportDeliveryProfileRequest $request,
        string $entityType,
        string $entityId,
    ): JsonResponse {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $profile = $this->reportDeliveryProfileService->upsert(
            workspace: $workspace,
            payload: array_merge($request->validated(), [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Varsayilan teslim profili kaydedildi.',
            'data' => $profile,
        ]);
    }

    public function toggleDeliveryProfile(
        ToggleReportDeliveryProfileRequest $request,
        string $entityType,
        string $entityId,
    ): JsonResponse {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $profile = $this->reportDeliveryProfileService->toggleByEntity(
            workspace: $workspace,
            entityType: $entityType,
            entityId: $entityId,
            isActive: $request->validated('is_active'),
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Varsayilan teslim profili durumu guncellendi.',
            'data' => $profile,
        ]);
    }

    public function deleteDeliveryProfile(Request $request, string $entityType, string $entityId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $this->reportDeliveryProfileService->deleteByEntity(
            workspace: $workspace,
            entityType: $entityType,
            entityId: $entityId,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Varsayilan teslim profili silindi.',
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

    public function retryDeliveryRun(Request $request, string $runId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        $result = $this->reportDeliveryScheduleService->retryRun(
            workspace: $workspace,
            runId: $runId,
            actor: $request->user(),
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Basarisiz teslim yeniden denendi.',
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
