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
            ->map(fn (array $item): array => $this->normalizeItem($item))
            ->sortBy([
                ['is_active', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        return [
            'summary' => [
                'total_presets' => $items->count(),
                'active_presets' => $items->where('is_active', true)->count(),
                'total_recipients' => $items->sum(fn (array $item): int => $item['recipients_count']),
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

        $nameExists = $items->contains(function (array $item) use ($name): bool {
            return mb_strtolower((string) ($item['name'] ?? '')) === mb_strtolower($name);
        });

        if ($nameExists) {
            throw ValidationException::withMessages([
                'name' => 'Bu isimle kayitli bir alici listesi zaten var.',
            ]);
        }

        $item = $this->normalizeItem([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'recipients' => $this->normalizeRecipients($payload['recipients'] ?? []),
            'notes' => isset($payload['notes']) ? trim((string) $payload['notes']) : null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $items->push($item);

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
            ],
            request: $request,
        );

        return $item;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $workspaceId, string $presetId): ?array
    {
        $item = $this->configStore
            ->collection($workspaceId, self::SETTING_KEY)
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $presetId);

        return $item ? $this->normalizeItem($item) : null;
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
    private function normalizeItem(array $item): array
    {
        $recipients = $this->normalizeRecipients(is_array($item['recipients'] ?? null) ? $item['recipients'] : []);

        return [
            'id' => (string) ($item['id'] ?? Str::uuid()->toString()),
            'name' => trim((string) ($item['name'] ?? 'Alici Listesi')),
            'recipients' => $recipients,
            'recipients_count' => count($recipients),
            'notes' => isset($item['notes']) && trim((string) $item['notes']) !== ''
                ? trim((string) $item['notes'])
                : null,
            'is_active' => (bool) ($item['is_active'] ?? true),
            'created_at' => isset($item['created_at']) ? (string) $item['created_at'] : null,
            'updated_at' => isset($item['updated_at']) ? (string) $item['updated_at'] : null,
        ];
    }
}
