<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Operations\EntityContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportDeliveryProfileService
{
    private const SETTING_KEY = 'reports.delivery_profiles';

    public function __construct(
        private readonly ReportWorkspaceConfigStore $configStore,
        private readonly ReportContactService $reportContactService,
        private readonly ReportRecipientPresetService $recipientPresetService,
        private readonly EntityContextResolver $entityContextResolver,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array{summary: array<string, int>, items: array<int, array<string, mixed>>}
     */
    public function index(string $workspaceId): array
    {
        $presets = collect($this->recipientPresetService->index($workspaceId)['items'])
            ->keyBy('id');

        $profiles = $this->configStore
            ->collection($workspaceId, self::SETTING_KEY)
            ->map(fn (array $item): array => $this->normalizeItem($item, $presets))
            ->values();

        $contexts = $this->entityContextResolver->resolveMany(
            $workspaceId,
            $profiles->map(fn (array $profile): array => [
                'type' => $profile['entity_type'],
                'id' => $profile['entity_id'],
            ])->all(),
        );

        $items = $profiles
            ->map(function (array $profile) use ($contexts, $workspaceId, $presets): array {
                $preset = $profile['recipient_preset_id']
                    ? $this->resolvePresetFromCollection($presets, $profile['recipient_preset_id'])
                    : null;
                $context = $contexts[$this->entityContextResolver->key($profile['entity_type'], $profile['entity_id'])] ?? [
                    'entity_label' => 'Bilinmeyen varlik',
                    'context_label' => null,
                ];

                return $this->enrichRecipientResolution($workspaceId, array_merge($profile, [
                    'entity_label' => $context['entity_label'],
                    'context_label' => $context['context_label'],
                    'report_url' => $this->reportUrl($profile['entity_type'], $profile['entity_id']),
                ]), $preset);
            })
            ->sortBy([
                ['is_active', 'desc'],
                ['updated_at', 'desc'],
            ])
            ->values();

        return [
            'summary' => [
                'total_profiles' => $items->count(),
                'active_profiles' => $items->where('is_active', true)->count(),
            ],
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEntity(string $workspaceId, string $entityType, string $entityId): ?array
    {
        $presets = collect($this->recipientPresetService->index($workspaceId)['items'])->keyBy('id');

        $raw = $this->configStore
            ->collection($workspaceId, self::SETTING_KEY)
            ->first(function (array $item) use ($entityType, $entityId): bool {
                return (string) ($item['entity_type'] ?? '') === $entityType
                    && (string) ($item['entity_id'] ?? '') === $entityId;
            });

        if (! $raw) {
            return null;
        }

        $profile = $this->normalizeItem($raw, $presets);
        $context = $this->entityContextResolver->resolveMany($workspaceId, [[
            'type' => $profile['entity_type'],
            'id' => $profile['entity_id'],
        ]]);
        $resolved = $context[$this->entityContextResolver->key($profile['entity_type'], $profile['entity_id'])] ?? [
            'entity_label' => 'Bilinmeyen varlik',
            'context_label' => null,
        ];

        return $this->enrichRecipientResolution($workspaceId, array_merge($profile, [
            'entity_label' => $resolved['entity_label'],
            'context_label' => $resolved['context_label'],
            'report_url' => $this->reportUrl($profile['entity_type'], $profile['entity_id']),
        ]), $profile['recipient_preset_id'] ? $this->resolvePresetFromCollection($presets, $profile['recipient_preset_id']) : null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $resolvedRecipients
     * @return array<string, mixed>
     */
    public function upsertFromSetup(
        Workspace $workspace,
        array $payload,
        array $resolvedRecipients,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        return $this->persist(
            workspace: $workspace,
            payload: $payload,
            resolvedRecipients: $resolvedRecipients,
            actor: $actor,
            request: $request,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function upsert(
        Workspace $workspace,
        array $payload,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $resolvedRecipients = $this->resolveRecipients($workspace->id, $payload);

        return $this->persist(
            workspace: $workspace,
            payload: $payload,
            resolvedRecipients: $resolvedRecipients,
            actor: $actor,
            request: $request,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleByEntity(
        Workspace $workspace,
        string $entityType,
        string $entityId,
        ?bool $isActive,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $items = $this->configStore->collection($workspace->id, self::SETTING_KEY);
        $recipientPresets = collect($this->recipientPresetService->index($workspace->id)['items'])->keyBy('id');
        $existingIndex = $this->findIndexByEntity($items->all(), $entityType, $entityId);

        if ($existingIndex === false) {
            throw ValidationException::withMessages([
                'entity_id' => 'Durumu guncellenecek teslim profili bulunamadi.',
            ]);
        }

        $current = $this->normalizeItem($items->get($existingIndex), $recipientPresets);
        $current['is_active'] = $isActive ?? ! $current['is_active'];
        $current['updated_at'] = now()->toDateTimeString();

        $items->put($existingIndex, $current);
        $this->configStore->put($workspace, self::SETTING_KEY, $items->all(), $actor);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_delivery_profile_toggled',
            targetType: 'report_delivery_profile',
            targetId: $current['id'],
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'entity_type' => $current['entity_type'],
                'entity_id' => $current['entity_id'],
                'is_active' => $current['is_active'],
            ],
            request: $request,
        );

        return $current;
    }

    public function deleteByEntity(
        Workspace $workspace,
        string $entityType,
        string $entityId,
        ?User $actor = null,
        ?Request $request = null,
    ): void {
        $items = $this->configStore->collection($workspace->id, self::SETTING_KEY);
        $existingIndex = $this->findIndexByEntity($items->all(), $entityType, $entityId);

        if ($existingIndex === false) {
            throw ValidationException::withMessages([
                'entity_id' => 'Silinecek teslim profili bulunamadi.',
            ]);
        }

        $current = $this->normalizeItem($items->get($existingIndex));

        $items->forget($existingIndex);
        $this->configStore->put($workspace, self::SETTING_KEY, $items->values()->all(), $actor);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_delivery_profile_deleted',
            targetType: 'report_delivery_profile',
            targetId: $current['id'],
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'entity_type' => $current['entity_type'],
                'entity_id' => $current['entity_id'],
            ],
            request: $request,
        );
    }

    /**
     * @param  array<int, string>  $resolvedRecipients
     * @return array<string, mixed>
     */
    private function persist(
        Workspace $workspace,
        array $payload,
        array $resolvedRecipients,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $items = $this->configStore->collection($workspace->id, self::SETTING_KEY);
        $recipientPresets = collect($this->recipientPresetService->index($workspace->id)['items'])->keyBy('id');
        $entityType = (string) $payload['entity_type'];
        $entityId = (string) $payload['entity_id'];

        $existingIndex = $this->findIndexByEntity($items->all(), $entityType, $entityId);

        $now = now()->toDateTimeString();
        $raw = [
            'id' => $existingIndex !== false
                ? (string) ($items->get($existingIndex)['id'] ?? Str::uuid()->toString())
                : (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'recipient_preset_id' => isset($payload['recipient_preset_id']) && $payload['recipient_preset_id'] !== ''
                ? (string) $payload['recipient_preset_id']
                : null,
            'contact_tags' => $this->reportContactService->normalizeTags(
                is_array($payload['contact_tags'] ?? null) ? $payload['contact_tags'] : [],
            ),
            'delivery_channel' => (string) ($payload['delivery_channel'] ?? 'email_stub'),
            'cadence' => (string) $payload['cadence'],
            'weekday' => isset($payload['weekday']) ? (int) $payload['weekday'] : null,
            'month_day' => isset($payload['month_day']) ? (int) $payload['month_day'] : null,
            'send_time' => (string) $payload['send_time'],
            'timezone' => (string) ($payload['timezone'] ?? $workspace->timezone),
            'default_range_days' => (int) ($payload['default_range_days'] ?? 7),
            'layout_preset' => (string) ($payload['layout_preset'] ?? 'client_digest'),
            'recipients' => $this->recipientPresetService->normalizeRecipients(
                is_array($payload['recipients'] ?? null) ? $payload['recipients'] : [],
            ),
            'share_delivery' => [
                'enabled' => (bool) ($payload['auto_share_enabled'] ?? false),
                'label_template' => $payload['share_label_template'] ?? null,
                'expires_in_days' => isset($payload['share_expires_in_days'])
                    ? (int) $payload['share_expires_in_days']
                    : null,
                'allow_csv_download' => (bool) ($payload['share_allow_csv_download'] ?? false),
            ],
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'created_at' => $existingIndex !== false
                ? (string) ($items->get($existingIndex)['created_at'] ?? $now)
                : $now,
            'updated_at' => $now,
        ];

        $item = $this->normalizeItem($raw, $recipientPresets);

        if ($existingIndex !== false) {
            $items->put($existingIndex, $item);
        } else {
            $items->push($item);
        }

        $this->configStore->put($workspace, self::SETTING_KEY, $items->all(), $actor);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_delivery_profile_upserted',
            targetType: 'report_delivery_profile',
            targetId: $item['id'],
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'entity_type' => $item['entity_type'],
                'entity_id' => $item['entity_id'],
                'recipient_preset_id' => $item['recipient_preset_id'],
                'contact_tags' => $item['contact_tags'],
                'recipients_count' => $item['recipients_count'],
                'cadence' => $item['cadence'],
            ],
            request: $request,
        );

        return $item;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function resolveRecipients(string $workspaceId, array $payload): array
    {
        $preset = null;

        if (isset($payload['recipient_preset_id']) && $payload['recipient_preset_id'] !== '') {
            $preset = $this->recipientPresetService->find($workspaceId, (string) $payload['recipient_preset_id']);

            if (! $preset || ! ($preset['is_active'] ?? true)) {
                throw ValidationException::withMessages([
                    'recipient_preset_id' => 'Secilen alici grubu bulunamadi veya aktif degil.',
                ]);
            }
        }

        $manualContactTags = $this->reportContactService->normalizeTags(
            is_array($payload['contact_tags'] ?? null) ? $payload['contact_tags'] : [],
        );

        $recipientGroup = $this->recipientPresetService->resolveRecipientGroup(
            workspaceId: $workspaceId,
            preset: $preset,
            manualRecipients: is_array($payload['recipients'] ?? null) ? $payload['recipients'] : [],
            manualContactTags: $manualContactTags,
        );
        $resolvedRecipients = $recipientGroup['resolved_recipients'];

        if ($resolvedRecipients === []) {
            throw ValidationException::withMessages([
                'recipients' => 'Teslim icin en az bir alici, alici grubu veya kisi etiketi gereklidir.',
            ]);
        }

        return $resolvedRecipients;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>|null  $preset
     * @return array<string, mixed>
     */
    private function enrichRecipientResolution(string $workspaceId, array $profile, ?array $preset = null): array
    {
        $recipientGroup = $this->recipientPresetService->resolveRecipientGroup(
            workspaceId: $workspaceId,
            preset: $preset,
            manualRecipients: is_array($profile['recipients'] ?? null) ? $profile['recipients'] : [],
            manualContactTags: is_array($profile['contact_tags'] ?? null) ? $profile['contact_tags'] : [],
        );

        return array_merge($profile, [
            'tagged_contacts' => $recipientGroup['tagged_contacts'],
            'tagged_contacts_count' => count($recipientGroup['tagged_contacts']),
            'resolved_recipients' => $recipientGroup['resolved_recipients'],
            'resolved_recipients_count' => count($recipientGroup['resolved_recipients']),
            'recipient_group_summary' => $recipientGroup['recipient_group_summary'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function findIndexByEntity(array $items, string $entityType, string $entityId): int|false
    {
        return collect($items)->search(function (array $item) use ($entityType, $entityId): bool {
            return (string) ($item['entity_type'] ?? '') === $entityType
                && (string) ($item['entity_id'] ?? '') === $entityId;
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>|null  $presets
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item, $presets = null): array
    {
        $recipients = $this->recipientPresetService->normalizeRecipients(
            is_array($item['recipients'] ?? null) ? $item['recipients'] : [],
        );
        $presetId = isset($item['recipient_preset_id']) && $item['recipient_preset_id'] !== ''
            ? (string) $item['recipient_preset_id']
            : null;
        $preset = $presetId && $presets ? $presets->get($presetId) : null;
        $shareDelivery = is_array($item['share_delivery'] ?? null) ? $item['share_delivery'] : [];

        return [
            'id' => (string) ($item['id'] ?? Str::uuid()->toString()),
            'entity_type' => (string) ($item['entity_type'] ?? 'campaign'),
            'entity_id' => (string) ($item['entity_id'] ?? ''),
            'recipient_preset_id' => $presetId,
            'recipient_preset_name' => $preset['name'] ?? null,
            'contact_tags' => $this->reportContactService->normalizeTags(
                is_array($item['contact_tags'] ?? null) ? $item['contact_tags'] : [],
            ),
            'delivery_channel' => (string) ($item['delivery_channel'] ?? 'email_stub'),
            'delivery_channel_label' => $this->deliveryChannelLabel((string) ($item['delivery_channel'] ?? 'email_stub')),
            'cadence' => (string) ($item['cadence'] ?? 'weekly'),
            'cadence_label' => $this->cadenceLabel(
                (string) ($item['cadence'] ?? 'weekly'),
                isset($item['weekday']) ? (int) $item['weekday'] : null,
                isset($item['month_day']) ? (int) $item['month_day'] : null,
                (string) ($item['send_time'] ?? '09:00'),
            ),
            'weekday' => isset($item['weekday']) ? (int) $item['weekday'] : null,
            'month_day' => isset($item['month_day']) ? (int) $item['month_day'] : null,
            'send_time' => (string) ($item['send_time'] ?? '09:00'),
            'timezone' => (string) ($item['timezone'] ?? 'Europe/Istanbul'),
            'default_range_days' => (int) ($item['default_range_days'] ?? 7),
            'layout_preset' => (string) ($item['layout_preset'] ?? 'client_digest'),
            'recipients' => $recipients,
            'recipients_count' => count($recipients),
            'share_delivery' => [
                'enabled' => (bool) ($shareDelivery['enabled'] ?? false),
                'label_template' => isset($shareDelivery['label_template']) && trim((string) $shareDelivery['label_template']) !== ''
                    ? trim((string) $shareDelivery['label_template'])
                    : null,
                'expires_in_days' => isset($shareDelivery['expires_in_days']) ? (int) $shareDelivery['expires_in_days'] : null,
                'allow_csv_download' => (bool) ($shareDelivery['allow_csv_download'] ?? false),
            ],
            'is_active' => (bool) ($item['is_active'] ?? true),
            'created_at' => isset($item['created_at']) ? (string) $item['created_at'] : null,
            'updated_at' => isset($item['updated_at']) ? (string) $item['updated_at'] : null,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $presets
     * @return array<string, mixed>|null
     */
    private function resolvePresetFromCollection($presets, ?string $presetId): ?array
    {
        if ($presetId === null || $presetId === '') {
            return null;
        }

        $preset = $presets->get($presetId);

        return is_array($preset) ? $preset : null;
    }

    private function cadenceLabel(string $cadence, ?int $weekday, ?int $monthDay, string $sendTime): string
    {
        return match ($cadence) {
            'daily' => sprintf('Her gun %s', $sendTime),
            'weekly' => sprintf('Her %s %s', $this->weekdayLabel($weekday ?? 1), $sendTime),
            'monthly' => sprintf('Her ay %d. gun %s', (int) ($monthDay ?? 1), $sendTime),
            default => $cadence,
        };
    }

    private function weekdayLabel(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Pazartesi',
            2 => 'Sali',
            3 => 'Carsamba',
            4 => 'Persembe',
            5 => 'Cuma',
            6 => 'Cumartesi',
            7 => 'Pazar',
            default => 'Gun',
        };
    }

    private function deliveryChannelLabel(string $channel): string
    {
        return match ($channel) {
            'email' => 'Gercek Email',
            'email_stub' => 'Email Stub',
            default => $channel,
        };
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
