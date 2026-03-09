<?php

namespace App\Domain\Meta\Adapters;

use App\Domain\Meta\Contracts\MetaApiAdapter;
use App\Models\CampaignDraft;
use App\Models\MetaConnection;
use Carbon\CarbonInterface;

class MetaGraphV20Adapter implements MetaApiAdapter
{
    public function listAdAccounts(MetaConnection $connection): array
    {
        return [
            [
                'account_id' => 'act_1001',
                'name' => 'Mock Hesap TR',
                'currency' => 'USD',
                'timezone_name' => 'Europe/Istanbul',
                'status' => 'active',
                'metadata' => [
                    'source' => 'stub',
                    'api_version' => 'v20.0',
                ],
            ],
        ];
    }

    public function listPages(MetaConnection $connection): array
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

    public function listPixels(MetaConnection $connection): array
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

    public function syncCampaigns(MetaConnection $connection, string $accountId): array
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

    public function syncAdSets(MetaConnection $connection, string $accountId): array
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

    public function syncAds(MetaConnection $connection, string $accountId): array
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

    public function syncCreatives(MetaConnection $connection, string $accountId): array
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

    public function syncDailyInsights(
        MetaConnection $connection,
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

    public function publishCampaignDraft(MetaConnection $connection, CampaignDraft $draft): array
    {
        return [
            'success' => false,
            'status' => 'stubbed',
            'message' => 'MVP publish adapter cagrisi stub durumunda.',
            'meta_reference' => null,
        ];
    }
}
