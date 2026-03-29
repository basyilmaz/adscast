<?php

namespace App\Domain\Meta\Adapters;

use App\Domain\Meta\Contracts\MetaApiAdapter;
use App\Domain\Meta\Services\MetaGraphClient;
use App\Models\CampaignDraft;
use App\Models\MetaConnection;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use RuntimeException;

class MetaGraphV20Adapter implements MetaApiAdapter
{
    public function __construct(
        private readonly MetaGraphClient $graphClient,
    ) {
    }

    public function listAdAccounts(MetaConnection $connection): array
    {
        if ($this->shouldUseStubMode()) {
            return $this->stubAdAccounts();
        }

        $response = $this->graphClient->getPaginatedList(
            apiVersion: 'v20.0',
            path: 'me/adaccounts',
            accessToken: $this->resolveAccessToken($connection),
            query: [
                'fields' => 'id,account_id,name,currency,timezone_name,account_status,business{id,name,verification_status}',
                'limit' => 100,
            ],
        );

        return array_map(function (array $item) use ($response): array {
            $status = $this->mapAccountStatus($item['account_status'] ?? null);
            $business = Arr::get($item, 'business', []);

            return [
                'account_id' => (string) ($item['id'] ?? $item['account_id']),
                'name' => (string) ($item['name'] ?? 'Unknown account'),
                'currency' => $item['currency'] ?? null,
                'timezone_name' => $item['timezone_name'] ?? null,
                'status' => $status,
                'is_active' => $status === 'active',
                'business' => [
                    'business_id' => Arr::get($business, 'id'),
                    'name' => Arr::get($business, 'name'),
                    'verification_status' => Arr::get($business, 'verification_status'),
                ],
                'metadata' => [
                    'source' => 'live',
                    'raw_account_id' => $item['account_id'] ?? null,
                    'api_usage' => $response['usage'],
                ],
            ];
        }, $response['data']);
    }

    public function listPages(MetaConnection $connection): array
    {
        if ($this->shouldUseStubMode()) {
            return $this->stubPages();
        }

        $response = $this->graphClient->getPaginatedList(
            apiVersion: 'v20.0',
            path: 'me/accounts',
            accessToken: $this->resolveAccessToken($connection),
            query: [
                'fields' => 'id,name,category,access_token',
                'limit' => 100,
            ],
        );

        return array_map(fn (array $item): array => [
            'page_id' => (string) $item['id'],
            'name' => (string) ($item['name'] ?? 'Unknown page'),
            'category' => $item['category'] ?? null,
            'page_access_token' => $item['access_token'] ?? null,
            'metadata' => [
                'source' => 'live',
                'api_usage' => $response['usage'],
            ],
        ], $response['data']);
    }

    public function listPixels(MetaConnection $connection): array
    {
        if ($this->shouldUseStubMode()) {
            return $this->stubPixels();
        }

        $accounts = $this->listAdAccounts($connection);
        $token = $this->resolveAccessToken($connection);
        $pixels = [];

        foreach ($accounts as $account) {
            $response = $this->graphClient->getPaginatedList(
                apiVersion: 'v20.0',
                path: "{$account['account_id']}/adspixels",
                accessToken: $token,
                query: [
                    'fields' => 'id,name,is_unavailable',
                    'limit' => 100,
                ],
            );

            foreach ($response['data'] as $item) {
                $pixels[] = [
                    'pixel_id' => (string) $item['id'],
                    'name' => (string) ($item['name'] ?? 'Unknown pixel'),
                    'is_active' => ! (bool) ($item['is_unavailable'] ?? false),
                    'metadata' => [
                        'source' => 'live',
                        'account_id' => $account['account_id'],
                        'api_usage' => $response['usage'],
                    ],
                ];
            }
        }

        return $pixels;
    }

