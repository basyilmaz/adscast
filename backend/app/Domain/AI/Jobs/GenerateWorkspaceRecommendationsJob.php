<?php

namespace App\Domain\AI\Jobs;

use App\Domain\AI\Services\RecommendationGenerationService;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWorkspaceRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $workspaceId,
        public readonly ?string $generatedBy = null,
    ) {
    }

    public function handle(RecommendationGenerationService $service): void
    {
        $workspace = Workspace::query()->findOrFail($this->workspaceId);
        $service->generateForWorkspace($workspace, $this->generatedBy);
    }
}
