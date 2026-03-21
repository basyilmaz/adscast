<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportRecipientPresetService
{
    private const SETTING_KEY = 'reports.recipient_presets';

    public function __construct(
        private readonly ReportWorkspaceConfigStore $configStore,
        private readonly ReportContactService $reportContactService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array{summary: array<string, int>, items: array<int, array<string, mixed>>}
     */
    public function index(string $workspaceId): array
    {
        $items = $this->configStore
            ->collection($workspaceId, self::SETTING_KEY)
            ->map(fn (array $item): array => $this->normalizeItem($workspaceId, $item))
            ->sortBy([
                ['is_active', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        return [
            'summary' => [
                'total_presets' => $items->count(),
                'active_presets' => $items->where('is_active', true)->count(),
                'total_recipients' => $items->sum(fn (array $item): int => $item['resolved_recipients_count']),
                'segment_backed_presets' => $items->filter(
                    fn (array $item): bool => count($item['contact_tags']) > 0,
                )->count(),
            ],
            'items' => $items->all(),
        ];
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
        $items = $this->configStore->collection($workspace->id, self::SETTING_KEY);
        $name = trim((string) $payload['name']);

        $this->assertUniqueName($items, $name);

        $raw = [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'recipients' => $this->normalizeRecipients($payload['recipients'] ?? []),
            'contact_tags' => $this->reportContactService->normalizeTags($payload['contact_tags'] ?? []),
            'notes' => isset($payload['notes']) ? trim((string) $payload['notes']) : null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
        $item = $this->normalizeItem($workspace->id, $raw);

        $items->push($raw);

        $this->configStore->put($workspace, self::SETTING_KEY, $items->all(), $actor);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_recipient_preset_created',
            targetType: 'report_recipient_preset',
            targetId: $item['id'],
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'name' => $item['name'],
                'recipients_count' => $item['recipients_count'],
                'contact_tags' => $item['contact_tags'],
                'resolved_recipients_count' => $item['resolved_recipients_count'],
            ],
            request: $request,
        );

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function update(
        Workspace $workspace,
        string $presetId,
        array $payload,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $items = $this->configStore->collection($workspace->id, self::SETTING_KEY);
        $index = $items->search(fn (array $item): bool => (string) ($item['id'] ?? '') === $presetId);

        if ($index === false) {
            throw ValidationException::withMessages([
                'preset_id' => 'Duzenlenecek alici grubu bulunamadi.',
            ]);
        }

        $current = $this->normalizeItem($workspace->id, $items->get($index));
        $name = trim((string) $payload['name']);
        $this->assertUniqueName($items, $name, $presetId);

        $raw = [
            'id' => $presetId,
            'name' => $name,
            'recipients' => $this->normalizeRecipients($payload['recipients'] ?? []),
            'contact_tags' => $this->reportContactService->normalizeTags($payload['contact_tags'] ?? []),
            'notes' => isset($payload['notes']) ? trim((string) $payload['notes']) : null,
            'is_active' => (bool) ($payload['is_active'] ?? $current['is_active']),
            'created_at' => $current['created_at'],
            'updated_at' => now()->toDateTimeString(),
        ];
        $item = $this->normalizeItem($workspace->id, $raw);

        $items->put($index, $raw);
        $this->configStore->put($workspace, self::SETTING_KEY, $items->all(), $actor);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_recipient_preset_updated',
            targetType: 'report_recipient_preset',
            targetId: $item['id'],
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'name' => $item['name'],
                'recipients_count' => $item['recipients_count'],
                'contact_tags' => $item['contact_tags'],
                'resolved_recipients_count' => $item['resolved_recipients_count'],
                'is_active' => $item['is_active'],
            ],
            request: $request,
        );

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function toggle(
        Workspace $workspace,
        string $presetId,
        ?bool $isActive,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $items = $this->configStore->collection($workspace->id, self::SETTING_KEY);
        $index = $items->search(fn (array $item): bool => (string) ($item['id'] ?? '') === $presetId);

        if ($index === false) {
            throw ValidationException::withMessages([
                'preset_id' => 'Durumu degistirilecek alici grubu bulunamadi.',
            ]);
        }

        $currentRaw = $items->get($index);
        $current = $this->normalizeItem($workspace->id, $currentRaw);
        $currentRaw['is_active'] = $isActive ?? ! $current['is_active'];
        $currentRaw['updated_at'] = now()->toDateTimeString();
        $current = $this->normalizeItem($workspace->id, $currentRaw);

        $items->put($index, $currentRaw);
        $this->configStore->put($workspace, self::SETTING_KEY, $items->all(), $actor);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_recipient_preset_toggled',
            targetType: 'report_recipient_preset',
            targetId: $current['id'],
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'is_active' => $current['is_active'],
                'name' => $current['name'],
            ],
            request: $request,
        );

        return $current;
    }

    public function delete(
        Workspace $workspace,
        string $presetId,
        ?User $actor = null,
        ?Request $request = null,
    ): void {
        $items = $this->configStore->collection($workspace->id, self::SETTING_KEY);
        $item = $this->find($workspace->id, $presetId);

        if (! $item) {
            throw ValidationException::withMessages([
                'preset_id' => 'Silinecek alici grubu bulunamadi.',
            ]);
        }

        $items = $items
            ->reject(fn (array $candidate): bool => (string) ($candidate['id'] ?? '') === $presetId)
            ->values();

        $this->configStore->put($workspace, self::SETTING_KEY, $items->all(), $actor);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_recipient_preset_deleted',
            targetType: 'report_recipient_preset',
            targetId: $presetId,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'name' => $item['name'],
            ],
            request: $request,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $workspaceId, string $presetId): ?array
    {
        $item = $this->configStore
            ->collection($workspaceId, self::SETTING_KEY)
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $presetId);

        return $item ? $this->normalizeItem($workspaceId, $item) : null;
    }

    /**
     * @param  array<string, mixed>|null  $preset
     * @param  array<int, mixed>  $manualRecipients
     * @param  array<int, mixed>  $manualContactTags
     * @return array{
     *   manual_recipients: array<int, string>,
     *   preset_recipients: array<int, string>,
     *   contact_tags: array<int, string>,
     *   tagged_contacts: array<int, array<string, mixed>>,
     *   resolved_recipients: array<int, string>,
     *   recipient_group_summary: array<string, mixed>
     * }
     */
    public function resolveRecipientGroup(
        string $workspaceId,
        ?array $preset = null,
        array $manualRecipients = [],
        array $manualContactTags = [],
    ): array {
        $manualRecipients = $this->normalizeRecipients($manualRecipients);
        $manualContactTags = $this->reportContactService->normalizeTags($manualContactTags);
        $presetRecipients = $this->normalizeRecipients(
            is_array($preset['recipients'] ?? null) ? $preset['recipients'] : [],
        );
        $mergedContactTags = $this->reportContactService->normalizeTags(array_merge(
            is_array($preset['contact_tags'] ?? null) ? $preset['contact_tags'] : [],
            $manualContactTags,
        ));
        $taggedContacts = $this->reportContactService->findActiveByTags($workspaceId, $mergedContactTags);
        $resolvedRecipients = $this->normalizeRecipients(array_merge(
            $manualRecipients,
            $presetRecipients,
            $this->reportContactService->extractEmails($taggedContacts),
        ));

        return [
            'manual_recipients' => $manualRecipients,
            'preset_recipients' => $presetRecipients,
            'contact_tags' => $mergedContactTags,
            'tagged_contacts' => $taggedContacts,
            'resolved_recipients' => $resolvedRecipients,
            'recipient_group_summary' => $this->recipientGroupSummary(
                presetName: $preset['name'] ?? null,
                manualRecipients: $manualRecipients,
                presetRecipients: $presetRecipients,
                contactTags: $mergedContactTags,
                taggedContacts: $taggedContacts,
                resolvedRecipients: $resolvedRecipients,
            ),
        ];
    }

    /**
     * @param  array<int, mixed>  $recipients
     * @return array<int, string>
     */
    public function normalizeRecipients(array $recipients): array
    {
        return collect($recipients)
            ->map(fn (mixed $recipient): string => trim((string) $recipient))
            ->filter(fn (string $recipient): bool => $recipient !== '')
            ->unique(fn (string $recipient): string => mb_strtolower($recipient))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(string $workspaceId, array $item): array
    {
        $recipients = $this->normalizeRecipients(is_array($item['recipients'] ?? null) ? $item['recipients'] : []);
        $contactTags = $this->reportContactService->normalizeTags(
            is_array($item['contact_tags'] ?? null) ? $item['contact_tags'] : [],
        );
        $recipientGroup = $this->resolveRecipientGroup(
            workspaceId: $workspaceId,
            preset: null,
            manualRecipients: $recipients,
            manualContactTags: $contactTags,
        );

        return [
            'id' => (string) ($item['id'] ?? Str::uuid()->toString()),
            'name' => trim((string) ($item['name'] ?? 'Alici Grubu')),
            'recipients' => $recipients,
            'recipients_count' => count($recipients),
            'contact_tags' => $contactTags,
            'tagged_contacts' => $recipientGroup['tagged_contacts'],
            'tagged_contacts_count' => count($recipientGroup['tagged_contacts']),
            'resolved_recipients' => $recipientGroup['resolved_recipients'],
            'resolved_recipients_count' => count($recipientGroup['resolved_recipients']),
            'recipient_group_summary' => $recipientGroup['recipient_group_summary'],
            'notes' => isset($item['notes']) && trim((string) $item['notes']) !== ''
                ? trim((string) $item['notes'])
                : null,
            'is_active' => (bool) ($item['is_active'] ?? true),
            'created_at' => isset($item['created_at']) ? (string) $item['created_at'] : null,
            'updated_at' => isset($item['updated_at']) ? (string) $item['updated_at'] : null,
        ];
    }

    /**
     * @param  array<int, string>  $manualRecipients
     * @param  array<int, string>  $presetRecipients
     * @param  array<int, string>  $contactTags
     * @param  array<int, array<string, mixed>>  $taggedContacts
     * @param  array<int, string>  $resolvedRecipients
     * @return array<string, mixed>
     */
    private function recipientGroupSummary(
        ?string $presetName,
        array $manualRecipients,
        array $presetRecipients,
        array $contactTags,
        array $taggedContacts,
        array $resolvedRecipients,
    ): array {
        $hasPreset = $presetName !== null && $presetName !== '';
        $hasManualRecipients = count($manualRecipients) > 0;
        $hasSegments = count($contactTags) > 0;

        $mode = match (true) {
            $hasPreset && $hasManualRecipients && $hasSegments => 'preset_plus_manual_plus_segment',
            $hasPreset && $hasManualRecipients => 'preset_plus_manual',
            $hasPreset && $hasSegments => 'preset_plus_segment',
            $hasPreset => 'preset',
            $hasManualRecipients && $hasSegments => 'manual_plus_segment',
            $hasSegments => 'segment',
            $hasManualRecipients => 'manual',
            default => 'empty',
        };

        $label = match ($mode) {
            'preset_plus_manual_plus_segment' => sprintf('Kayitli grup + manuel + segment (%s)', implode(', ', $contactTags)),
            'preset_plus_manual' => sprintf('Kayitli grup + manuel alici (%s)', $presetName ?? 'Kayitli grup'),
            'preset_plus_segment' => sprintf('Kayitli grup + segment (%s)', implode(', ', $contactTags)),
            'preset' => sprintf('Kayitli grup: %s', $presetName ?? 'Kayitli grup'),
            'manual_plus_segment' => sprintf('Manuel alici + segment (%s)', implode(', ', $contactTags)),
            'segment' => sprintf('Segment: %s', implode(', ', $contactTags)),
            'manual' => 'Manuel alici listesi',
            default => 'Alici grubu tanimli degil',
        };

        return [
            'mode' => $mode,
            'label' => $label,
            'preset_name' => $presetName,
            'contact_tags' => $contactTags,
            'static_recipients_count' => count($manualRecipients) + count($presetRecipients),
            'manual_recipients_count' => count($manualRecipients),
            'preset_recipients_count' => count($presetRecipients),
            'dynamic_contacts_count' => count($taggedContacts),
            'resolved_recipients_count' => count($resolvedRecipients),
            'sample_contact_names' => collect($taggedContacts)->pluck('name')->take(3)->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function assertUniqueName(Collection $items, string $name, ?string $ignoreId = null): void
    {
        $nameExists = $items->contains(function (array $item) use ($name, $ignoreId): bool {
            if ($ignoreId !== null && (string) ($item['id'] ?? '') === $ignoreId) {
                return false;
            }

            return mb_strtolower((string) ($item['name'] ?? '')) === mb_strtolower($name);
        });

        if ($nameExists) {
            throw ValidationException::withMessages([
                'name' => 'Bu isimle kayitli bir alici grubu zaten var.',
            ]);
        }
    }
}