    public function syncCampaigns(MetaConnection $connection, string $accountId): array
    {
        if ($this->shouldUseStubMode()) {
            return $this->stubCampaigns($accountId);
        }

        $response = $this->graphClient->getPaginatedList(
            apiVersion: 'v20.0',
            path: "{$accountId}/campaigns",
            accessToken: $this->resolveAccessToken($connection),
            query: [
                'fields' => 'id,name,objective,status,effective_status,buying_type,daily_budget,lifetime_budget,start_time,stop_time',
                'limit' => 200,
            ],
        );

        return array_map(fn (array $item): array => [
            'meta_campaign_id' => (string) $item['id'],
            'name' => (string) ($item['name'] ?? 'Unknown campaign'),
            'objective' => $item['objective'] ?? null,
            'status' => strtolower((string) ($item['status'] ?? 'active')),
            'effective_status' => $item['effective_status'] ?? null,
            'buying_type' => $item['buying_type'] ?? null,
            'daily_budget' => $this->normalizeBudget($item['daily_budget'] ?? null),
            'lifetime_budget' => $this->normalizeBudget($item['lifetime_budget'] ?? null),
            'start_time' => $item['start_time'] ?? null,
            'stop_time' => $item['stop_time'] ?? null,
            'is_active' => strtoupper((string) ($item['effective_status'] ?? '')) === 'ACTIVE',
            'metadata' => [
                'account_id' => $accountId,
                'source' => 'live',
                'api_usage' => $response['usage'],
            ],
        ], $response['data']);
    }

    public function syncAdSets(MetaConnection $connection, string $accountId): array
    {
        if ($this->shouldUseStubMode()) {
            return $this->stubAdSets();
        }

        $response = $this->graphClient->getPaginatedList(
            apiVersion: 'v20.0',
            path: "{$accountId}/adsets",
            accessToken: $this->resolveAccessToken($connection),
            query: [
                'fields' => 'id,campaign_id,name,status,effective_status,optimization_goal,billing_event,bid_strategy,daily_budget,lifetime_budget,start_time,stop_time,targeting',
                'limit' => 200,
            ],
        );

        return array_map(fn (array $item): array => [
            'meta_ad_set_id' => (string) $item['id'],
            'meta_campaign_id' => (string) ($item['campaign_id'] ?? ''),
            'name' => (string) ($item['name'] ?? 'Unknown ad set'),
            'status' => strtolower((string) ($item['status'] ?? 'active')),
            'effective_status' => $item['effective_status'] ?? null,
            'optimization_goal' => $item['optimization_goal'] ?? null,
            'billing_event' => $item['billing_event'] ?? null,
            'bid_strategy' => $item['bid_strategy'] ?? null,
            'daily_budget' => $this->normalizeBudget($item['daily_budget'] ?? null),
            'lifetime_budget' => $this->normalizeBudget($item['lifetime_budget'] ?? null),
            'start_time' => $item['start_time'] ?? null,
            'stop_time' => $item['stop_time'] ?? null,
            'targeting' => $item['targeting'] ?? null,
            'metadata' => [
                'account_id' => $accountId,
                'source' => 'live',
                'api_usage' => $response['usage'],
            ],
        ], $response['data']);
    }

    public function syncAds(MetaConnection $connection, string $accountId): array
    {
        if ($this->shouldUseStubMode()) {
            return $this->stubAds();
        }

        $response = $this->graphClient->getPaginatedList(
            apiVersion: 'v20.0',
            path: "{$accountId}/ads",
            accessToken: $this->resolveAccessToken($connection),
            query: [
                'fields' => 'id,campaign_id,adset_id,name,status,effective_status,creative{id},preview_shareable_link',
                'limit' => 200,
            ],
        );

        return array_map(fn (array $item): array => [
            'meta_ad_id' => (string) $item['id'],
            'meta_campaign_id' => (string) ($item['campaign_id'] ?? ''),
            'meta_ad_set_id' => (string) ($item['adset_id'] ?? ''),
            'meta_creative_id' => Arr::get($item, 'creative.id'),
            'name' => (string) ($item['name'] ?? 'Unknown ad'),
            'status' => strtolower((string) ($item['status'] ?? 'active')),
            'effective_status' => $item['effective_status'] ?? null,
            'preview_url' => $item['preview_shareable_link'] ?? null,
            'metadata' => [
                'account_id' => $accountId,
                'source' => 'live',
                'api_usage' => $response['usage'],
            ],
        ], $response['data']);
    }

