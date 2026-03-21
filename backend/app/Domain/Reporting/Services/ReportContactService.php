<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Services\AuditLogService;
use App\Models\ReportContact;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReportContactService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array{summary: array<string, int>, items: array<int, array<string, mixed>>}
     */
    public function index(string $workspaceId): array
    {
        $items = ReportContact::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('is_active')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->map(fn (ReportContact $contact): array => $this->toPayload($contact))
            ->values();

        return [
            'summary' => [
                'total_contacts' => $items->count(),
                'active_contacts' => $items->where('is_active', true)->count(),
                'primary_contacts' => $items->where('is_primary', true)->count(),
                'tagged_contacts' => $items->filter(fn (array $item): bool => count($item['tags']) > 0)->count(),
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
        $this->assertUniqueEmail($workspace->id, (string) $payload['email']);

        $contact = ReportContact::query()->create([
            'workspace_id' => $workspace->id,
            'name' => trim((string) $payload['name']),
            'email' => mb_strtolower(trim((string) $payload['email'])),
            'company_name' => $this->nullableTrimmed($payload['company_name'] ?? null),
            'role_label' => $this->nullableTrimmed($payload['role_label'] ?? null),
            'tags' => $this->normalizeTags($payload['tags'] ?? []),
            'notes' => $this->nullableTrimmed($payload['notes'] ?? null),
            'is_primary' => (bool) ($payload['is_primary'] ?? false),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_contact_created',
            targetType: 'report_contact',
            targetId: $contact->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'email' => $contact->email,
                'company_name' => $contact->company_name,
                'is_primary' => $contact->is_primary,
            ],
            request: $request,
        );

        return $this->toPayload($contact);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(
        Workspace $workspace,
        string $contactId,
        array $payload,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $contact = $this->findModel($workspace->id, $contactId);
        $this->assertUniqueEmail($workspace->id, (string) $payload['email'], $contact->id);

        $contact->fill([
            'name' => trim((string) $payload['name']),
            'email' => mb_strtolower(trim((string) $payload['email'])),
            'company_name' => $this->nullableTrimmed($payload['company_name'] ?? null),
            'role_label' => $this->nullableTrimmed($payload['role_label'] ?? null),
            'tags' => $this->normalizeTags($payload['tags'] ?? []),
            'notes' => $this->nullableTrimmed($payload['notes'] ?? null),
            'is_primary' => (bool) ($payload['is_primary'] ?? false),
            'is_active' => (bool) ($payload['is_active'] ?? $contact->is_active),
            'updated_by' => $actor?->id,
        ]);
        $contact->save();

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_contact_updated',
            targetType: 'report_contact',
            targetId: $contact->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'email' => $contact->email,
                'company_name' => $contact->company_name,
                'is_primary' => $contact->is_primary,
                'is_active' => $contact->is_active,
            ],
            request: $request,
        );

        return $this->toPayload($contact);
    }

    /**
     * @return array<string, mixed>
     */
    public function toggle(
        Workspace $workspace,
        string $contactId,
        ?bool $isActive,
        ?User $actor = null,
        ?Request $request = null,
    ): array {
        $contact = $this->findModel($workspace->id, $contactId);
        $contact->is_active = $isActive ?? ! $contact->is_active;
        $contact->updated_by = $actor?->id;
        $contact->save();

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_contact_toggled',
            targetType: 'report_contact',
            targetId: $contact->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'email' => $contact->email,
                'is_active' => $contact->is_active,
            ],
            request: $request,
        );

        return $this->toPayload($contact);
    }

    public function delete(
        Workspace $workspace,
        string $contactId,
        ?User $actor = null,
        ?Request $request = null,
    ): void {
        $contact = $this->findModel($workspace->id, $contactId);
        $email = $contact->email;
        $contact->delete();

        $this->auditLogService->log(
            actor: $actor,
            action: 'report_contact_deleted',
            targetType: 'report_contact',
            targetId: $contactId,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            metadata: [
                'email' => $email,
            ],
            request: $request,
        );
    }

    /**
     * @param  array<int, string>  $contactIds
     * @return array<int, array<string, mixed>>
     */
    public function findMany(string $workspaceId, array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        return ReportContact::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $contactIds)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->map(fn (ReportContact $contact): array => $this->toPayload($contact))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function extractEmails(array $contacts): array
    {
        return collect($contacts)
            ->map(fn (array $contact): string => mb_strtolower(trim((string) ($contact['email'] ?? ''))))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(ReportContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'company_name' => $contact->company_name,
            'role_label' => $contact->role_label,
            'tags' => $this->normalizeTags($contact->tags ?? []),
            'notes' => $contact->notes,
            'is_primary' => $contact->is_primary,
            'is_active' => $contact->is_active,
            'last_used_at' => $contact->last_used_at?->toDateTimeString(),
            'created_at' => $contact->created_at?->toDateTimeString(),
            'updated_at' => $contact->updated_at?->toDateTimeString(),
        ];
    }

    private function findModel(string $workspaceId, string $contactId): ReportContact
    {
        $contact = ReportContact::query()
            ->where('workspace_id', $workspaceId)
            ->find($contactId);

        if (! $contact) {
            throw ValidationException::withMessages([
                'contact_id' => 'Islem yapilacak kisi bulunamadi.',
            ]);
        }

        return $contact;
    }

    private function assertUniqueEmail(string $workspaceId, string $email, ?string $ignoreId = null): void
    {
        $normalized = mb_strtolower(trim($email));
        $exists = ReportContact::query()
            ->where('workspace_id', $workspaceId)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get()
            ->contains(fn (ReportContact $contact): bool => mb_strtolower($contact->email) === $normalized);

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => 'Bu e-posta adresi kisi havuzunda zaten kayitli.',
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    private function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->values()
            ->all();
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
