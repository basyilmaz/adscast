<?php

namespace App\Domain\Approvals\Http\Controllers;

use App\Domain\Approvals\Http\Requests\CompleteApprovalManualCheckRequest;
use App\Domain\Approvals\Services\ApprovalPayloadPresenter;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Meta\Services\MetaAdapterFactory;
use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\Approval;
use App\Models\CampaignDraft;
use App\Models\MetaConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ApprovalController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MetaAdapterFactory $adapterFactory,
        private readonly ApprovalPayloadPresenter $approvalPayloadPresenter,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $approvals = Approval::query()
            ->where('workspace_id', $workspaceId)
            ->with('approvable')
            ->latest()
            ->get()
            ->map(fn (Approval $approval): array => $this->approvalPayloadPresenter->present($approval))
            ->filter(fn (array $approval): bool => $this->matchesFilters($approval, $request))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 20;
        $paginator = new LengthAwarePaginator(
            $approvals->forPage($page, $perPage)->values(),
            $approvals->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return new JsonResponse([
            'data' => $paginator,
        ]);
    }

    public function approve(Request $request, string $approvalId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $workspaceId = $workspace->id;
        $user = $request->user();

        $approval = Approval::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($approvalId);

        $approval->forceFill([
            'status' => 'approved',
            'reviewed_by' => $user?->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();

        $this->syncApprovableStatus($approval, 'approved', $user?->id);

        $this->auditLogService->log(
            actor: $user,
            action: 'draft_approved',
            targetType: 'approval',
            targetId: $approval->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Onay islemi tamamlandi.',
            'data' => $approval->fresh(),
        ]);
    }

    public function reject(Request $request, string $approvalId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $workspaceId = $workspace->id;
        $user = $request->user();

        $approval = Approval::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($approvalId);

        $approval->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $user?->id,
            'rejected_at' => now(),
            'rejection_reason' => $validated['reason'],
        ])->save();

        $this->syncApprovableStatus($approval, 'rejected', $user?->id, $validated['reason']);

        $this->auditLogService->log(
            actor: $user,
            action: 'draft_rejected',
            targetType: 'approval',
            targetId: $approval->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'reason' => $validated['reason'],
            ],
        );

        return new JsonResponse([
            'message' => 'Onay reddedildi.',
            'data' => $approval->fresh(),
        ]);
    }

    public function publish(Request $request, string $approvalId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $workspaceId = $workspace->id;
        $user = $request->user();

        /** @var Approval $approval */
        $approval = Approval::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($approvalId);

        if ($approval->status !== 'approved') {
            return new JsonResponse([
                'message' => 'Publish icin once onay durumu approved olmali.',
                'error_code' => 'approval_not_ready',
            ], 422);
        }

        if ($approval->approvable_type !== CampaignDraft::class) {
            return new JsonResponse([
                'message' => 'Bu approvable turu MVP publish akisi disinda.',
                'error_code' => 'approvable_type_not_supported',
            ], 422);
        }

        /** @var CampaignDraft $draft */
        $draft = CampaignDraft::query()->findOrFail($approval->approvable_id);

        $connection = MetaConnection::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'meta')
            ->where('status', 'active')
            ->first();

        if (! $connection) {
            $approval->forceFill([
                'status' => 'publish_failed',
                'publish_response_metadata' => [
                    'message' => 'Aktif Meta baglantisi bulunamadi.',
                ],
            ])->save();

            $draft->forceFill([
                'status' => 'publish_failed',
            ])->save();

            return new JsonResponse([
                'message' => 'Publish basarisiz: aktif Meta baglantisi yok.',
                'data' => $approval->fresh(),
            ], 422);
        }

        $adapter = $this->adapterFactory->resolve($connection);
        $response = $adapter->publishCampaignDraft($connection, $draft);
        $isSuccess = (bool) ($response['success'] ?? false);

        $approval->forceFill([
            'status' => $isSuccess ? 'published' : 'publish_failed',
            'published_at' => $isSuccess ? now() : null,
            'publish_response_metadata' => $response,
        ])->save();

        $draft->forceFill([
            'status' => $isSuccess ? 'published' : 'publish_failed',
            'published_at' => $isSuccess ? now() : null,
            'publish_response_metadata' => $response,
        ])->save();

        $this->auditLogService->log(
            actor: $user,
            action: 'publish_attempted',
            targetType: 'approval',
            targetId: $approval->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'success' => $isSuccess,
                'response' => $response,
            ],
        );

        return new JsonResponse([
            'message' => $isSuccess ? 'Publish basarili.' : 'Publish basarisiz.',
            'data' => [
                'approval' => $approval->fresh(),
                'draft' => $draft->fresh(),
            ],
        ]);
    }

    public function completeManualCheck(CompleteApprovalManualCheckRequest $request, string $approvalId): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $workspaceId = $workspace->id;
        $user = $request->user();

        /** @var Approval $approval */
        $approval = Approval::query()
            ->where('workspace_id', $workspaceId)
            ->with('approvable')
            ->findOrFail($approvalId);

        $metadata = is_array($approval->publish_response_metadata) ? $approval->publish_response_metadata : [];
        $publishState = $this->approvalPayloadPresenter->present($approval)['publish_state'];

        if (! is_array($publishState) || ! ($publishState['manual_check_required'] ?? false)) {
            return new JsonResponse([
                'message' => 'Bu approval icin bekleyen manuel kontrol gereksinimi yok.',
                'error_code' => 'manual_check_not_required',
            ], 422);
        }

        data_set($metadata, 'manual_check.completed', true);
        data_set($metadata, 'manual_check.completed_at', now()->toIso8601String());
        data_set($metadata, 'manual_check.completed_by', $user?->id);
        data_set($metadata, 'manual_check.note', $request->validated('note'));

        $approval->forceFill([
            'publish_response_metadata' => $metadata,
        ])->save();

        if ($approval->approvable_type === CampaignDraft::class) {
            CampaignDraft::query()
                ->whereKey($approval->approvable_id)
                ->update([
                    'publish_response_metadata' => $metadata,
                ]);
        }

        $this->auditLogService->log(
            actor: $user,
            action: 'approval_manual_check_completed',
            targetType: 'approval',
            targetId: $approval->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'note' => $request->validated('note'),
                'meta_campaign_id' => data_get($metadata, 'meta_reference.campaign_id'),
            ],
        );

        return new JsonResponse([
            'message' => 'Manuel kontrol tamamlandi olarak isaretlendi.',
            'data' => $this->approvalPayloadPresenter->present($approval->fresh(['approvable'])),
        ]);
    }

    private function syncApprovableStatus(
        Approval $approval,
        string $status,
        ?string $reviewedBy = null,
        ?string $rejectionReason = null,
    ): void {
        if ($approval->approvable_type !== CampaignDraft::class) {
            return;
        }

        /** @var CampaignDraft|null $draft */
        $draft = CampaignDraft::query()->find($approval->approvable_id);

        if (! $draft) {
            return;
        }

        $draft->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'rejected_reason' => $rejectionReason,
        ])->save();
    }

    /**
     * @param array<string, mixed> $approval
     */
    private function matchesFilters(array $approval, Request $request): bool
    {
        $status = $request->string('status')->toString();
        if ($status !== '' && ($approval['status'] ?? null) !== $status) {
            return false;
        }

        /** @var array<string, mixed>|null $publishState */
        $publishState = $approval['publish_state'] ?? null;
        $cleanupState = $request->string('cleanup_state')->toString();
        if ($cleanupState !== '' && ! $this->matchesCleanupState($cleanupState, $publishState)) {
            return false;
        }

        $manualCheckState = $request->string('manual_check_state')->toString();
        if ($manualCheckState !== '' && ! $this->matchesManualCheckState($manualCheckState, $publishState)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $publishState
     */
    private function matchesCleanupState(string $cleanupState, ?array $publishState): bool
    {
        return match ($cleanupState) {
            'failed' => (bool) ($publishState['cleanup_attempted'] ?? false) && ($publishState['cleanup_success'] ?? null) === false,
            'successful' => (bool) ($publishState['cleanup_attempted'] ?? false) && ($publishState['cleanup_success'] ?? null) === true,
            'not_attempted' => ! ($publishState['cleanup_attempted'] ?? false),
            default => true,
        };
    }

    /**
     * @param array<string, mixed>|null $publishState
     */
    private function matchesManualCheckState(string $manualCheckState, ?array $publishState): bool
    {
        return match ($manualCheckState) {
            'required' => (bool) ($publishState['manual_check_required'] ?? false),
            'completed' => (bool) ($publishState['manual_check_completed'] ?? false),
            'not_required' => ! ($publishState['manual_check_required'] ?? false) && ! ($publishState['manual_check_completed'] ?? false),
            default => true,
        };
    }
}