    public function syncCreatives(MetaConnection $connection, string $accountId): array
    {
        if ($this->shouldUseStubMode()) {
            return $this->stubCreatives();
        }

        $response = $this->graphClient->getPaginatedList(
            apiVersion: 'v20.0',
            path: "{$accountId}/adcreatives",
            accessToken: $this->resolveAccessToken($connection),
            query: [
                'fields' => 'id,name,object_story_spec,object_url,call_to_action_type,title,body',
                'limit' => 200,
            ],
        );

        return array_map(function (array $item) use ($accountId, $response): array {
            $storySpec = Arr::get($item, 'object_story_spec', []);
            $linkData = Arr::get($storySpec, 'link_data', []);
            $videoData = Arr::get($storySpec, 'video_data', []);

            return [
                'meta_creative_id' => (string) $item['id'],
                'name' => $item['name'] ?? null,
                'asset_type' => $this->resolveCreativeAssetType($storySpec),
                'body' => Arr::get($linkData, 'message')
                    ?? Arr::get($videoData, 'message')
                    ?? ($item['body'] ?? null),
                'headline' => Arr::get($linkData, 'name')
                    ?? Arr::get($videoData, 'title')
                    ?? ($item['title'] ?? null),
                'description' => Arr::get($linkData, 'description')
                    ?? Arr::get($videoData, 'description'),
                'call_to_action' => Arr::get($linkData, 'call_to_action.type')
                    ?? Arr::get($videoData, 'call_to_action.type')
                    ?? ($item['call_to_action_type'] ?? null),
                'destination_url' => Arr::get($linkData, 'link')
                    ?? Arr::get($videoData, 'link')
                    ?? ($item['object_url'] ?? null),
                'metadata' => [
                    'account_id' => $accountId,
                    'source' => 'live',
                    'api_usage' => $response['usage'],
                ],
            ];
        }, $response['data']);
    }

    public function syncDailyInsights(
        MetaConnection $connection,
        string $accountId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): array {
        if ($this->shouldUseStubMode()) {
            return $this->stubDailyInsights($accountId, $startDate, $endDate);
        }

        $response = $this->graphClient->getPaginatedList(
            apiVersion: 'v20.0',
            path: "{$accountId}/insights",
            accessToken: $this->resolveAccessToken($connection),
            query: [
                'level' => 'campaign',
                'time_increment' => 1,
                'time_range' => json_encode([
                    'since' => $startDate->toDateString(),
                    'until' => $endDate->toDateString(),
                ], JSON_THROW_ON_ERROR),
                'fields' => 'campaign_id,date_start,spend,impressions,reach,frequency,clicks,inline_link_clicks,ctr,cpc,cpm,actions,cost_per_action_type,purchase_roas',
                'limit' => 500,
            ],
        );

        return array_map(function (array $item) use ($accountId, $response): array {
            $actions = $this->normalizeActions($item['actions'] ?? []);
            $leads = $this->sumActions($actions, ['lead', 'onsite_conversion.lead_grouped']);
            $purchases = $this->sumActions($actions, ['purchase', 'omni_purchase', 'offsite_conversion.purchase']);
            $results = $leads ?: $purchases;

            return [
                'level' => 'campaign',
                'entity_external_id' => (string) ($item['campaign_id'] ?? ''),
                'date' => Carbon::parse((string) ($item['date_start'] ?? now()->toDateString()))->toDateString(),
                'spend' => $this->normalizeMetric($item['spend'] ?? null) ?? 0,
                'impressions' => (int) ($item['impressions'] ?? 0),
                'reach' => isset($item['reach']) ? (int) $item['reach'] : null,
                'frequency' => $this->normalizeMetric($item['frequency'] ?? null),
                'clicks' => (int) ($item['clicks'] ?? 0),
                'link_clicks' => isset($item['inline_link_clicks']) ? (int) $item['inline_link_clicks'] : null,
                'ctr' => $this->normalizeMetric($item['ctr'] ?? null),
                'cpc' => $this->normalizeMetric($item['cpc'] ?? null),
                'cpm' => $this->normalizeMetric($item['cpm'] ?? null),
                'results' => $results !== null ? (float) $results : null,
                'cost_per_result' => $this->resolveCostPerResult($item['cost_per_action_type'] ?? [], $leads, $purchases),
                'leads' => $leads !== null ? (float) $leads : null,
                'purchases' => $purchases !== null ? (float) $purchases : null,
                'roas' => $this->resolveRoas($item['purchase_roas'] ?? []),
                'conversions' => $results !== null ? (float) $results : null,
                'actions' => $actions,
                'source' => 'meta',
                'metadata' => [
                    'account_id' => $accountId,
                    'api_usage' => $response['usage'],
                ],
            ];
        }, array_filter($response['data'], fn (array $item): bool => filled($item['campaign_id'] ?? null)));
    }

