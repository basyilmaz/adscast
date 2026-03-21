<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\ReportShareLink;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportShareLinkService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ReportSnapshotService $reportSnapshotService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(string $workspaceId): array
    {
        $query = ReportShareLink::query()->where('workspace_id', $workspaceId);

        return [
            'total_links' => (clone $query)->count(),
            'active_links' => (clone $query)
                ->whereNull('revoked_at')
                ->where(function ($builder): void {
                    $builder->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->count(),
            'expiring_soon' => (clone $query)
                ->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(3)])
                ->count(),
        ];
    }

    /**
     * @param  array<int, string>  $snapshotIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupedForSnapshots(string $workspaceId, array $snapshotIds): array
    {
        if ($snapshotIds === []) {
            return [];
        }

        return ReportShareLink::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('report_snapshot_id', $snapshotIds)
            ->latest()
            ->get([
                'id',
                'workspace_id',
                'report_snapshot_id',
                'label',
                'token_encrypted',
                'allow_csv_download',
                'expires_at',
                'revoked_at',
                'last_accessed_at',
                'access_count',
                'created_at',
            ])
            ->groupBy('report_snapshot_id')
            ->map(fn (Collection $links): array => $links->take(5)
                ->map(fn (ReportShareLink $link): array => $this->internalItem($link))
                ->values()
                ->all())
            ->all();
    }

    public function create(
        Workspace $workspace,
        string $snapshotId,
        array $payload,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $snapshot = ReportSnapshot::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($snapshotId);

        $rawToken = Str::random(48);
        $shareLink = ReportShareLink::query()->create([
            'workspace_id' => $workspace->id,
            'report_snapshot_id' => $snapshot->id,
            'label' => $payload['label'] ?? $snapshot->title,
            'token_hash' => hash('sha256', $rawToken),
            'token_encrypted' => Crypt::encryptString($rawToken),
            'allow_csv_download' => (bool) ($payload['allow_csv_download'] ?? false),
            'expires_at' => isset($payload['expires_in_days'])
                ? now()->addDays((int) $payload['expires_in_days'])
                : now()->addDays((int) config('services.reports.share.default_expiry_days', 7)),
            'created_by' => $actor?->id,
            'metadata' => [
                'created_from' => 'snapshot_detail',
            ],
        ]);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_share_link_created',
            targetType: 'report_share_link',
            targetId: $shareLink->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'snapshot_id' => $snapshot->id,
                'allow_csv_download' => $shareLink->allow_csv_download,
                'expires_at' => $shareLink->expires_at?->toDateTimeString(),
            ],
            request: $request,
        );

        return $this->internalItem($shareLink);
    }

    public function revoke(
        Workspace $workspace,
        string $shareLinkId,
        ?User $actor = null,
        ?Request $request = null,
    ): ReportShareLink {
        $shareLink = ReportShareLink::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($shareLinkId);

        $shareLink->revoked_at = now();
        $shareLink->save();

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_share_link_revoked',
            targetType: 'report_share_link',
            targetId: $shareLink->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'snapshot_id' => $shareLink->report_snapshot_id,
            ],
            request: $request,
        );

        return $shareLink;
    }

    /**
     * @return array{share_link: array<string, mixed>, report: array<string, mixed>}
     */
    public function resolvePublicPayload(string $rawToken): array
    {
        $shareLink = $this->resolveAccessibleShareLink($rawToken);

        $payload = $this->reportSnapshotService->snapshotDetail($shareLink->snapshot);
        $payload['report']['operator_summary'] = 'Paylasim gorunumu icin operator notlari gizlendi.';
        $payload['next_best_actions'] = [];
        $payload['snapshot_history'] = [];
        $payload['recommendations'] = collect($payload['recommendations'] ?? [])
            ->map(function (array $recommendation): array {
                $recommendation['details'] = $recommendation['client_view']['summary'] ?? $recommendation['details'] ?? null;
                $recommendation['operator_view'] = [
                    'summary' => null,
                    'budget_note' => null,
                    'creative_note' => null,
                    'targeting_note' => null,
                    'landing_page_note' => null,
                    'next_test' => null,
                ];
                $recommendation['action_status'] = [
                    'code' => 'share_view',
                    'label' => 'Paylasim gorunumu',
                    'manual_review_required' => false,
                ];

                return $recommendation;
            })
            ->values()
            ->all();

        $payload['share_link'] = [
            'id' => $shareLink->id,
            'label' => $shareLink->label,
            'expires_at' => $shareLink->expires_at?->toDateTimeString(),
            'allow_csv_download' => $shareLink->allow_csv_download,
            'access_count' => $shareLink->access_count,
            'export_csv_url' => $shareLink->allow_csv_download
                ? sprintf('/api/v1/public/report-shares/%s/export.csv', urlencode($rawToken))
                : null,
        ];
        $payload['snapshot']['export_csv_url'] = $payload['share_link']['export_csv_url'];
        $payload['export_options']['live_csv_url'] = $payload['share_link']['export_csv_url'];

        return [
            'share_link' => $this->internalItem($shareLink),
            'report' => $payload,
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function resolvePublicExportRows(string $rawToken): array
    {
        $shareLink = $this->resolveAccessibleShareLink($rawToken);

        if (! $shareLink->allow_csv_download) {
            abort(403, 'Bu paylasim linki icin CSV indirme kapali.');
        }

        return data_get($shareLink->snapshot?->payload, 'export_rows', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function internalItem(ReportShareLink $shareLink): array
    {
        $rawToken = $shareLink->decryptToken();

        return [
            'id' => $shareLink->id,
            'label' => $shareLink->label,
            'status' => $shareLink->revoked_at !== null
                ? 'revoked'
                : (($shareLink->expires_at !== null && $shareLink->expires_at->isPast()) ? 'expired' : 'active'),
            'allow_csv_download' => $shareLink->allow_csv_download,
            'expires_at' => $shareLink->expires_at?->toDateTimeString(),
            'revoked_at' => $shareLink->revoked_at?->toDateTimeString(),
            'last_accessed_at' => $shareLink->last_accessed_at?->toDateTimeString(),
            'access_count' => $shareLink->access_count,
            'created_at' => $shareLink->created_at?->toDateTimeString(),
            'share_url' => $rawToken ? $this->shareUrl($rawToken) : null,
            'export_csv_url' => $rawToken && $shareLink->allow_csv_download
                ? sprintf('%s/api/v1/public/report-shares/%s/export.csv', $this->baseUrl(), urlencode($rawToken))
                : null,
        ];
    }

    private function shareUrl(string $rawToken): string
    {
        return sprintf('%s/shared-report?token=%s', $this->baseUrl(), urlencode($rawToken));
    }

    private function resolveAccessibleShareLink(string $rawToken): ReportShareLink
    {
        $shareLink = ReportShareLink::query()
            ->with('snapshot')
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();

        if (! $shareLink || ! $shareLink->snapshot) {
            throw new NotFoundHttpException('Paylasim linki bulunamadi.');
        }

        if ($shareLink->revoked_at !== null) {
            throw new GoneHttpException('Paylasim linki iptal edildi.');
        }

        if ($shareLink->expires_at !== null && $shareLink->expires_at->isPast()) {
            throw new GoneHttpException('Paylasim linkinin suresi doldu.');
        }

        $shareLink->forceFill([
            'last_accessed_at' => now(),
            'access_count' => $shareLink->access_count + 1,
        ])->save();

        return $shareLink;
    }

    private function baseUrl(): string
    {
        return rtrim((string) env('FRONTEND_ORIGIN', config('app.url')), '/');
    }
}
