<?php

namespace App\Domain\Meta\Contracts;

use App\Models\CampaignDraft;
use App\Models\MetaConnection;
use Carbon\CarbonInterface;

interface MetaApiAdapter
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAdAccounts(MetaConnection $connection): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPages(MetaConnection $connection): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPixels(MetaConnection $connection): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncCampaigns(MetaConnection $connection, string $accountId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncAdSets(MetaConnection $connection, string $accountId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncAds(MetaConnection $connection, string $accountId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncCreatives(MetaConnection $connection, string $accountId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncDailyInsights(
        MetaConnection $connection,
        string $accountId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function publishCampaignDraft(MetaConnection $connection, CampaignDraft $draft): array;
}
