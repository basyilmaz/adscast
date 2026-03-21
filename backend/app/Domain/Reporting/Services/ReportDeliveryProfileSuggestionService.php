<?php

namespace App\Domain\Reporting\Services;

use App\Models\Workspace;

class ReportDeliveryProfileSuggestionService
{
    public function __construct(
        private readonly ReportRecipientGroupAdvisorService $reportRecipientGroupAdvisorService,
        private readonly ReportRecipientPresetService $reportRecipientPresetService,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $currentProfile
     * @return array<string, mixed>|null
     */
    public function suggestForEntity(
        string $workspaceId,
        string $entityType,
        string $entityId,
        ?array $currentProfile = null,
    ): ?array {
        $candidate = collect($this->reportRecipientGroupAdvisorService->suggestForEntity(
            workspaceId: $workspaceId,
            entityType: $entityType,
            entityId: $entityId,
            currentProfile: $currentProfile,
            limit: 12,
        ))->first(fn (array $item): bool => $this->isManagedTemplateCandidate($item));

        if (! is_array($candidate)) {
            return null;
        }

        $presetId = (string) ($candidate['recipient_preset_id'] ?? '');

        if ($presetId === '') {
            return null;
        }

        $preset = $this->reportRecipientPresetService->find($workspaceId, $presetId);

        if (! is_array($preset) || ! ($preset['is_active'] ?? true)) {
            return null;
        }

        $templateProfile = is_array($preset['template_profile'] ?? null) ? $preset['template_profile'] : null;
        $templateRuleSummary = is_array($preset['template_rule_summary'] ?? null) ? $preset['template_rule_summary'] : null;

        if ($templateProfile === null || $templateRuleSummary === null) {
            return null;
        }

        $defaults = $this->defaultsForTemplateKind((string) ($templateProfile['kind'] ?? 'client_reporting'));
        $workspaceTimezone = Workspace::query()->whereKey($workspaceId)->value('timezone') ?: 'Europe/Istanbul';
        $shareDelivery = $this->shareDefaults($defaults, $currentProfile);

        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'recipient_preset_id' => $preset['id'],
            'delivery_channel' => (string) ($currentProfile['delivery_channel'] ?? 'email'),
            'cadence' => $defaults['cadence'],
            'weekday' => $defaults['weekday'],
            'month_day' => $defaults['month_day'],
            'send_time' => $defaults['send_time'],
            'timezone' => (string) ($currentProfile['timezone'] ?? $workspaceTimezone),
            'default_range_days' => $defaults['default_range_days'],
            'layout_preset' => $defaults['layout_preset'],
            'recipients' => [],
            'contact_tags' => $preset['contact_tags'] ?? [],
            'auto_share_enabled' => $shareDelivery['enabled'],
            'share_label_template' => $shareDelivery['label_template'],
            'share_expires_in_days' => $shareDelivery['expires_in_days'],
            'share_allow_csv_download' => $shareDelivery['allow_csv_download'],
        ];

        $changes = $this->diffAgainstCurrentProfile($currentProfile, $payload);
        $status = $currentProfile === null
            ? 'recommended'
            : ($changes === [] ? 'already_applied' : 'upgrade_available');

        return [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'can_apply' => $status !== 'already_applied',
            'score' => (int) ($candidate['score'] ?? 0),
            'recommendation_label' => $candidate['recommendation_label'] ?? null,
            'reason' => $this->reasonText($status, $candidate['recommendation_reason'] ?? null),
            'changes' => $changes,
            'recipient_preset_id' => $preset['id'],
            'recipient_preset_name' => $preset['name'],
            'template_profile' => $templateProfile,
            'template_rule_summary' => $templateRuleSummary,
            'delivery_channel' => $payload['delivery_channel'],
            'delivery_channel_label' => $this->deliveryChannelLabel($payload['delivery_channel']),
            'cadence' => $payload['cadence'],
            'cadence_label' => $this->cadenceLabel(
                $payload['cadence'],
                $payload['weekday'],
                $payload['month_day'],
                $payload['send_time'],
            ),
            'weekday' => $payload['weekday'],
            'month_day' => $payload['month_day'],
            'send_time' => $payload['send_time'],
            'timezone' => $payload['timezone'],
            'default_range_days' => $payload['default_range_days'],
            'layout_preset' => $payload['layout_preset'],
            'resolved_recipients' => $preset['resolved_recipients'] ?? [],
            'resolved_recipients_count' => (int) ($preset['resolved_recipients_count'] ?? 0),
            'recipient_group_summary' => $preset['recipient_group_summary'] ?? null,
            'share_delivery' => $shareDelivery,
            'apply_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function isManagedTemplateCandidate(array $candidate): bool
    {
        $templateProfile = is_array($candidate['template_profile'] ?? null) ? $candidate['template_profile'] : null;

        if ($templateProfile === null || ($candidate['source_type'] ?? null) !== 'preset') {
            return false;
        }

        return ((int) ($templateProfile['priority'] ?? 50)) !== 50
            || (bool) ($templateProfile['is_recommended_default'] ?? false)
            || count($templateProfile['target_entity_types'] ?? []) > 0
            || count($templateProfile['matching_companies'] ?? []) > 0;
    }

    /**
     * @return array{
     *   cadence: string,
     *   weekday: int|null,
     *   month_day: int|null,
     *   send_time: string,
     *   default_range_days: int,
     *   layout_preset: string,
     *   share_enabled: bool,
     *   share_expires_in_days: int|null,
     *   share_allow_csv_download: bool
     * }
     */
    private function defaultsForTemplateKind(string $kind): array
    {
        return match ($kind) {
            'stakeholder_update' => [
                'cadence' => 'weekly',
                'weekday' => 3,
                'month_day' => null,
                'send_time' => '09:00',
                'default_range_days' => 7,
                'layout_preset' => 'client_digest',
                'share_enabled' => true,
                'share_expires_in_days' => 14,
                'share_allow_csv_download' => false,
            ],
            'executive_digest' => [
                'cadence' => 'monthly',
                'weekday' => null,
                'month_day' => 3,
                'send_time' => '08:45',
                'default_range_days' => 30,
                'layout_preset' => 'client_digest',
                'share_enabled' => true,
                'share_expires_in_days' => 30,
                'share_allow_csv_download' => true,
            ],
            'internal_ops' => [
                'cadence' => 'daily',
                'weekday' => null,
                'month_day' => null,
                'send_time' => '09:00',
                'default_range_days' => 7,
                'layout_preset' => 'client_digest',
                'share_enabled' => false,
                'share_expires_in_days' => null,
                'share_allow_csv_download' => false,
            ],
            default => [
                'cadence' => 'weekly',
                'weekday' => 1,
                'month_day' => null,
                'send_time' => '09:30',
                'default_range_days' => 14,
                'layout_preset' => 'client_digest',
                'share_enabled' => true,
                'share_expires_in_days' => 14,
                'share_allow_csv_download' => true,
            ],
        };
    }

    /**
     * @param  array<string, mixed>|null  $currentProfile
     * @param  array{
     *   cadence: string,
     *   weekday: int|null,
     *   month_day: int|null,
     *   send_time: string,
     *   default_range_days: int,
     *   layout_preset: string,
     *   share_enabled: bool,
     *   share_expires_in_days: int|null,
     *   share_allow_csv_download: bool
     * }  $defaults
     * @return array<string, mixed>
     */
    private function shareDefaults(array $defaults, ?array $currentProfile): array
    {
        $currentShare = is_array($currentProfile['share_delivery'] ?? null) ? $currentProfile['share_delivery'] : null;

        return [
            'enabled' => (bool) ($currentShare['enabled'] ?? $defaults['share_enabled']),
            'label_template' => $currentShare['label_template'] ?? '{template_name} / {end_date}',
            'expires_in_days' => isset($currentShare['expires_in_days'])
                ? (int) $currentShare['expires_in_days']
                : $defaults['share_expires_in_days'],
            'allow_csv_download' => (bool) ($currentShare['allow_csv_download'] ?? $defaults['share_allow_csv_download']),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $currentProfile
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function diffAgainstCurrentProfile(?array $currentProfile, array $payload): array
    {
        if ($currentProfile === null) {
            return ['recipient_group', 'cadence', 'send_time', 'range', 'share_delivery'];
        }

        $changes = [];

        if (($currentProfile['recipient_preset_id'] ?? null) !== $payload['recipient_preset_id']) {
            $changes[] = 'recipient_group';
        }

        if (($currentProfile['cadence'] ?? null) !== $payload['cadence']) {
            $changes[] = 'cadence';
        }

        if (($currentProfile['weekday'] ?? null) !== $payload['weekday'] || ($currentProfile['month_day'] ?? null) !== $payload['month_day']) {
            $changes[] = 'schedule_slot';
        }

        if (($currentProfile['send_time'] ?? null) !== $payload['send_time']) {
            $changes[] = 'send_time';
        }

        if ((int) ($currentProfile['default_range_days'] ?? 0) !== (int) $payload['default_range_days']) {
            $changes[] = 'range';
        }

        if (($currentProfile['layout_preset'] ?? null) !== $payload['layout_preset']) {
            $changes[] = 'layout';
        }

        $currentTags = collect($currentProfile['contact_tags'] ?? [])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if ($currentTags !== $payload['contact_tags']) {
            $changes[] = 'contact_tags';
        }

        $currentShare = is_array($currentProfile['share_delivery'] ?? null) ? $currentProfile['share_delivery'] : [];

        if (
            (bool) ($currentShare['enabled'] ?? false) !== (bool) $payload['auto_share_enabled']
            || (($currentShare['label_template'] ?? null) !== $payload['share_label_template'])
            || ((int) ($currentShare['expires_in_days'] ?? 0) !== (int) ($payload['share_expires_in_days'] ?? 0))
            || ((bool) ($currentShare['allow_csv_download'] ?? false) !== (bool) $payload['share_allow_csv_download'])
        ) {
            $changes[] = 'share_delivery';
        }

        return array_values(array_unique($changes));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'already_applied' => 'Zaten Uygulaniyor',
            'upgrade_available' => 'Guncelleme Oneriliyor',
            default => 'Onerilen Profil',
        };
    }

    private function reasonText(string $status, ?string $recommendationReason): string
    {
        return match ($status) {
            'already_applied' => 'Bu entity icin managed template onerisi mevcut varsayilan teslim profiline zaten uyuyor.',
            'upgrade_available' => $recommendationReason
                ? sprintf('Managed template uygun bulundu. Mevcut profilde fark var: %s', $recommendationReason)
                : 'Managed template uygun bulundu. Mevcut profil ile onerilen profil arasinda fark var.',
            default => $recommendationReason
                ? sprintf('Managed template uygun bulundu: %s', $recommendationReason)
                : 'Bu entity icin uygulanabilir bir varsayilan teslim profili onerisi bulundu.',
        };
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
}
