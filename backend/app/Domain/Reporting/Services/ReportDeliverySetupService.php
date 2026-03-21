<?php

namespace App\Domain\Reporting\Services;

use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

class ReportDeliverySetupService
{
    public function __construct(
        private readonly ReportTemplateService $reportTemplateService,
        private readonly ReportDeliveryScheduleService $reportDeliveryScheduleService,
        private readonly ReportContactService $reportContactService,
        private readonly ReportRecipientPresetService $reportRecipientPresetService,
        private readonly ReportDeliveryProfileService $reportDeliveryProfileService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function store(
        Workspace $workspace,
        array $payload,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $entityType = (string) $payload['entity_type'];
        $entityId = (string) $payload['entity_id'];
        $reportType = $this->reportTypeForEntity($entityType);
        $layoutPreset = (string) ($payload['layout_preset'] ?? 'client_digest');
        $defaultRangeDays = (int) ($payload['default_range_days'] ?? 7);
        $templateName = trim((string) ($payload['template_name'] ?? ''));
        $preset = null;

        if (isset($payload['recipient_preset_id']) && $payload['recipient_preset_id'] !== '') {
            $preset = $this->reportRecipientPresetService->find($workspace->id, (string) $payload['recipient_preset_id']);

            if (! $preset || ! ($preset['is_active'] ?? true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'recipient_preset_id' => 'Secilen alici grubu bulunamadi veya aktif degil.',
                ]);
            }
        }

        $manualContactTags = $this->reportContactService->normalizeTags(
            is_array($payload['contact_tags'] ?? null) ? $payload['contact_tags'] : [],
        );

        $manualRecipients = $this->reportRecipientPresetService->normalizeRecipients(
            is_array($payload['recipients'] ?? null) ? $payload['recipients'] : [],
        );
        $recipientGroup = $this->reportRecipientPresetService->resolveRecipientGroup(
            workspaceId: $workspace->id,
            preset: $preset,
            manualRecipients: $manualRecipients,
            manualContactTags: $manualContactTags,
        );
        $resolvedRecipients = $recipientGroup['resolved_recipients'];

        if ($resolvedRecipients === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recipients' => 'Teslim icin en az bir alici, alici grubu veya kisi etiketi gereklidir.',
            ]);
        }

        $template = ReportTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('report_type', $reportType)
            ->where('layout_preset', $layoutPreset)
            ->where('default_range_days', $defaultRangeDays)
            ->where('is_active', true)
            ->latest()
            ->first();

        $templateCreated = false;

        if (! $template) {
            $template = $this->reportTemplateService->store(
                workspace: $workspace,
                payload: [
                    'name' => $templateName !== '' ? $templateName : $this->suggestTemplateName($workspace, $entityType, $entityId),
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'report_type' => $reportType,
                    'default_range_days' => $defaultRangeDays,
                    'layout_preset' => $layoutPreset,
                    'notes' => $payload['notes'] ?? null,
                    'configuration' => [
                        'created_from' => 'report_delivery_setup',
                    ],
                ],
                actor: $actor,
                request: $request,
            );

            $templateCreated = true;
        }

        $schedule = $this->reportDeliveryScheduleService->store(
            workspace: $workspace,
            payload: array_merge($payload, [
                'report_template_id' => $template->id,
                'recipient_preset_id' => $preset['id'] ?? null,
                'recipients' => $recipientGroup['manual_recipients'],
                'contact_tags' => $manualContactTags,
            ]),
            actor: $actor,
            request: $request,
        );

        $profile = null;

        if ((bool) ($payload['save_as_default_profile'] ?? false)) {
            $profile = $this->reportDeliveryProfileService->upsertFromSetup(
                workspace: $workspace,
                payload: array_merge($payload, [
                    'recipients' => $recipientGroup['manual_recipients'],
                    'contact_tags' => $manualContactTags,
                ]),
                resolvedRecipients: $resolvedRecipients,
                actor: $actor,
                request: $request,
            );
        }

        return [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_created' => $templateCreated,
            'schedule_id' => $schedule->id,
            'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'recipient_preset_id' => $preset['id'] ?? null,
            'recipient_preset_name' => $preset['name'] ?? null,
            'contact_tags' => $recipientGroup['contact_tags'],
            'recipient_group_summary' => $recipientGroup['recipient_group_summary'],
            'profile_saved' => $profile !== null,
            'profile_id' => $profile['id'] ?? null,
        ];
    }

    private function reportTypeForEntity(string $entityType): string
    {
        return match ($entityType) {
            'account' => 'client_account_summary_v1',
            'campaign' => 'client_campaign_summary_v1',
            default => throw new \InvalidArgumentException('Desteklenmeyen rapor entity tipi.'),
        };
    }

    private function suggestTemplateName(Workspace $workspace, string $entityType, string $entityId): string
    {
        return match ($entityType) {
            'account' => sprintf(
                '%s / Musteri Raporu',
                MetaAdAccount::query()->where('workspace_id', $workspace->id)->findOrFail($entityId)->name,
            ),
            'campaign' => sprintf(
                '%s / Musteri Raporu',
                Campaign::query()->where('workspace_id', $workspace->id)->findOrFail($entityId)->name,
            ),
            default => 'Musteri Raporu',
        };
    }
}
