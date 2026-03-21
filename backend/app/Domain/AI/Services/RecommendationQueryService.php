<?php

namespace App\Domain\AI\Services;

use App\Models\Recommendation;
use App\Support\Operations\ActionFeedService;
use Illuminate\Http\Request;

class RecommendationQueryService
{
    public function __construct(
        private readonly ActionFeedService $actionFeedService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(string $workspaceId, Request $request): array
    {
        $query = Recommendation::query()
            ->where('workspace_id', $workspaceId)
            ->latest('generated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority')->toString());
        }

        if ($request->filled('source')) {
            $query->where('source', $request->string('source')->toString());
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->string('target_type')->toString());
        }

        $paginator = $query->paginate(25);
        $recommendations = $paginator->getCollection();
        $payload = $this->actionFeedService->presentRecommendations($workspaceId, $recommendations);
        $paginator->setCollection(collect($payload['items']));

        return [
            'data' => $paginator,
            'summary' => $payload['summary'],
            'entity_groups' => $payload['entity_groups'],
            'next_best_actions' => $payload['next_best_actions'],
        ];
    }
}
