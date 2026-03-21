<?php

namespace App\Domain\Rules\Services;

use App\Models\Alert;
use App\Support\Operations\ActionFeedService;
use Illuminate\Http\Request;

class AlertQueryService
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
        $query = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->latest('date_detected');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type')->toString());
        }

        $paginator = $query->paginate(25);
        $alerts = $paginator->getCollection();
        $payload = $this->actionFeedService->presentAlerts($workspaceId, $alerts);
        $paginator->setCollection(collect($payload['items']));

        return [
            'data' => $paginator,
            'summary' => $payload['summary'],
            'entity_groups' => $payload['entity_groups'],
            'next_best_actions' => $payload['next_best_actions'],
        ];
    }
}
