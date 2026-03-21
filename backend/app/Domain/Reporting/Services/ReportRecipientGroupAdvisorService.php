<?php

namespace App\Domain\Reporting\Services;

use App\Support\Operations\EntityContextResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportRecipientGroupAdvisorService
{
    public function __construct(
        private readonly ReportContactService $reportContactService,
        private readonly ReportRecipientPresetService $recipientPresetService,
        private readonly EntityContextResolver $entityContextResolver,
    ) {
    }

    /**
     * @return array{
     *   summary: array<string, int>,
     *   items: array<int, array<string, mixed>>
     * }
     */
    public function catalog(string $workspaceId): array
    {
        $contactIndex = $this->reportContactService->index($workspaceId);
        $segments = collect($contactIndex['segments'] ?? []);
        $contacts = collect($contactIndex['items'] ?? []);
        $presets = collect($this->recipientPresetService->index($workspaceId)['items'])
            ->where('is_active', true)
            ->values();

        $items = collect()
            ->merge($presets->map(fn (array $preset): array => $this->presetCandidate($workspaceId, $preset)))
            ->merge($segments->map(fn (array $segment): array => $this->segmentCandidate($workspaceId, $segment)))
            ->merge($this->companyCandidates($workspaceId, $contacts))
            ->when(
                $contacts->where('is_primary', true)->where('is_active', true)->isNotEmpty(),
                fn (Collection $collection): Collection => $collection->push(
                    $this->primaryContactsCandidate($workspaceId, $contacts->all()),
                ),
            )
            ->sort(fn (array $left, array $right): int => $this->compareCatalogItems($left, $right))
            ->values();

        return [
            'summary' => [
                'total_groups' => $items->count(),
                'preset_groups' => $items->where('source_type', 'preset')->count(),
                'segment_groups' => $items->where('source_type', 'segment')->count(),
                'smart_groups' => $items->where('source_type', 'smart')->count(),
            ],
            'items' => $items->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $currentProfile
     * @return array<int, array<string, mixed>>
     */
    public function suggestForEntity(
        string $workspaceId,
        string $entityType,
        string $entityId,
        ?array $currentProfile = null,
        int $limit = 4,
    ): array {
        $catalog = collect($this->catalog($workspaceId)['items']);

        if ($catalog->isEmpty()) {
            return [];
        }

        $context = $this->entityContextResolver
            ->resolveMany($workspaceId, [[
                'type' => $entityType,
                'id' => $entityId,
            ]]);

        $resolvedContext = $context[$this->entityContextResolver->key($entityType, $entityId)] ?? [
            'entity_label' => null,
            'context_label' => null,
        ];

        $entityTokens = $this->extractTokens([
            $resolvedContext['entity_label'] ?? null,
            $resolvedContext['context_label'] ?? null,
        ]);

        $ranked = $catalog
            ->map(function (array $candidate) use ($entityTokens, $currentProfile): array {
                $meta = $this->recommendationMeta($candidate, $entityTokens, $currentProfile);

                return array_merge($candidate, $meta);
            })
            ->sort(fn (array $left, array $right): int => $this->compareSuggestedItems($left, $right))
            ->values()
            ->take($limit)
            ->values();

        return $ranked
            ->map(function (array $candidate, int $index): array {
                $candidate['recommendation_label'] = match (true) {
                    $index === 0 => 'En Uygun',
                    $candidate['score'] >= 75 => 'Guclu Eslesme',
                    $candidate['score'] >= 55 => 'Iyi Alternatif',
                    default => 'Alternatif',
                };

                return $candidate;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $preset
     * @return array<string, mixed>
     */
    private function presetCandidate(string $workspaceId, array $preset): array
    {
        $recipientGroup = $this->recipientPresetService->resolveRecipientGroup(
            workspaceId: $workspaceId,
            preset: $preset,
            manualRecipients: [],
            manualContactTags: [],
        );

        return [
            'id' => sprintf('preset:%s', $preset['id']),
            'source_type' => 'preset',
            'source_subtype' => null,
            'source_id' => $preset['id'],
            'name' => $preset['name'],
            'description' => $preset['notes'] ?: 'Kayitli alici grubu',
            'recipient_preset_id' => $preset['id'],
            'recipients' => [],
            'recipients_count' => 0,
            'contact_tags' => $preset['contact_tags'] ?? [],
            'tagged_contacts' => $recipientGroup['tagged_contacts'],
            'tagged_contacts_count' => count($recipientGroup['tagged_contacts']),
            'resolved_recipients' => $recipientGroup['resolved_recipients'],
            'resolved_recipients_count' => count($recipientGroup['resolved_recipients']),
            'recipient_group_summary' => $recipientGroup['recipient_group_summary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return array<string, mixed>
     */
    private function segmentCandidate(string $workspaceId, array $segment): array
    {
        $recipientGroup = $this->recipientPresetService->resolveRecipientGroup(
            workspaceId: $workspaceId,
            preset: null,
            manualRecipients: [],
            manualContactTags: [$segment['tag']],
        );

        return [
            'id' => sprintf('segment:%s', Str::slug((string) $segment['tag'])),
            'source_type' => 'segment',
            'source_subtype' => null,
            'source_id' => (string) $segment['tag'],
            'name' => sprintf('%s Segmenti', $segment['tag']),
            'description' => sprintf(
                '%d aktif kisi / %d primary kisi',
                (int) ($segment['active_contacts_count'] ?? 0),
                (int) ($segment['primary_contacts_count'] ?? 0),
            ),
            'recipient_preset_id' => null,
            'recipients' => [],
            'recipients_count' => 0,
            'contact_tags' => [(string) $segment['tag']],
            'tagged_contacts' => $recipientGroup['tagged_contacts'],
            'tagged_contacts_count' => count($recipientGroup['tagged_contacts']),
            'resolved_recipients' => $recipientGroup['resolved_recipients'],
            'resolved_recipients_count' => count($recipientGroup['resolved_recipients']),
            'recipient_group_summary' => $recipientGroup['recipient_group_summary'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<string, mixed>
     */
    private function primaryContactsCandidate(string $workspaceId, array $contacts): array
    {
        $primaryContacts = collect($contacts)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->values();

        $emails = $this->reportContactService->extractEmails($primaryContacts->all());
        $recipientGroup = $this->recipientPresetService->resolveRecipientGroup(
            workspaceId: $workspaceId,
            preset: null,
            manualRecipients: $emails,
            manualContactTags: [],
        );

        return [
            'id' => 'smart:primary_contacts',
            'source_type' => 'smart',
            'source_subtype' => 'primary',
            'source_id' => 'primary_contacts',
            'name' => 'Primary Musteri Kisileri',
            'description' => sprintf('%d primary kisi otomatik olarak secildi.', $primaryContacts->count()),
            'recipient_preset_id' => null,
            'recipients' => $emails,
            'recipients_count' => count($emails),
            'contact_tags' => [],
            'tagged_contacts' => $recipientGroup['tagged_contacts'],
            'tagged_contacts_count' => count($recipientGroup['tagged_contacts']),
            'resolved_recipients' => $recipientGroup['resolved_recipients'],
            'resolved_recipients_count' => count($recipientGroup['resolved_recipients']),
            'recipient_group_summary' => $recipientGroup['recipient_group_summary'],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $contacts
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function companyCandidates(string $workspaceId, Collection $contacts): Collection
    {
        return $contacts
            ->where('is_active', true)
            ->filter(fn (array $contact): bool => filled($contact['company_name'] ?? null))
            ->groupBy(fn (array $contact): string => mb_strtolower(trim((string) $contact['company_name'])))
            ->map(function (Collection $group): array {
                $contacts = $group
                    ->sortBy([
                        ['is_primary', 'desc'],
                        ['name', 'asc'],
                    ])
                    ->values();

                return [
                    'company_name' => trim((string) ($contacts->first()['company_name'] ?? '')),
                    'contacts' => $contacts->all(),
                ];
            })
            ->filter(fn (array $group): bool => $group['company_name'] !== '')
            ->sort(fn (array $left, array $right): int => $this->compareCompanyCandidateGroups($left, $right))
            ->map(fn (array $group): array => $this->companyCandidate(
                workspaceId: $workspaceId,
                companyName: $group['company_name'],
                contacts: $group['contacts'],
            ))
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<string, mixed>
     */
    private function companyCandidate(string $workspaceId, string $companyName, array $contacts): array
    {
        $companyContacts = collect($contacts)
            ->where('is_active', true)
            ->values();

        $emails = $this->reportContactService->extractEmails($companyContacts->all());
        $recipientGroup = $this->recipientPresetService->resolveRecipientGroup(
            workspaceId: $workspaceId,
            preset: null,
            manualRecipients: $emails,
            manualContactTags: [],
        );

        return [
            'id' => sprintf('smart:company:%s', Str::slug($companyName)),
            'source_type' => 'smart',
            'source_subtype' => 'company',
            'source_id' => sprintf('company:%s', Str::slug($companyName)),
            'name' => sprintf('%s Musteri Grubu', $companyName),
            'description' => sprintf(
                '%d aktif kisi / %d primary kisi',
                $companyContacts->count(),
                $companyContacts->where('is_primary', true)->count(),
            ),
            'recipient_preset_id' => null,
            'recipients' => $emails,
            'recipients_count' => count($emails),
            'contact_tags' => [],
            'tagged_contacts' => $recipientGroup['tagged_contacts'],
            'tagged_contacts_count' => count($recipientGroup['tagged_contacts']),
            'resolved_recipients' => $recipientGroup['resolved_recipients'],
            'resolved_recipients_count' => count($recipientGroup['resolved_recipients']),
            'recipient_group_summary' => $recipientGroup['recipient_group_summary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<int, string>  $entityTokens
     * @param  array<string, mixed>|null  $currentProfile
     * @return array{score: int, recommendation_reason: string}
     */
    private function recommendationMeta(array $candidate, array $entityTokens, ?array $currentProfile = null): array
    {
        $score = match ($candidate['source_type']) {
            'preset' => 50,
            'segment' => 40,
            'smart' => 30,
            default => 20,
        };

        $reasons = [];
        $matchedTokens = $this->matchedTokens($candidate, $entityTokens);

        if ($currentProfile !== null) {
            if (
                filled($candidate['recipient_preset_id'] ?? null)
                && ($candidate['recipient_preset_id'] ?? null) === ($currentProfile['recipient_preset_id'] ?? null)
            ) {
                $score += 60;
                $reasons[] = 'Mevcut varsayilan profille ayni kayitli grup.';
            }

            $currentTags = collect($currentProfile['contact_tags'] ?? [])
                ->map(fn (mixed $tag): string => mb_strtolower(trim((string) $tag)))
                ->filter()
                ->all();

            $candidateTags = collect($candidate['contact_tags'] ?? [])
                ->map(fn (mixed $tag): string => mb_strtolower(trim((string) $tag)))
                ->filter()
                ->all();

            $overlap = array_values(array_intersect($candidateTags, $currentTags));

            if ($overlap !== []) {
                $score += min(30, count($overlap) * 10);
                $reasons[] = 'Mevcut profilde kullanilan etiketlerle ortusuyor.';
            }
        }

        if ($matchedTokens !== []) {
            $score += min(24, count($matchedTokens) * 8);
            $reasons[] = 'Hesap veya kampanya baglamiyla isim/etiket eslesmesi var.';
        }

        if ($candidate['source_type'] === 'smart' && ($candidate['source_id'] ?? null) === 'primary_contacts') {
            $score += 8;
            $reasons[] = 'Primary musteri kisilerini iceriyor.';
        }

        if (($candidate['source_subtype'] ?? null) === 'company' && $matchedTokens !== []) {
            $score += 12;
            $reasons[] = 'Sirket/marka eslesmesine gore akilli grup onerildi.';
        }

        if ((int) ($candidate['recipient_group_summary']['dynamic_contacts_count'] ?? 0) > 0) {
            $score += min(10, (int) ($candidate['recipient_group_summary']['dynamic_contacts_count'] ?? 0));
        }

        if ($reasons === []) {
            $reasons[] = 'Workspace icindeki aktif teslim gruplarindan onerildi.';
        }

        return [
            'score' => $score,
            'recommendation_reason' => implode(' ', array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<int, string>  $entityTokens
     * @return array<int, string>
     */
    private function matchedTokens(array $candidate, array $entityTokens): array
    {
        if ($entityTokens === []) {
            return [];
        }

        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($candidate['name'] ?? ''),
            (string) ($candidate['description'] ?? ''),
            implode(' ', $candidate['contact_tags'] ?? []),
            implode(' ', $candidate['recipient_group_summary']['sample_contact_names'] ?? []),
        ])));

        return collect($entityTokens)
            ->filter(fn (string $token): bool => $token !== '' && str_contains($haystack, $token))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string|null>  $values
     * @return array<int, string>
     */
    private function extractTokens(array $values): array
    {
        return collect($values)
            ->filter()
            ->flatMap(function (string $value): array {
                $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value));

                return preg_split('/\s+/u', trim((string) $normalized)) ?: [];
            })
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    private function catalogSourcePriority(string $sourceType): int
    {
        return match ($sourceType) {
            'preset' => 1,
            'segment' => 2,
            'smart' => 3,
            default => 9,
        };
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareCatalogItems(array $left, array $right): int
    {
        $sourceComparison = $this->catalogSourcePriority((string) $left['source_type'])
            <=> $this->catalogSourcePriority((string) $right['source_type']);

        if ($sourceComparison !== 0) {
            return $sourceComparison;
        }

        $recipientComparison = ((int) $right['resolved_recipients_count']) <=> ((int) $left['resolved_recipients_count']);

        if ($recipientComparison !== 0) {
            return $recipientComparison;
        }

        return strcmp((string) $left['name'], (string) $right['name']);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareSuggestedItems(array $left, array $right): int
    {
        $scoreComparison = ((int) $right['score']) <=> ((int) $left['score']);

        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        return $this->compareCatalogItems($left, $right);
    }

    /**
     * @param  array{company_name: string, contacts: array<int, array<string, mixed>>}  $left
     * @param  array{company_name: string, contacts: array<int, array<string, mixed>>}  $right
     */
    private function compareCompanyCandidateGroups(array $left, array $right): int
    {
        $contactCountComparison = count($right['contacts']) <=> count($left['contacts']);

        if ($contactCountComparison !== 0) {
            return $contactCountComparison;
        }

        return strcmp($left['company_name'], $right['company_name']);
    }
}
