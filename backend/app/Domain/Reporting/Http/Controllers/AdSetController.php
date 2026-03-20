<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Services\CampaignQueryService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\AdSet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdSetController
{
    public function __construct(
        private readonly CampaignQueryService $campaignQueryService,
    ) {
    }

    public function show(Request $request, string $adSetId): JsonResponse
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

        $adSet = AdSet::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($adSetId);

        return new JsonResponse([
            'data' => $this->campaignQueryService->adSetDetail($adSet, $startDate, $endDate),
        ]);
    }
}
