<?php

namespace App\Domain\Reporting\Services;

use App\Models\ReportDeliveryRun;
use App\Models\ReportDeliverySchedule;
use Illuminate\Support\Str;

class ReportRecipientGroupSelectionService
{
    /**
     * @param  array<string, mixed>|null  $selection
     * @return array<string, mixed>|null
     */
    public function fromArray(?array $selection): ?array
    {
        return $this->normalizeSelection($selection, null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $recipientGroupSummary
     * @param  array<string, mixed>|null  $preset
     * @return array<string, mixed>|null
     */
    public function fromPayload(array $payload, array $recipientGroupSummary = [], ?array $preset = null): ?array
    {
        $explicit = is_array($payload['recipient_group_selection'] ?? null)
            ? $payload['recipient_group_selection']
            : null;

        return $this->normalizeSelection(
            $explicit,
            $this->fallbackSelection(
                recipientPresetId: isset($payload['recipient_preset_id']) && $payload['recipient_preset_id'] !== ''
                    ? (string) $payload['recipient_preset_id']
                    : null,
                recipientPresetName: $preset['name'] ?? data_get($recipientGroupSummary, 'preset_name'),
                recipients: is_array($payload['recipients'] ?? null) ? $payload['recipients'] : [],
                contactTags: is_array($payload['contact_tags'] ?? null) ? $payload['contact_tags'] : [],
                recipientGroupSummary: $recipientGroupSummary,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $recipientGroupSummary
     * @return array<string, mixed>|null
     */
    public function fromSchedule(ReportDeliverySchedule $schedule, array $recipientGroupSummary = []): ?array
    {
        $configuration = is_array($schedule->configuration ?? null) ? $schedule->configuration : [];
        $explicit = is_array($configuration['recipient_group_selection'] ?? null)
            ? $configuration['recipient_group_selection']
            : null;
        $summary = $recipientGroupSummary !== []
            ? $recipientGroupSummary
            : (is_array($configuration['recipient_group_summary'] ?? null) ? $configuration['recipient_group_summary'] : []);
        $presetId = data_get($configuration, 'recipient_preset_id');

        return $this->normalizeSelection(
            $explicit,
            $this->fallbackSelection(
                recipientPresetId: is_string($presetId) && $presetId !== '' ? $presetId : null,
                recipientPresetName: data_get($summary, 'preset_name'),
                recipients: is_array($schedule->recipients ?? null) ? $schedule->recipients : [],
                contactTags: is_array($configuration['contact_tags'] ?? null) ? $configuration['contact_tags'] : [],
                recipientGroupSummary: $summary,
            ),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromRun(ReportDeliveryRun $run, ?ReportDeliverySchedule $schedule = null): ?array
    {
        $metadata = is_array($run->metadata ?? null) ? $run->metadata : [];
        $explicit = is_array($metadata['recipient_group_selection'] ?? null)
            ? $metadata['recipient_group_selection']
            : null;
        $summary = is_array($metadata['recipient_group_summary'] ?? null)
            ? $metadata['recipient_group_summary']
            : [];
        $presetId = data_get($metadata, 'recipient_preset_id');
        $selection = $this->normalizeSelection(
            $explicit,
            $this->fallbackSelection(
                recipientPresetId: is_string($presetId) && $presetId !== '' ? $presetId : null,
                recipientPresetName: data_get($metadata, 'recipient_preset_name') ?: data_get($summary, 'preset_name'),
                recipients: is_array($run->recipients ?? null) ? $run->recipients : [],
                contactTags: is_array($metadata['contact_tags'] ?? null) ? $metadata['contact_tags'] : [],
                recipientGroupSummary: $summary,
            ),
        );

        if ($selection !== null) {
            return $selection;
        }

        return $schedule ? $this->fromSchedule($schedule) : null;
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    public function selectionKey(array $selection): string
    {
        $id = trim((string) ($selection['id'] ?? ''));

        if ($id !== '') {
            return $id;
        }

        $sourceType = trim((string) ($selection['source_type'] ?? 'manual'));
        $sourceId = trim((string) ($selection['source_id'] ?? 'custom'));

        return sprintf('%s:%s', $sourceType, $sourceId);
    }

    /**
     * @param  array<string, mixed>|null  $selected
     * @param  array<string, mixed>|null  $recommended
     * @return array<string, mixed>
     */
    public function alignment(?array $selected, ?array $recommended): array
    {
        if ($selected === null && $recommended === null) {
            return [
                'status' => 'unknown',
                'is_aligned' => null,
                'reason' => 'Secilen veya onerilen grup izi bulunmuyor.',
            ];
        }

        if ($recommended === null) {
            return [
                'status' => 'no_recommendation',
                'is_aligned' => null,
                'reason' => 'Bu karar aninda kayitli bir onerilen grup izi bulunmuyor.',
            ];
        }

        if ($selected === null) {
            return [
                'status' => 'missing_selection',
                'is_aligned' => false,
                'reason' => 'Onerilen grup kayitli ancak secilen grup izi eksik.',
            ];
        }

        $isAligned = $this->selectionKey($selected) === $this->selectionKey($recommended);

        return [
            'status' => $isAligned ? 'aligned' : 'override',
            'is_aligned' => $isAligned,
            'reason' => $isAligned
                ? 'Operator onerilen grubu kullanmis.'
                : 'Operator onerilen grup yerine farkli bir alici grubu secmis.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $explicit
     * @param  array<string, mixed>|null  $fallback
     * @return array<string, mixed>|null
     */
    private function normalizeSelection(?array $explicit, ?array $fallback): ?array
    {
        if ($explicit === null && $fallback === null) {
            return null;
        }

        $sourceType = $this->normalizeSourceType(
            $explicit['source_type'] ?? null,
            $fallback['source_type'] ?? null,
        );

        if ($sourceType === null) {
            return null;
        }

        $sourceSubtype = $this->normalizeNullableString($explicit['source_subtype'] ?? ($fallback['source_subtype'] ?? null));
        $sourceId = $this->normalizeNullableString($explicit['source_id'] ?? ($fallback['source_id'] ?? null));
        $name = $this->normalizeNullableString($explicit['name'] ?? ($fallback['name'] ?? null))
            ?? $this->defaultSelectionName(
                $sourceType,
                $sourceSubtype,
                $fallback['contact_tags'] ?? [],
                $fallback['label'] ?? null,
            );

        $id = $this->normalizeNullableString($explicit['id'] ?? ($fallback['id'] ?? null))
            ?? sprintf(
                '%s:%s',
                $sourceType,
                $sourceId !== null && $sourceId !== ''
                    ? $sourceId
                    : substr(sha1($name), 0, 16),
            );

        return [
            'id' => $id,
            'source_type' => $sourceType,
            'source_subtype' => $sourceSubtype,
            'source_id' => $sourceId,
            'name' => $name,
            'mode' => $fallback['mode'] ?? null,
            'label' => $fallback['label'] ?? $name,
            'contact_tags' => $fallback['contact_tags'] ?? [],
            'sample_recipients' => $fallback['sample_recipients'] ?? [],
            'resolved_recipients_count' => (int) ($fallback['resolved_recipients_count'] ?? 0),
        ];
    }

    /**
     * @param  array<int, mixed>  $recipients
     * @param  array<int, mixed>  $contactTags
     * @param  array<string, mixed>  $recipientGroupSummary
     * @return array<string, mixed>|null
     */
    private function fallbackSelection(
        ?string $recipientPresetId,
        ?string $recipientPresetName,
        array $recipients,
        array $contactTags,
        array $recipientGroupSummary,
    ): ?array {
        $normalizedRecipients = $this->normalizeStringList($recipients);
        $normalizedTags = $this->normalizeStringList($contactTags);
        $mode = $this->normalizeNullableString($recipientGroupSummary['mode'] ?? null)
            ?? $this->inferMode($recipientPresetId, $normalizedRecipients, $normalizedTags);

        if ($recipientPresetId === null && $normalizedRecipients === [] && $normalizedTags === [] && $mode === null) {
            return null;
        }

        $sourceType = match (true) {
            $recipientPresetId !== null => 'preset',
            $normalizedTags !== [] && $normalizedRecipients === [] => 'segment',
            $normalizedRecipients !== [] || $normalizedTags !== [] => 'manual',
            default => null,
        };

        if ($sourceType === null) {
            return null;
        }

        $sourceSubtype = match ($mode) {
            'preset_plus_manual_plus_segment',
            'preset_plus_manual',
            'preset_plus_segment',
            'manual_plus_segment' => $mode,
            default => null,
        };

        $sourceId = match ($sourceType) {
            'preset' => $recipientPresetId,
            'segment' => implode('|', $normalizedTags),
            'manual' => sprintf(
                'custom:%s',
                substr(sha1(implode('|', $normalizedRecipients).'#'.implode('|', $normalizedTags)), 0, 16),
            ),
            default => null,
        };

        return [
            'source_type' => $sourceType,
            'source_subtype' => $sourceSubtype,
            'source_id' => $sourceId,
            'name' => $this->normalizeNullableString($recipientGroupSummary['label'] ?? null)
                ?? $this->defaultSelectionName($sourceType, $sourceSubtype, $normalizedTags, $recipientPresetName),
            'mode' => $mode,
            'label' => $this->normalizeNullableString($recipientGroupSummary['label'] ?? null),
            'contact_tags' => $normalizedTags,
            'sample_recipients' => array_slice($normalizedRecipients, 0, 3),
            'resolved_recipients_count' => (int) ($recipientGroupSummary['resolved_recipients_count'] ?? count($normalizedRecipients)),
            'id' => $sourceType !== null && $sourceId !== null
                ? sprintf('%s:%s', $sourceType, $sourceId)
                : null,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function normalizeStringList(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->unique(fn (string $item): string => mb_strtolower($item))
            ->values()
            ->all();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeSourceType(mixed $sourceType, mixed $fallback): ?string
    {
        $candidate = mb_strtolower(trim((string) ($sourceType ?? $fallback ?? '')));

        return in_array($candidate, ['preset', 'segment', 'smart', 'manual'], true)
            ? $candidate
            : null;
    }

    /**
     * @param  array<int, string>  $normalizedRecipients
     * @param  array<int, string>  $normalizedTags
     */
    private function inferMode(?string $recipientPresetId, array $normalizedRecipients, array $normalizedTags): ?string
    {
        $hasPreset = $recipientPresetId !== null && trim($recipientPresetId) !== '';
        $hasRecipients = $normalizedRecipients !== [];
        $hasSegments = $normalizedTags !== [];

        return match (true) {
            $hasPreset && $hasRecipients && $hasSegments => 'preset_plus_manual_plus_segment',
            $hasPreset && $hasRecipients => 'preset_plus_manual',
            $hasPreset && $hasSegments => 'preset_plus_segment',
            $hasPreset => 'preset',
            $hasRecipients && $hasSegments => 'manual_plus_segment',
            $hasSegments => 'segment',
            $hasRecipients => 'manual',
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $contactTags
     */
    private function defaultSelectionName(
        string $sourceType,
        ?string $sourceSubtype,
        array $contactTags,
        ?string $fallbackLabel,
    ): string {
        if ($fallbackLabel !== null && trim($fallbackLabel) !== '') {
            return trim($fallbackLabel);
        }

        return match ($sourceType) {
            'preset' => 'Kayitli alici grubu',
            'segment' => sprintf('Segment: %s', implode(', ', $contactTags)),
            'smart' => match ($sourceSubtype) {
                'company' => 'Sirket akilli grubu',
                'primary' => 'Primary akilli grup',
                default => 'Akilli alici grubu',
            },
            'manual' => $sourceSubtype === 'manual_plus_segment'
                ? sprintf('Manuel alici + segment (%s)', implode(', ', $contactTags))
                : 'Manuel alici listesi',
            default => 'Alici grubu',
        };
    }
}
