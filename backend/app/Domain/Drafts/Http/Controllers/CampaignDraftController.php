<?php

namespace App\Domain\Drafts\Http\Controllers;

use App\Domain\Approvals\Services\ApprovalPayloadPresenter;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Drafts\Http\Requests\StoreCampaignDraftRequest;
use App\Domain\Drafts\Services\CampaignDraftSuggestionService;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\Approval;
use App\Models\CampaignDraft;
use App\Models\CampaignDraftItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignDraftController
{
    public function __construct(
        private readonly CampaignDraftSuggestionService $suggestionService,
        private readonly AuditLogService $auditLogService,
        private readonly ApprovalPayloadPresenter $approvalPayloadPresenter,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $query = CampaignDraft::query()
            ->where('workspace_id', $workspaceId)
            ->with(['adAccount:id,name,account_id', 'creator:id,name', 'reviewer:id,name'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return new JsonResponse([
            'data' => $query->paginate(20),
        ]);
    }

    public function store(StoreCampaignDraftRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $user = $request->user();
        $validated = $request->validated();

        $draft = DB::transaction(function () use ($validated, $workspace, $user): CampaignDraft {
            $draft = CampaignDraft::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'meta_ad_account_id' => $validated['meta_ad_account_id'],
                'objective' => $validated['objective'],
                'product_service' => $validated['product_service'],
                'target_audience' => $validated['target_audience'],
                'location' => $validated['location'] ?? null,
                'budget_min' => $validated['budget_min'] ?? null,
                'budget_max' => $validated['budget_max'] ?? null,
                'offer' => $validated['offer'] ?? null,
                'landing_page_url' => $validated['landing_page_url'] ?? null,
                'tone_style' => $validated['tone_style'] ?? null,
                'existing_creative_availability' => $validated['existing_creative_availability'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $user?->id,
            ]);

            $items = $this->suggestionService->buildSuggestions($validated);

            foreach ($items as $item) {
                CampaignDraftItem::query()->create([
                    'id' => (string) Str::uuid(),
                    'campaign_draft_id' => $draft->id,
                    'item_type' => $item['item_type'],
                    'title' => $item['title'],
                    'content' => $item['content'],
                    'sort_order' => $item['sort_order'],
                ]);
            }

            Approval::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'approvable_type' => CampaignDraft::class,
                'approvable_id' => $draft->id,
                'status' => 'draft',
                'created_by' => $user?->id,
            ]);

            return $draft;
        });

        $this->auditLogService->log(
            actor: $user,
            action: 'draft_created',
            targetType: 'campaign_draft',
            targetId: $draft->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'objective' => $draft->objective,
            ],
        );

        return new JsonResponse([
            'message' => 'Campaign draft olusturuldu.',
            'data' => $draft->load(['items', 'approval']),
        ], 201);
    }

    public function show(Request $request, string $draftId): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $draft = CampaignDraft::query()
            ->where('workspace_id', $workspaceId)
            ->with(['items', 'approval', 'adAccount:id,name,account_id', 'creator:id,name', 'reviewer:id,name'])
            ->findOrFail($draftId);

        return new JsonResponse([
            'data' => [
                ...$draft->toArray(),
                'approval' => $draft->approval ? $this->approvalPayloadPresenter->present($draft->approval->loadMissing('approvable')) : null,
            ],
        ]);
    }

    public function submitForReview(Request $request, string $draftId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $workspaceId = $workspace->id;
        $user = $request->user();

        /** @var CampaignDraft $draft */
        $draft = CampaignDraft::query()
            ->where('workspace_id', $workspaceId)
            ->with('approval')
            ->findOrFail($draftId);

        $draft->forceFill([
            'status' => 'pending_review',
        ])->save();

        $draft->approval?->forceFill([
            'status' => 'pending_review',
            'submitted_at' => now(),
        ])->save();

        $this->auditLogService->log(
            actor: $user,
            action: 'draft_submitted_for_review',
            targetType: 'campaign_draft',
            targetId: $draft->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Draft inceleme kuyruğuna alindi.',
            'data' => $draft->fresh(['items', 'approval']),
        ]);
    }
}
