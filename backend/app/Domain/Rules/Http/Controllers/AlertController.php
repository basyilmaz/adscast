<?php

namespace App\Domain\Rules\Http\Controllers;

use App\Domain\Rules\Services\RulesEngineService;
use App\Domain\Rules\Services\AlertQueryService;
use App\Domain\Tenants\Support\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController
{
    public function __construct(
        private readonly RulesEngineService $rulesEngineService,
        private readonly AlertQueryService $alertQueryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        return new JsonResponse($this->alertQueryService->index($workspaceId, $request));
    }

    public function evaluate(Request $request): JsonResponse
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

        $alerts = $this->rulesEngineService->evaluateWorkspace($workspaceId, $startDate, $endDate);

        return new JsonResponse([
            'message' => 'Rules engine degerlendirmesi tamamlandi.',
            'count' => count($alerts),
            'data' => $alerts,
        ]);
    }
}
