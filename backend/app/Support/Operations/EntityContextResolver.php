<?php

namespace App\Support\Operations;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class EntityContextResolver
{
    /**
     * @param  array<int, array{type: string|null, id: string|null}>  $references
     * @return array<string, array<string, string|null>>
     */
    public function resolveMany(string $workspaceId, array $references): array
    {
        $normalizedReferences = collect($references)
            ->map(function (array $reference): array {
                return [
                    'type' => $this->normalizeType($reference['type'] ?? null),
                    'id' => $reference['id'] ?? null,
                ];
            })
            ->filter(fn (array $reference): bool => $reference['type'] !== null && $reference['id'] !== null)
            ->values();

        if ($normalizedReferences->isEmpty()) {
            return [];
        }

        $workspace = Workspace::query()
            ->where('id', $workspaceId)
            ->first(['id', 'name', 'slug']);

        $campaigns = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $this->idsForType($normalizedReferences, 'campaign'))
            ->with(['adAccount:id,name,account_id'])
            ->get(['id', 'meta_ad_account_id', 'name'])
            ->keyBy('id');

        $adSets = AdSet::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $this->idsForType($normalizedReferences, 'ad_set'))
            ->with(['campaign.adAccount:id,name,account_id'])
            ->get(['id', 'campaign_id', 'name'])
            ->keyBy('id');

        $ads = Ad::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $this->idsForType($normalizedReferences, 'ad'))
            ->with(['adSet.campaign.adAccount:id,name,account_id', 'campaign.adAccount:id,name,account_id'])
            ->get(['id', 'campaign_id', 'ad_set_id', 'name'])
            ->keyBy('id');

        $accounts = MetaAdAccount::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $this->idsForType($normalizedReferences, 'account'))
            ->get(['id', 'account_id', 'name'])
            ->keyBy('id');

        $contexts = [];

        foreach ($normalizedReferences as $reference) {
            $key = $this->key($reference['type'], $reference['id']);

            if (isset($contexts[$key])) {
                continue;
            }

            $contexts[$key] = match ($reference['type']) {
                'workspace' => [
                    'entity_type' => 'workspace',
                    'entity_label' => $workspace?->name ?? 'Workspace',
                    'context_label' => $workspace?->slug,
                    'route' => '/dashboard',
                ],
                'account' => $this->accountContext($accounts->get($reference['id'])),
                'campaign' => $this->campaignContext($campaigns->get($reference['id'])),
                'ad_set' => $this->adSetContext($adSets->get($reference['id'])),
                'ad' => $this->adContext($ads->get($reference['id'])),
                default => $this->fallbackContext($reference['type']),
            };
        }

        return $contexts;
    }

    public function key(?string $type, ?string $id): string
    {
        return sprintf('%s:%s', $this->normalizeType($type) ?? 'unknown', $id ?? 'unknown');
    }

    private function normalizeType(?string $type): ?string
    {
        return match ($type) {
            'meta_ad_account', 'ad_account', 'account' => 'account',
            'adset', 'ad_set' => 'ad_set',
            'campaign', 'ad', 'workspace' => $type,
            default => $type !== null ? str_replace('-', '_', $type) : null,
        };
    }

    /**
     * @param  Collection<int, array{type: string|null, id: string|null}>  $references
     * @return array<int, string>
     */
    private function idsForType(Collection $references, string $type): array
    {
        return $references
            ->where('type', $type)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function fallbackContext(?string $type): array
    {
        return [
            'entity_type' => $this->normalizeType($type) ?? 'unknown',
            'entity_label' => 'Bilinmeyen varlik',
            'context_label' => null,
            'route' => null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function accountContext(?MetaAdAccount $account): array
    {
        if (! $account) {
            return $this->fallbackContext('account');
        }

        return [
            'entity_type' => 'account',
            'entity_label' => $account->name,
            'context_label' => $account->account_id,
            'route' => sprintf('/ad-accounts/detail?id=%s', $account->id),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function campaignContext(?Campaign $campaign): array
    {
        if (! $campaign) {
            return $this->fallbackContext('campaign');
        }

        return [
            'entity_type' => 'campaign',
            'entity_label' => $campaign->name,
            'context_label' => $campaign->adAccount?->name,
            'route' => sprintf('/campaigns/detail?id=%s', $campaign->id),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function adSetContext(?AdSet $adSet): array
    {
        if (! $adSet) {
            return $this->fallbackContext('ad_set');
        }

        return [
            'entity_type' => 'ad_set',
            'entity_label' => $adSet->name,
            'context_label' => $adSet->campaign?->name,
            'route' => sprintf('/ad-sets/detail?id=%s', $adSet->id),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function adContext(?Ad $ad): array
    {
        if (! $ad) {
            return $this->fallbackContext('ad');
        }

        return [
            'entity_type' => 'ad',
            'entity_label' => $ad->name,
            'context_label' => $ad->adSet?->name ?? $ad->campaign?->name,
            'route' => sprintf('/ads/detail?id=%s', $ad->id),
        ];
    }
}
