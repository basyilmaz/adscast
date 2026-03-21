<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Services\CampaignQueryService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\Campaign;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController
{
    public function __construct(
        private readonly CampaignQueryService $campaignQueryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'max:80'],
            'objective' => ['nullable', 'string', 'max:120'],
            'ad_account_id' => ['nullable', 'uuid'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now()->subDays(6);
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();

        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        return new JsonResponse([
            'data' => $this->campaignQueryService->list(
                workspaceId: $workspaceId,
                startDate: $startDate,
                endDate: $endDate,
                adAccountId: $validated['ad_account_id'] ?? null,
                objective: $validated['objective'] ?? null,
                status: $validated['status'] ?? null,
            ),
        ]);
    }

    public function show(Request $request, string $campaignId): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now()->subDays(13);
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();

        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $campaign = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($campaignId);

        return new JsonResponse([
            'data' => $this->campaignQueryService->detail($campaign, $startDate, $endDate),
        ]);
    }
}
