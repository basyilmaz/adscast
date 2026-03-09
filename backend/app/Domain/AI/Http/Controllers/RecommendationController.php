<?php

namespace App\Domain\AI\Http\Controllers;

use App\Domain\AI\Services\RecommendationGenerationService;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\Recommendation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController
{
    public function __construct(
        private readonly RecommendationGenerationService $generationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $recommendations = Recommendation::query()
            ->where('workspace_id', $workspaceId)
            ->latest('generated_at')
            ->paginate(25);

        return new JsonResponse([
            'data' => $recommendations,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $user = $request->user();

        $result = $this->generationService->generateForWorkspace($workspace, $user?->id);

        $this->auditLogService->log(
            actor: $user,
            action: 'recommendation_generated',
            targetType: 'ai_generation',
            targetId: $result['generation']->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'recommendation_id' => $result['recommendation']->id,
            ],
        );

        return new JsonResponse([
            'message' => 'AI onerisi olusturuldu.',
            'data' => $result,
        ]);
    }
}
