<?php

namespace App\Domain\Rules\Http\Controllers;

use App\Domain\Rules\Services\RulesEngineService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController
{
    public function __construct(
        private readonly RulesEngineService $rulesEngineService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $query = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->latest('date_detected');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        return new JsonResponse([
            'data' => $query->paginate(25),
        ]);
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
