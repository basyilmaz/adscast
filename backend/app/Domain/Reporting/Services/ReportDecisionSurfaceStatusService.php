<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportDecisionSurfaceStatusService
{
    private const SETTING_KEY = 'reports.decision_surface_statuses';

    /**
     * @var array<string, string>
     */
    private const SURFACE_LABELS = [
        'featured_fix' => 'Hizli Duzeltme',
        'retry' => 'Retry Rehberi',
        'profile' => 'Profil Onerisi',
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Beklemede',
        'reviewed' => 'Gozden Gecirildi',
        'completed' => 'Tamamlandi',
        'deferred' => 'Ertelendi',
    ];

    /**
     * @var array<string, string>
     */
    private const DEFER_REASON_LABELS = [
        'waiting_client_feedback' => 'Musteri Donusu Bekleniyor',
        'waiting_data_validation' => 'Veri Dogrulamasi Bekleniyor',
        'scheduled_followup' => 'Planli Takip Bekleniyor',
        'blocked_external_dependency' => 'Dis Bagimlilik Engeli',
        'priority_window_shifted' => 'Oncelik Penceresi Degisti',
    ];

    public function __construct(
        private readonly ReportWorkspaceConfigStore $configStore,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array{summary: array<string, int>, items: array<int, array<string, mixed>>}
     */
    public function forEntity(string $workspaceId, string $entityType, string $entityId): array
    {
        $storedItems = $this->configStore
            ->collection($workspaceId, self::SETTING_KEY)
            ->filter(function (array $item) use ($entityType, $entityId): bool {
                return (string) ($item['entity_type'] ?? '') === $entityType
                    && (string) ($item['entity_id'] ?? '') === $entityId;
            })
            ->map(fn (array $item): array => $this->normalizeItem($item))
            ->keyBy('surface_key');

        $items = collect(array_keys(self::SURFACE_LABELS))
            ->map(fn (string $surfaceKey): array => $storedItems->get(
                $surfaceKey,
                $this->defaultItem($entityType, $entityId, $surfaceKey),
            ))
            ->values();

        return [
            'summary' => $this->summary($items),
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function upsert(
        Workspace $workspace,
        string $entityType,
        string $entityId,
        string $surfaceKey,
        string $status,
        array $attributes = [],
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $items = $this->configStore->collection($workspace->id, self::SETTING_KEY);
        $index = $this->findIndex($items, $entityType, $entityId, $surfaceKey);
        $now = now()->toDateTimeString();
        $existing = $index === false ? [] : (array) $items->get($index);
        $operatorNote = array_key_exists('operator_note', $attributes)
            ? $this->normalizeOptionalText($attributes['operator_note'] ?? null)
            : $this->normalizeOptionalText($existing['operator_note'] ?? null);
        $deferReasonCode = $this->resolveDeferReasonCode($status, $attributes, $existing);

        $raw = [
            'id' => $index === false
                ? (string) Str::uuid()
                : (string) ($items->get($index)['id'] ?? Str::uuid()->toString()),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'surface_key' => $surfaceKey,
            'status' => $status,
            'created_at' => $index === false
                ? $now
                : (string) ($items->get($index)['created_at'] ?? $now),
            'updated_at' => $now,
            'updated_by_user_id' => $actor?->id,
            'updated_by_name' => $actor?->name,
            'operator_note' => $operatorNote,
            'defer_reason_code' => $deferReasonCode,
        ];

        if ($index === false) {
            $items->push($raw);
        } else {
            $items->put($index, $raw);
        }

        $normalized = $this->normalizeItem($raw);
        $this->configStore->put($workspace, self::SETTING_KEY, $items->all(), $actor);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_decision_surface_status_upserted',
            targetType: 'report_decision_surface_status',
            targetId: $normalized['id'],
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'surface_key' => $surfaceKey,
                'status' => $status,
                'operator_note' => $operatorNote,
                'defer_reason_code' => $deferReasonCode,
            ],
            request: $request,
        );

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    public static function validSurfaceKeys(): array
    {
        return array_keys(self::SURFACE_LABELS);
    }

    /**
     * @return array<int, string>
     */
    public static function validStatuses(): array
    {
        return array_keys(self::STATUS_LABELS);
    }

    /**
     * @return array<int, string>
     */
    public static function validDeferReasonCodes(): array
    {
        return array_keys(self::DEFER_REASON_LABELS);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function findIndex(Collection $items, string $entityType, string $entityId, string $surfaceKey): int|false
    {
        return $items->search(function (array $item) use ($entityType, $entityId, $surfaceKey): bool {
            return (string) ($item['entity_type'] ?? '') === $entityType
                && (string) ($item['entity_id'] ?? '') === $entityId
                && (string) ($item['surface_key'] ?? '') === $surfaceKey;
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        $surfaceKey = (string) ($item['surface_key'] ?? '');
        $status = (string) ($item['status'] ?? 'pending');
        $deferReasonCode = $this->normalizeDeferReasonCode($item['defer_reason_code'] ?? null);

        if (! array_key_exists($surfaceKey, self::SURFACE_LABELS)) {
            throw ValidationException::withMessages([
                'surface_key' => 'Gecersiz report decision surface anahtari.',
            ]);
        }

        if (! array_key_exists($status, self::STATUS_LABELS)) {
            throw ValidationException::withMessages([
                'status' => 'Gecersiz report decision surface durumu.',
            ]);
        }

        return [
            'id' => (string) ($item['id'] ?? Str::uuid()->toString()),
            'entity_type' => (string) ($item['entity_type'] ?? ''),
            'entity_id' => (string) ($item['entity_id'] ?? ''),
            'surface_key' => $surfaceKey,
            'surface_label' => self::SURFACE_LABELS[$surfaceKey],
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'is_default' => false,
            'created_at' => isset($item['created_at']) ? (string) $item['created_at'] : null,
            'updated_at' => isset($item['updated_at']) ? (string) $item['updated_at'] : null,
            'updated_by_user_id' => isset($item['updated_by_user_id']) && $item['updated_by_user_id'] !== ''
                ? (string) $item['updated_by_user_id']
                : null,
            'updated_by_name' => isset($item['updated_by_name']) && trim((string) $item['updated_by_name']) !== ''
                ? trim((string) $item['updated_by_name'])
                : null,
            'operator_note' => $this->normalizeOptionalText($item['operator_note'] ?? null),
            'defer_reason_code' => $deferReasonCode,
            'defer_reason_label' => self::DEFER_REASON_LABELS[$deferReasonCode] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultItem(string $entityType, string $entityId, string $surfaceKey): array
    {
        return [
            'id' => sprintf('%s:%s:%s', $entityType, $entityId, $surfaceKey),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'surface_key' => $surfaceKey,
            'surface_label' => self::SURFACE_LABELS[$surfaceKey],
            'status' => 'pending',
            'status_label' => self::STATUS_LABELS['pending'],
            'is_default' => true,
            'created_at' => null,
            'updated_at' => null,
            'updated_by_user_id' => null,
            'updated_by_name' => null,
            'operator_note' => null,
            'defer_reason_code' => null,
            'defer_reason_label' => null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function summary(Collection $items): array
    {
        return [
            'total_surfaces' => $items->count(),
            'pending_surfaces' => $items->where('status', 'pending')->count(),
            'reviewed_surfaces' => $items->where('status', 'reviewed')->count(),
            'completed_surfaces' => $items->where('status', 'completed')->count(),
            'deferred_surfaces' => $items->where('status', 'deferred')->count(),
            'tracked_surfaces' => $items->where('status', '!=', 'pending')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $existing
     */
    private function resolveDeferReasonCode(string $status, array $attributes, array $existing): ?string
    {
        if ($status !== 'deferred') {
            return null;
        }

        if (array_key_exists('defer_reason_code', $attributes)) {
            return $this->normalizeDeferReasonCode($attributes['defer_reason_code'] ?? null);
        }

        return $this->normalizeDeferReasonCode($existing['defer_reason_code'] ?? null);
    }

    private function normalizeOptionalText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDeferReasonCode(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '' || ! array_key_exists($normalized, self::DEFER_REASON_LABELS)) {
            return null;
        }

        return $normalized;
    }
}
