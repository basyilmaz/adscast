<?php

namespace App\Domain\Reporting\Services;

use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\ReportSnapshot;
use App\Models\Workspace;
use App\Support\Operations\EntityContextResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportSnapshotService
{
    public function __construct(
        private readonly ReportBuilderService $reportBuilderService,
        private readonly EntityContextResolver $entityContextResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(string $workspaceId): array
    {
        $snapshots = ReportSnapshot::query()
            ->where('workspace_id', $workspaceId)
            ->latest()
            ->limit(25)
            ->get([
                'id',
                'entity_type',
                'entity_id',
                'report_type',
                'title',
                'start_date',
                'end_date',
                'generated_by',
                'created_at',
            ]);

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $snapshots->map(fn (ReportSnapshot $snapshot): array => [
                'type' => $snapshot->entity_type,
                'id' => $snapshot->entity_id,
            ])->all(),
        );

        return [
            'summary' => [
                'total_snapshots' => $snapshots->count(),
                'account_snapshots' => $snapshots->where('entity_type', 'account')->count(),
                'campaign_snapshots' => $snapshots->where('entity_type', 'campaign')->count(),
            ],
            'items' => $snapshots->map(function (ReportSnapshot $snapshot) use ($contexts): array {
                $context = $contexts[$this->entityContextResolver->key($snapshot->entity_type, $snapshot->entity_id)] ?? [
                    'entity_label' => 'Bilinmeyen varlik',
                    'context_label' => null,
                ];

                return [
                    'id' => $snapshot->id,
                    'title' => $snapshot->title,
                    'entity_type' => $snapshot->entity_type,
                    'entity_id' => $snapshot->entity_id,
                    'entity_label' => $context['entity_label'],
                    'context_label' => $context['context_label'],
                    'report_type' => $snapshot->report_type,
                    'start_date' => $snapshot->start_date?->toDateString(),
                    'end_date' => $snapshot->end_date?->toDateString(),
                    'created_at' => $snapshot->created_at?->toDateTimeString(),
                    'report_url' => $this->reportUrl($snapshot->entity_type, $snapshot->entity_id),
                    'snapshot_url' => sprintf('/reports/snapshots/detail?id=%s', $snapshot->id),
                    'export_csv_url' => sprintf('/api/v1/reports/snapshots/%s/export.csv', $snapshot->id),
                ];
            })->values()->all(),
            'builders' => [
                'accounts' => $this->accountBuilderOptions($workspaceId),
                'campaigns' => $this->campaignBuilderOptions($workspaceId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotDetail(ReportSnapshot $snapshot): array
    {
        $payload = $snapshot->payload;
        unset($payload['export_rows']);

        $payload['snapshot'] = [
            'id' => $snapshot->id,
            'report_type' => $snapshot->report_type,
            'created_at' => $snapshot->created_at?->toDateTimeString(),
            'export_csv_url' => sprintf('/api/v1/reports/snapshots/%s/export.csv', $snapshot->id),
        ];

        return $payload;
    }

    public function storeSnapshot(
        Workspace $workspace,
        string $entityType,
        string $entityId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?string $generatedBy = null,
        ?string $reportType = null,
    ): ReportSnapshot {
        $payload = match ($entityType) {
            'account' => $this->reportBuilderService->buildAccountReport(
                MetaAdAccount::query()->where('workspace_id', $workspace->id)->findOrFail($entityId),
                $startDate,
                $endDate,
            ),
            'campaign' => $this->reportBuilderService->buildCampaignReport(
                Campaign::query()->where('workspace_id', $workspace->id)->findOrFail($entityId),
                $startDate,
                $endDate,
            ),
            default => throw new \InvalidArgumentException('Desteklenmeyen rapor entity tipi.'),
        };

        return ReportSnapshot::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'report_type' => $reportType ?? (string) $payload['report']['type'],
            'title' => (string) $payload['report']['title'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'payload' => $payload,
            'generated_by' => $generatedBy,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accountBuilderOptions(string $workspaceId): array
    {
        return MetaAdAccount::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'account_id', 'name', 'status', 'last_synced_at'])
            ->map(fn (MetaAdAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'external_id' => $account->account_id,
                'status' => $account->status,
                'route' => sprintf('/reports/account?id=%s', $account->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function campaignBuilderOptions(string $workspaceId): array
    {
        return Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->with('adAccount:id,name')
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get(['id', 'meta_ad_account_id', 'name', 'objective', 'status'])
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'objective' => $campaign->objective,
                'status' => $campaign->status,
                'context_label' => $campaign->adAccount?->name,
                'route' => sprintf('/reports/campaign?id=%s', $campaign->id),
            ])
            ->values()
            ->all();
    }

    private function reportUrl(?string $entityType, ?string $entityId): ?string
    {
        return match ($entityType) {
            'account' => $entityId ? sprintf('/reports/account?id=%s', $entityId) : null,
            'campaign' => $entityId ? sprintf('/reports/campaign?id=%s', $entityId) : null,
            default => null,
        };
    }
}
