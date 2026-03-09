<?php

namespace App\Domain\Meta\Services;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Creative;
use App\Models\InsightDaily;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\MetaPage;
use App\Models\MetaPixel;
use App\Models\RawApiPayload;
use App\Models\SyncRun;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MetaSyncService
{
    public function __construct(
        private readonly MetaAdapterFactory $adapterFactory,
        private readonly MetaConnectionService $connectionService,
    ) {
    }

    public function runAssetSync(MetaConnection $connection): SyncRun
    {
        $adapter = $this->adapterFactory->resolve($connection);

        $syncRun = SyncRun::query()->create([
            'workspace_id' => $connection->workspace_id,
            'meta_connection_id' => $connection->id,
            'type' => 'asset_sync',
            'status' => 'running',
            'started_at' => now(),
            'request_fingerprint' => Str::uuid()->toString(),
        ]);

        try {
            $accounts = $adapter->listAdAccounts($connection);
            $pages = $adapter->listPages($connection);
            $pixels = $adapter->listPixels($connection);

            $this->storeRawPayload($syncRun, $connection, 'list_ad_accounts', $accounts);
            $this->storeRawPayload($syncRun, $connection, 'list_pages', $pages);
            $this->storeRawPayload($syncRun, $connection, 'list_pixels', $pixels);

            $accountCount = $this->upsertAdAccounts($connection, $accounts);
            $pageCount = $this->upsertPages($connection, $pages);
            $pixelCount = $this->upsertPixels($connection, $pixels);

            $campaignCount = 0;
            $adSetCount = 0;
            $creativeCount = 0;
            $adCount = 0;

            foreach ($accounts as $accountData) {
                $accountId = $accountData['account_id'];
                $account = MetaAdAccount::query()
                    ->where('workspace_id', $connection->workspace_id)
                    ->where('account_id', $accountId)
                    ->first();

                if (! $account) {
                    continue;
                }

                $campaignPayload = $adapter->syncCampaigns($connection, $accountId);
                $adSetPayload = $adapter->syncAdSets($connection, $accountId);
                $creativePayload = $adapter->syncCreatives($connection, $accountId);
                $adPayload = $adapter->syncAds($connection, $accountId);

                $this->storeRawPayload($syncRun, $connection, "sync_campaigns:{$accountId}", $campaignPayload);
                $this->storeRawPayload($syncRun, $connection, "sync_ad_sets:{$accountId}", $adSetPayload);
                $this->storeRawPayload($syncRun, $connection, "sync_creatives:{$accountId}", $creativePayload);
                $this->storeRawPayload($syncRun, $connection, "sync_ads:{$accountId}", $adPayload);

                $campaignCount += $this->upsertCampaigns($connection, $account, $campaignPayload);
                $adSetCount += $this->upsertAdSets($connection, $campaignPayload, $adSetPayload);
                $creativeCount += $this->upsertCreatives($connection, $account, $creativePayload);
                $adCount += $this->upsertAds($connection, $adPayload);
            }

            $syncRun->forceFill([
                'status' => 'completed',
                'finished_at' => now(),
                'summary' => [
                    'accounts' => $accountCount,
                    'pages' => $pageCount,
                    'pixels' => $pixelCount,
                    'campaigns' => $campaignCount,
                    'ad_sets' => $adSetCount,
                    'creatives' => $creativeCount,
                    'ads' => $adCount,
                ],
            ])->save();

            $this->connectionService->markSynced($connection);
        } catch (Throwable $throwable) {
            $syncRun->forceFill([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
                'attempts' => $syncRun->attempts + 1,
            ])->save();

            throw $throwable;
        }

        return $syncRun->fresh();
    }

    public function runInsightsSync(
        MetaConnection $connection,
        string $accountId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): SyncRun {
        $adapter = $this->adapterFactory->resolve($connection);

        $syncRun = SyncRun::query()->create([
            'workspace_id' => $connection->workspace_id,
            'meta_connection_id' => $connection->id,
            'type' => 'insights_daily_sync',
            'status' => 'running',
            'started_at' => now(),
            'request_fingerprint' => sha1($connection->id.$accountId.$startDate->toDateString().$endDate->toDateString()),
        ]);

        try {
            $payload = $adapter->syncDailyInsights($connection, $accountId, $startDate, $endDate);
            $this->storeRawPayload($syncRun, $connection, "sync_insights:{$accountId}", $payload);

            $rows = [];

            foreach ($payload as $item) {
                $entityExternalId = (string) ($item['entity_external_id'] ?? '');

                if ($entityExternalId === '') {
                    continue;
                }

                $campaign = Campaign::query()
                    ->where('workspace_id', $connection->workspace_id)
                    ->where('meta_campaign_id', $entityExternalId)
                    ->first();

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $connection->workspace_id,
                    'level' => $item['level'] ?? 'campaign',
                    'entity_id' => $campaign?->id,
                    'entity_external_id' => $entityExternalId,
                    'date' => $item['date'],
                    'spend' => $item['spend'] ?? 0,
                    'impressions' => $item['impressions'] ?? 0,
                    'reach' => $item['reach'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'clicks' => $item['clicks'] ?? 0,
                    'link_clicks' => $item['link_clicks'] ?? null,
                    'ctr' => $item['ctr'] ?? null,
                    'cpc' => $item['cpc'] ?? null,
                    'cpm' => $item['cpm'] ?? null,
                    'results' => $item['results'] ?? null,
                    'cost_per_result' => $item['cost_per_result'] ?? null,
                    'leads' => $item['leads'] ?? null,
                    'purchases' => $item['purchases'] ?? null,
                    'roas' => $item['roas'] ?? null,
                    'conversions' => $item['conversions'] ?? null,
                    'actions' => $item['actions'] ?? null,
                    'source' => $item['source'] ?? 'meta',
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                InsightDaily::query()->upsert(
                    $rows,
                    ['workspace_id', 'level', 'entity_external_id', 'date', 'source'],
                    [
                        'entity_id',
                        'spend',
                        'impressions',
                        'reach',
                        'frequency',
                        'clicks',
                        'link_clicks',
                        'ctr',
                        'cpc',
                        'cpm',
                        'results',
                        'cost_per_result',
                        'leads',
                        'purchases',
                        'roas',
                        'conversions',
                        'actions',
                        'synced_at',
                        'updated_at',
                    ]
                );
            }

            $syncRun->forceFill([
                'status' => 'completed',
                'finished_at' => now(),
                'summary' => [
                    'account_id' => $accountId,
                    'rows' => count($rows),
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ])->save();

            $this->connectionService->markSynced($connection);
        } catch (Throwable $throwable) {
            $syncRun->forceFill([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
                'attempts' => $syncRun->attempts + 1,
            ])->save();

            throw $throwable;
        }

        return $syncRun->fresh();
    }

    /**
     * @param array<int, array<string, mixed>> $accounts
     */
    private function upsertAdAccounts(MetaConnection $connection, array $accounts): int
    {
        $rows = array_map(fn (array $item): array => [
            'id' => (string) Str::uuid(),
            'workspace_id' => $connection->workspace_id,
            'meta_connection_id' => $connection->id,
            'account_id' => $item['account_id'],
            'name' => $item['name'],
            'currency' => $item['currency'] ?? null,
            'timezone_name' => $item['timezone_name'] ?? null,
            'status' => $item['status'] ?? 'active',
            'is_active' => $item['is_active'] ?? true,
            'metadata' => $item['metadata'] ?? null,
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $accounts);

        if ($rows !== []) {
            MetaAdAccount::query()->upsert(
                $rows,
                ['workspace_id', 'account_id'],
                ['name', 'currency', 'timezone_name', 'status', 'is_active', 'metadata', 'last_synced_at', 'updated_at']
            );
        }

        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     */
    private function upsertPages(MetaConnection $connection, array $pages): int
    {
        $rows = array_map(fn (array $item): array => [
            'id' => (string) Str::uuid(),
            'workspace_id' => $connection->workspace_id,
            'meta_connection_id' => $connection->id,
            'page_id' => $item['page_id'],
            'name' => $item['name'],
            'category' => $item['category'] ?? null,
            'metadata' => $item['metadata'] ?? null,
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $pages);

        if ($rows !== []) {
            MetaPage::query()->upsert(
                $rows,
                ['workspace_id', 'page_id'],
                ['name', 'category', 'metadata', 'last_synced_at', 'updated_at']
            );
        }

        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $pixels
     */
    private function upsertPixels(MetaConnection $connection, array $pixels): int
    {
        $rows = array_map(fn (array $item): array => [
            'id' => (string) Str::uuid(),
            'workspace_id' => $connection->workspace_id,
            'meta_connection_id' => $connection->id,
            'pixel_id' => $item['pixel_id'],
            'name' => $item['name'],
            'is_active' => $item['is_active'] ?? true,
            'metadata' => $item['metadata'] ?? null,
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $pixels);

        if ($rows !== []) {
            MetaPixel::query()->upsert(
                $rows,
                ['workspace_id', 'pixel_id'],
                ['name', 'is_active', 'metadata', 'last_synced_at', 'updated_at']
            );
        }

        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $campaigns
     */
    private function upsertCampaigns(MetaConnection $connection, MetaAdAccount $account, array $campaigns): int
    {
        $rows = array_map(fn (array $item): array => [
            'id' => (string) Str::uuid(),
            'workspace_id' => $connection->workspace_id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => $item['meta_campaign_id'],
            'name' => $item['name'],
            'objective' => $item['objective'] ?? null,
            'status' => $item['status'] ?? 'active',
            'effective_status' => $item['effective_status'] ?? null,
            'buying_type' => $item['buying_type'] ?? null,
            'daily_budget' => $item['daily_budget'] ?? null,
            'lifetime_budget' => $item['lifetime_budget'] ?? null,
            'start_time' => isset($item['start_time']) ? Carbon::parse($item['start_time']) : null,
            'stop_time' => isset($item['stop_time']) ? Carbon::parse($item['stop_time']) : null,
            'is_active' => $item['is_active'] ?? true,
            'metadata' => $item['metadata'] ?? null,
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $campaigns);

        if ($rows !== []) {
            Campaign::query()->upsert(
                $rows,
                ['workspace_id', 'meta_campaign_id'],
                [
                    'meta_ad_account_id',
                    'name',
                    'objective',
                    'status',
                    'effective_status',
                    'buying_type',
                    'daily_budget',
                    'lifetime_budget',
                    'start_time',
                    'stop_time',
                    'is_active',
                    'metadata',
                    'last_synced_at',
                    'updated_at',
                ]
            );
        }

        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $campaignPayload
     * @param array<int, array<string, mixed>> $adSets
     */
    private function upsertAdSets(MetaConnection $connection, array $campaignPayload, array $adSets): int
    {
        $campaignIdMap = [];
        $campaignMetaIds = array_filter(array_map(fn (array $item) => $item['meta_campaign_id'] ?? null, $campaignPayload));

        if ($campaignMetaIds !== []) {
            $campaigns = Campaign::query()
                ->where('workspace_id', $connection->workspace_id)
                ->whereIn('meta_campaign_id', $campaignMetaIds)
                ->get(['id', 'meta_campaign_id']);

            foreach ($campaigns as $campaign) {
                $campaignIdMap[$campaign->meta_campaign_id] = $campaign->id;
            }
        }

        $rows = [];

        foreach ($adSets as $item) {
            $campaignId = $campaignIdMap[$item['meta_campaign_id'] ?? ''] ?? null;

            if (! $campaignId) {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'workspace_id' => $connection->workspace_id,
                'campaign_id' => $campaignId,
                'meta_ad_set_id' => $item['meta_ad_set_id'],
                'name' => $item['name'],
                'status' => $item['status'] ?? 'active',
                'effective_status' => $item['effective_status'] ?? null,
                'optimization_goal' => $item['optimization_goal'] ?? null,
                'billing_event' => $item['billing_event'] ?? null,
                'bid_strategy' => $item['bid_strategy'] ?? null,
                'daily_budget' => $item['daily_budget'] ?? null,
                'lifetime_budget' => $item['lifetime_budget'] ?? null,
                'targeting' => $item['targeting'] ?? null,
                'metadata' => $item['metadata'] ?? null,
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            AdSet::query()->upsert(
                $rows,
                ['workspace_id', 'meta_ad_set_id'],
                [
                    'campaign_id',
                    'name',
                    'status',
                    'effective_status',
                    'optimization_goal',
                    'billing_event',
                    'bid_strategy',
                    'daily_budget',
                    'lifetime_budget',
                    'targeting',
                    'metadata',
                    'last_synced_at',
                    'updated_at',
                ]
            );
        }

        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $creatives
     */
    private function upsertCreatives(MetaConnection $connection, MetaAdAccount $account, array $creatives): int
    {
        $rows = array_map(fn (array $item): array => [
            'id' => (string) Str::uuid(),
            'workspace_id' => $connection->workspace_id,
            'meta_ad_account_id' => $account->id,
            'meta_creative_id' => $item['meta_creative_id'],
            'name' => $item['name'] ?? null,
            'asset_type' => $item['asset_type'] ?? null,
            'body' => $item['body'] ?? null,
            'headline' => $item['headline'] ?? null,
            'description' => $item['description'] ?? null,
            'call_to_action' => $item['call_to_action'] ?? null,
            'destination_url' => $item['destination_url'] ?? null,
            'metadata' => $item['metadata'] ?? null,
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $creatives);

        if ($rows !== []) {
            Creative::query()->upsert(
                $rows,
                ['workspace_id', 'meta_creative_id'],
                [
                    'meta_ad_account_id',
                    'name',
                    'asset_type',
                    'body',
                    'headline',
                    'description',
                    'call_to_action',
                    'destination_url',
                    'metadata',
                    'last_synced_at',
                    'updated_at',
                ]
            );
        }

        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $ads
     */
    private function upsertAds(MetaConnection $connection, array $ads): int
    {
        $campaignMetaIds = array_filter(array_map(fn (array $item) => $item['meta_campaign_id'] ?? null, $ads));
        $adSetMetaIds = array_filter(array_map(fn (array $item) => $item['meta_ad_set_id'] ?? null, $ads));
        $creativeMetaIds = array_filter(array_map(fn (array $item) => $item['meta_creative_id'] ?? null, $ads));

        $campaignMap = Campaign::query()
            ->where('workspace_id', $connection->workspace_id)
            ->whereIn('meta_campaign_id', $campaignMetaIds)
            ->pluck('id', 'meta_campaign_id')
            ->toArray();

        $adSetMap = AdSet::query()
            ->where('workspace_id', $connection->workspace_id)
            ->whereIn('meta_ad_set_id', $adSetMetaIds)
            ->pluck('id', 'meta_ad_set_id')
            ->toArray();

        $creativeMap = Creative::query()
            ->where('workspace_id', $connection->workspace_id)
            ->whereIn('meta_creative_id', $creativeMetaIds)
            ->pluck('id', 'meta_creative_id')
            ->toArray();

        $rows = [];

        foreach ($ads as $item) {
            $campaignId = $campaignMap[$item['meta_campaign_id'] ?? ''] ?? null;
            $adSetId = $adSetMap[$item['meta_ad_set_id'] ?? ''] ?? null;

            if (! $campaignId || ! $adSetId) {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'workspace_id' => $connection->workspace_id,
                'campaign_id' => $campaignId,
                'ad_set_id' => $adSetId,
                'creative_id' => $creativeMap[$item['meta_creative_id'] ?? ''] ?? null,
                'meta_ad_id' => $item['meta_ad_id'],
                'name' => $item['name'],
                'status' => $item['status'] ?? 'active',
                'effective_status' => $item['effective_status'] ?? null,
                'preview_url' => $item['preview_url'] ?? null,
                'metadata' => $item['metadata'] ?? null,
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            Ad::query()->upsert(
                $rows,
                ['workspace_id', 'meta_ad_id'],
                [
                    'campaign_id',
                    'ad_set_id',
                    'creative_id',
                    'name',
                    'status',
                    'effective_status',
                    'preview_url',
                    'metadata',
                    'last_synced_at',
                    'updated_at',
                ]
            );
        }

        return count($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     */
    private function storeRawPayload(
        SyncRun $syncRun,
        MetaConnection $connection,
        string $resourceType,
        array $payload,
    ): void {
        $serializedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        RawApiPayload::query()->create([
            'workspace_id' => $connection->workspace_id,
            'meta_connection_id' => $connection->id,
            'sync_run_id' => $syncRun->id,
            'resource_type' => $resourceType,
            'resource_key' => $syncRun->request_fingerprint,
            'payload' => $payload,
            'payload_hash' => hash('sha256', $serializedPayload),
            'captured_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);
    }
}