    public function publishCampaignDraft(MetaConnection $connection, CampaignDraft $draft): array
    {
        if ($this->shouldUseStubMode()) {
            return [
                'success' => false,
                'status' => 'stubbed',
                'message' => 'Meta mode stub durumunda. Canli yayinlamak icin META_MODE=live ayarlayin.',
                'meta_reference' => null,
            ];
        }

        $accessToken = $this->resolveAccessToken($connection);
        $adAccountId = $draft->adAccount?->account_id;

        if (! $adAccountId) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Draft ile iliskili ad account bulunamadi.',
                'meta_reference' => null,
            ];
        }

        $normalizedAccountId = str_starts_with($adAccountId, 'act_') ? $adAccountId : "act_{$adAccountId}";

        try {
            $objectiveMap = [
                'LEADS' => 'OUTCOME_LEADS',
                'CONVERSIONS' => 'OUTCOME_SALES',
                'SALES' => 'OUTCOME_SALES',
                'TRAFFIC' => 'OUTCOME_TRAFFIC',
                'AWARENESS' => 'OUTCOME_AWARENESS',
                'ENGAGEMENT' => 'OUTCOME_ENGAGEMENT',
                'APP_PROMOTION' => 'OUTCOME_APP_PROMOTION',
            ];

            $metaObjective = $objectiveMap[strtoupper($draft->objective ?? '')] ?? 'OUTCOME_LEADS';

            // 1. Create Campaign
            $campaignResponse = $this->graphClient->postObject(
                apiVersion: 'v20.0',
                path: "{$normalizedAccountId}/campaigns",
                accessToken: $accessToken,
                data: [
                    'name' => strtoupper($draft->objective ?? 'CAMPAIGN') . '_' . ($draft->location ?? 'TR') . '_' . now()->format('Ymd'),
                    'objective' => $metaObjective,
                    'status' => 'PAUSED',
                    'special_ad_categories' => '[]',
                ],
            );

            $metaCampaignId = $campaignResponse['body']['id'] ?? null;

            if (! $metaCampaignId) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Meta kampanya olusturuldu ancak ID donmedi.',
                    'meta_reference' => null,
                ];
            }

            // 2. Create Ad Set
            $dailyBudget = (int) (($draft->budget_min ?? 50) * 100); // Meta expects cents

            $adSetData = [
                'name' => 'AS_' . ($draft->location ?? 'TR') . '_' . now()->format('Ymd'),
                'campaign_id' => $metaCampaignId,
                'billing_event' => 'IMPRESSIONS',
                'optimization_goal' => $metaObjective === 'OUTCOME_LEADS' ? 'LEAD_GENERATION' : 'OFFSITE_CONVERSIONS',
                'daily_budget' => $dailyBudget,
                'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
                'status' => 'PAUSED',
                'targeting' => json_encode([
                    'geo_locations' => [
                        'countries' => [strtoupper($draft->location ?? 'TR')],
                    ],
                ]),
            ];

            $adSetResponse = $this->graphClient->postObject(
                apiVersion: 'v20.0',
                path: "{$normalizedAccountId}/adsets",
                accessToken: $accessToken,
                data: $adSetData,
            );

            $metaAdSetId = $adSetResponse['body']['id'] ?? null;

            return [
                'success' => true,
                'status' => 'published',
                'message' => 'Kampanya ve ad set Meta Ads Manager\'da PAUSED olarak olusturuldu.',
                'meta_reference' => [
                    'campaign_id' => $metaCampaignId,
                    'ad_set_id' => $metaAdSetId,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Meta API hatasi: ' . $e->getMessage(),
                'meta_reference' => null,
            ];
        }
    }

    private function shouldUseStubMode(): bool
    {
        return config('services.meta.mode', 'stub') !== 'live';
    }

    private function resolveAccessToken(MetaConnection $connection): string
    {
        $token = $connection->decryptAccessToken();

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Meta access token cozulamedi.');
        }

        return $token;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stubAdAccounts(): array
    {
        return [
            [
                'account_id' => 'act_1001',
                'name' => 'Mock Hesap TR',
                'currency' => 'USD',
                'timezone_name' => 'Europe/Istanbul',
                'status' => 'active',
                'is_active' => true,
                'business' => [
                    'business_id' => 'biz_1001',
                    'name' => 'Mock Agency',
                    'verification_status' => 'verified',
                ],
                'metadata' => [
                    'source' => 'stub',
                    'api_version' => 'v20.0',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stubPages(): array
    {
        return [
            [
                'page_id' => 'pg_1001',
                'name' => 'Mock Marka Sayfasi',
                'category' => 'Brand',
                'metadata' => ['source' => 'stub'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stubPixels(): array
    {
        return [
            [
                'pixel_id' => 'px_1001',
                'name' => 'Mock Pixel',
                'is_active' => true,
                'metadata' => ['source' => 'stub'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stubCampaigns(string $accountId): array
    {
        return [
            [
                'meta_campaign_id' => 'cmp_1001',
                'name' => 'Ilk Performans Kampanyasi',
                'objective' => 'LEADS',
                'status' => 'active',
                'effective_status' => 'ACTIVE',
                'buying_type' => 'AUCTION',
                'daily_budget' => 100.00,
                'lifetime_budget' => null,
                'start_time' => now()->subDays(10)->toISOString(),
                'stop_time' => null,
                'is_active' => true,
                'metadata' => [
                    'account_id' => $accountId,
                    'source' => 'stub',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stubAdSets(): array
    {
        return [
            [
                'meta_ad_set_id' => 'adset_1001',
                'meta_campaign_id' => 'cmp_1001',
                'name' => 'TR - Genis Kitle',
                'status' => 'active',
                'effective_status' => 'ACTIVE',
                'optimization_goal' => 'LEAD_GENERATION',
                'billing_event' => 'IMPRESSIONS',
                'bid_strategy' => 'LOWEST_COST',
                'daily_budget' => 50.00,
                'lifetime_budget' => null,
                'targeting' => [
                    'countries' => ['TR'],
                    'age_min' => 25,
                    'age_max' => 45,
                ],
                'metadata' => ['source' => 'stub'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stubAds(): array
    {
        return [
            [
                'meta_ad_id' => 'ad_1001',
                'meta_campaign_id' => 'cmp_1001',
                'meta_ad_set_id' => 'adset_1001',
                'meta_creative_id' => 'crt_1001',
                'name' => 'Video Ad - A',
                'status' => 'active',
                'effective_status' => 'ACTIVE',
                'preview_url' => 'https://example.com/preview/ad_1001',
                'metadata' => ['source' => 'stub'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stubCreatives(): array
    {
        return [
            [
                'meta_creative_id' => 'crt_1001',
                'name' => 'Creative A',
                'asset_type' => 'video',
                'body' => 'Deneme kreatif metni',
                'headline' => 'Hemen Incele',
                'description' => 'Ornek aciklama',
                'call_to_action' => 'LEARN_MORE',
                'destination_url' => 'https://example.com/landing',
                'metadata' => ['source' => 'stub'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stubDailyInsights(
        string $accountId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): array {
        $data = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $data[] = [
                'level' => 'campaign',
                'entity_external_id' => 'cmp_1001',
                'date' => $date->toDateString(),
                'spend' => 120.50,
                'impressions' => 5400,
                'reach' => 4100,
                'frequency' => 1.32,
                'clicks' => 210,
                'link_clicks' => 150,
                'ctr' => 3.88,
                'cpc' => 0.57,
                'cpm' => 22.31,
                'results' => 14,
                'cost_per_result' => 8.60,
                'leads' => 12,
                'purchases' => 2,
                'roas' => 2.40,
                'conversions' => 14,
                'actions' => [
                    ['type' => 'lead', 'value' => 12],
                    ['type' => 'purchase', 'value' => 2],
                ],
                'source' => 'meta',
                'metadata' => ['account_id' => $accountId],
            ];
        }

        return $data;
    }

    private function mapAccountStatus(mixed $value): string
    {
        return match ((int) $value) {
            1 => 'active',
            2, 101 => 'disabled',
            3, 7, 8, 9, 100 => 'restricted',
            default => 'active',
        };
    }

    private function normalizeBudget(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round(((float) $value) / 100, 2);
    }

    private function normalizeMetric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeActions(array $actions): array
    {
        return array_values(array_map(fn (array $item): array => [
            'type' => (string) ($item['action_type'] ?? $item['type'] ?? 'unknown'),
            'value' => (float) ($item['value'] ?? 0),
        ], $actions));
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @param array<int, string> $needleTypes
     */
    private function sumActions(array $actions, array $needleTypes): ?float
    {
        $value = collect($actions)
            ->filter(fn (array $item): bool => in_array($item['type'], $needleTypes, true))
            ->sum('value');

        return $value > 0 ? (float) $value : null;
    }

    /**
     * @param array<int, array<string, mixed>> $costPerActionType
     */
    private function resolveCostPerResult(array $costPerActionType, ?float $leads, ?float $purchases): ?float
    {
        $normalized = collect($costPerActionType)
            ->map(fn (array $item): array => [
                'type' => (string) ($item['action_type'] ?? $item['type'] ?? 'unknown'),
                'value' => (float) ($item['value'] ?? 0),
            ]);

        $leadCost = data_get($normalized->firstWhere('type', 'lead'), 'value')
            ?? data_get($normalized->firstWhere('type', 'onsite_conversion.lead_grouped'), 'value');

        if ($leadCost !== null && $leads !== null) {
            return (float) $leadCost;
        }

        $purchaseCost = data_get($normalized->firstWhere('type', 'purchase'), 'value')
            ?? data_get($normalized->firstWhere('type', 'omni_purchase'), 'value');

        if ($purchaseCost !== null && $purchases !== null) {
            return (float) $purchaseCost;
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $purchaseRoas
     */
    private function resolveRoas(array $purchaseRoas): ?float
    {
        $row = $purchaseRoas[0] ?? null;

        if (! is_array($row) || ! isset($row['value'])) {
            return null;
        }

        return (float) $row['value'];
    }

    /**
     * @param array<string, mixed> $storySpec
     */
    private function resolveCreativeAssetType(array $storySpec): ?string
    {
        if (Arr::has($storySpec, 'video_data')) {
            return 'video';
        }

        if (Arr::has($storySpec, 'photo_data')) {
            return 'image';
        }

        if (Arr::has($storySpec, 'link_data')) {
            return 'link';
        }

        return null;
    }
}
