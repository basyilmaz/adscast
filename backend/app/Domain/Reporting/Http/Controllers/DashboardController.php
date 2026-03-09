<?php

namespace App\Domain\Reporting\Http\Controllers;

use App\Domain\Reporting\Services\DashboardQueryService;
use App\Domain\Tenants\Support\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    public function __construct(
        private readonly DashboardQueryService $dashboardQueryService,
    ) {
    }

    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now()->subDays(6);
        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();

        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $data = $this->dashboardQueryService->getOverview($workspaceId, $startDate, $endDate);

        return new JsonResponse([
            'data' => $data,
        ]);
    }
}
