<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Operations\EntityContextResolver;
use Illuminate\Http\Request;

class ReportTemplateService
{
    public function __construct(
        private readonly EntityContextResolver $entityContextResolver,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(string $workspaceId): array
    {
        $templates = ReportTemplate::query()
            ->where('workspace_id', $workspaceId)
            ->withCount('deliverySchedules')
            ->latest()
            ->limit(20)
            ->get([
                'id',
                'workspace_id',
                'name',
                'entity_type',
                'entity_id',
                'report_type',
                'default_range_days',
                'layout_preset',
                'notes',
                'is_active',
                'created_at',
            ]);

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $templates->map(fn (ReportTemplate $template): array => [
                'type' => $template->entity_type,
                'id' => $template->entity_id,
            ])->all(),
        );

        return [
            'summary' => [
                'total_templates' => $templates->count(),
                'active_templates' => $templates->where('is_active', true)->count(),
                'templates_with_schedules' => $templates->where('delivery_schedules_count', '>', 0)->count(),
            ],
            'items' => $templates->map(function (ReportTemplate $template) use ($contexts): array {
                $context = $contexts[$this->entityContextResolver->key($template->entity_type, $template->entity_id)] ?? [
                    'entity_label' => 'Bilinmeyen varlik',
                    'context_label' => null,
                ];

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'entity_type' => $template->entity_type,
                    'entity_id' => $template->entity_id,
                    'entity_label' => $context['entity_label'],
                    'context_label' => $context['context_label'],
                    'report_type' => $template->report_type,
                    'default_range_days' => $template->default_range_days,
                    'layout_preset' => $template->layout_preset,
                    'notes' => $template->notes,
                    'is_active' => $template->is_active,
                    'delivery_schedules_count' => (int) $template->delivery_schedules_count,
                    'created_at' => $template->created_at?->toDateTimeString(),
                    'report_url' => $this->reportUrl($template->entity_type, $template->entity_id),
                ];
            })->values()->all(),
        ];
    }

    public function store(
        Workspace $workspace,
        array $payload,
        ?User $actor = null,
        ?Request $request = null,
    ): ReportTemplate {
        $entityType = (string) $payload['entity_type'];
        $entityId = (string) $payload['entity_id'];
        $reportType = (string) $payload['report_type'];

        $this->assertReportTypeMatchesEntity($entityType, $reportType);
        $this->assertEntityExists($workspace, $entityType, $entityId);

        $template = ReportTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'name' => (string) $payload['name'],
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'report_type' => $reportType,
            'default_range_days' => (int) ($payload['default_range_days'] ?? 30),
            'layout_preset' => (string) ($payload['layout_preset'] ?? 'standard'),
            'configuration' => $payload['configuration'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_template_created',
            targetType: 'report_template',
            targetId: $template->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'entity_type' => $template->entity_type,
                'entity_id' => $template->entity_id,
                'report_type' => $template->report_type,
                'default_range_days' => $template->default_range_days,
            ],
            request: $request,
        );

        return $template;
    }

    private function assertEntityExists(Workspace $workspace, string $entityType, string $entityId): void
    {
        match ($entityType) {
            'account' => MetaAdAccount::query()
                ->where('workspace_id', $workspace->id)
                ->findOrFail($entityId),
            'campaign' => Campaign::query()
                ->where('workspace_id', $workspace->id)
                ->findOrFail($entityId),
            default => throw new \InvalidArgumentException('Desteklenmeyen rapor entity tipi.'),
        };
    }

    private function assertReportTypeMatchesEntity(string $entityType, string $reportType): void
    {
        $expectedReportType = match ($entityType) {
            'account' => 'client_account_summary_v1',
            'campaign' => 'client_campaign_summary_v1',
            default => null,
        };

        if ($expectedReportType !== $reportType) {
            throw new \InvalidArgumentException('Entity tipi ile rapor tipi eslesmiyor.');
        }
    }

    private function reportUrl(string $entityType, string $entityId): string
    {
        return match ($entityType) {
            'account' => sprintf('/reports/account?id=%s', $entityId),
            'campaign' => sprintf('/reports/campaign?id=%s', $entityId),
            default => '/reports',
        };
    }
}
