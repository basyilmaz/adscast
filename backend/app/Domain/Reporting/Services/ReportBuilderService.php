<?php

namespace App\Domain\Reporting\Services;

use App\Models\Campaign;
use App\Models\MetaAdAccount;
use App\Models\ReportSnapshot;
use Carbon\CarbonInterface;

class ReportBuilderService
{
    public function __construct(
        private readonly AdAccountQueryService $adAccountQueryService,
        private readonly CampaignQueryService $campaignQueryService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAccountReport(MetaAdAccount $account, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $detail = $this->adAccountQueryService->detail($account, $startDate, $endDate);
        $topRisk = $detail['alerts'][0]['summary'] ?? $detail['health']['summary'];
        $topOpportunity = $detail['recommendations'][0]['summary']
            ?? ($detail['summary']['results'] > 0
                ? 'Sonuc veren kampanyalarda kontrollu buyutme penceresi bulunuyor.'
                : 'Net bir buyutme penceresi icin yeni test verisi gerekli.');

        $whatWeTested = collect($detail['campaigns'])
            ->take(5)
            ->map(fn (array $campaign): array => [
                'type' => 'campaign',
                'title' => $campaign['name'],
                'subtitle' => $campaign['objective'] ?? 'Objective yok',
                'status' => $campaign['status'],
                'note' => $campaign['health_summary'],
                'route' => sprintf('/campaigns/detail?id=%s', $campaign['id']),
            ])
            ->values()
            ->all();

        $payload = [
            'range' => $detail['range'],
            'entity' => [
                'type' => 'account',
                'id' => $detail['ad_account']['id'],
                'name' => $detail['ad_account']['name'],
                'external_id' => $detail['ad_account']['account_id'],
                'context_label' => $detail['ad_account']['currency'],
            ],
            'report' => [
                'type' => 'client_account_summary_v1',
                'title' => sprintf('%s hesap raporu', $detail['ad_account']['name']),
                'headline' => $detail['report_preview']['headline'],
                'client_summary' => $detail['report_preview']['client_summary'],
                'operator_summary' => $detail['report_preview']['operator_summary'],
                'biggest_risk' => $topRisk,
                'biggest_opportunity' => $topOpportunity,
                'next_test' => $detail['next_best_actions'][0]['recommended_action'] ?? $detail['report_preview']['next_step'],
                'next_step' => $detail['report_preview']['next_step'],
                'generated_at' => now()->toDateTimeString(),
            ],
            'summary' => $detail['summary'],
            'trend' => $detail['trend'],
            'focus_areas' => [
                ['label' => 'En Buyuk Risk', 'detail' => $topRisk],
                ['label' => 'En Buyuk Firsat', 'detail' => $topOpportunity],
                ['label' => 'Bir Sonraki Test', 'detail' => $detail['next_best_actions'][0]['recommended_action'] ?? $detail['report_preview']['next_step']],
            ],
            'what_we_tested' => $whatWeTested,
            'risks' => array_slice($detail['alerts'], 0, 5),
            'recommendations' => array_slice($detail['recommendations'], 0, 5),
            'next_best_actions' => $detail['next_best_actions'],
            'snapshot_history' => $this->snapshotHistory('account', $detail['ad_account']['id']),
            'snapshot_defaults' => [
                'report_type' => 'client_account_summary_v1',
            ],
            'export_options' => [
                'live_csv_url' => sprintf(
                    '/api/v1/reports/account/%s/export.csv?start_date=%s&end_date=%s',
                    $detail['ad_account']['id'],
                    $detail['range']['start_date'],
                    $detail['range']['end_date'],
                ),
                'pdf_foundation' => [
                    'supported' => true,
                    'mode' => 'browser_print',
                    'note' => 'Rapor ekrani tarayici uzerinden yazdirilarak PDF olarak alinabilir.',
                ],
            ],
        ];

        $payload['export_rows'] = $this->accountExportRows($payload, $detail);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCampaignReport(Campaign $campaign, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $detail = $this->campaignQueryService->detail($campaign, $startDate, $endDate);
        $topRisk = $detail['analysis']['biggest_risk'] ?? $detail['health']['summary'];
        $topOpportunity = $detail['analysis']['biggest_opportunity']
            ?? ($detail['summary']['results'] > 0
                ? 'Kampanya kontrollu sekilde yeni varyasyon testine hazir.'
                : 'Yeni kreatif ve hedefleme testleriyle firsat aranmasi gerekiyor.');

        $whatWeTested = collect($detail['ad_sets'])
            ->take(3)
            ->map(fn (array $adSet): array => [
                'type' => 'ad_set',
                'title' => $adSet['name'],
                'subtitle' => $adSet['optimization_goal'] ?? 'Optimization goal yok',
                'status' => $adSet['status'],
                'note' => $adSet['health_summary'],
                'route' => sprintf('/ad-sets/detail?id=%s', $adSet['id']),
                'spend' => $adSet['spend'] ?? null,
                'results' => $adSet['results'] ?? null,
                'cpa_cpl' => $adSet['cpa_cpl'] ?? null,
                'ctr' => $adSet['ctr'] ?? null,
                'cpm' => $adSet['cpm'] ?? null,
            ])
            ->concat(
                collect($detail['ads'])->take(3)->map(fn (array $ad): array => [
                    'type' => 'ad',
                    'title' => $ad['name'],
                    'subtitle' => $ad['creative']['headline'] ?? ($ad['creative']['name'] ?? 'Kreatif'),
                    'status' => $ad['status'],
                    'note' => $ad['health_summary'],
                    'route' => sprintf('/ads/detail?id=%s', $ad['id']),
                    'spend' => $ad['spend'] ?? null,
                    'results' => $ad['results'] ?? null,
                    'cpa_cpl' => $ad['cpa_cpl'] ?? null,
                    'ctr' => $ad['ctr'] ?? null,
                    'cpm' => $ad['cpm'] ?? null,
                ])
            )
            ->values()
            ->all();

        $payload = [
            'range' => $detail['range'],
            'entity' => [
                'type' => 'campaign',
                'id' => $detail['campaign']['id'],
                'name' => $detail['campaign']['name'],
                'external_id' => $detail['campaign']['meta_campaign_id'],
                'context_label' => $detail['campaign']['ad_account']['name'],
            ],
            'report' => [
                'type' => 'client_campaign_summary_v1',
                'title' => sprintf('%s kampanya raporu', $detail['campaign']['name']),
                'headline' => $detail['report_preview']['headline'],
                'client_summary' => $detail['report_preview']['client_summary'],
                'operator_summary' => $detail['report_preview']['operator_summary'],
                'biggest_risk' => $topRisk,
                'biggest_opportunity' => $topOpportunity,
                'next_test' => $detail['report_preview']['next_test'],
                'next_step' => $detail['next_best_actions'][0]['recommended_action'] ?? $detail['report_preview']['next_step'],
                'generated_at' => now()->toDateTimeString(),
            ],
            'summary' => $detail['summary'],
            'trend' => $detail['trend'],
            'focus_areas' => [
                ['label' => 'En Buyuk Risk', 'detail' => $topRisk],
                ['label' => 'En Buyuk Firsat', 'detail' => $topOpportunity],
                ['label' => 'Bir Sonraki Test', 'detail' => $detail['report_preview']['next_test']],
            ],
            'what_we_tested' => $whatWeTested,
            'risks' => array_slice($detail['alerts'], 0, 5),
            'recommendations' => array_slice($detail['recommendations'], 0, 5),
            'creative_performance' => $detail['creative_performance'] ?? [],
            'next_best_actions' => $detail['next_best_actions'],
            'snapshot_history' => $this->snapshotHistory('campaign', $detail['campaign']['id']),
            'snapshot_defaults' => [
                'report_type' => 'client_campaign_summary_v1',
            ],
            'export_options' => [
                'live_csv_url' => sprintf(
                    '/api/v1/reports/campaign/%s/export.csv?start_date=%s&end_date=%s',
                    $detail['campaign']['id'],
                    $detail['range']['start_date'],
                    $detail['range']['end_date'],
                ),
                'pdf_foundation' => [
                    'supported' => true,
                    'mode' => 'browser_print',
                    'note' => 'Rapor ekrani tarayici uzerinden yazdirilarak PDF olarak alinabilir.',
                ],
            ],
        ];

        $payload['export_rows'] = $this->campaignExportRows($payload, $detail);

        return $payload;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function accountExportRows(array $payload, array $detail): array
    {
        $rows = [
            ['section', 'label', 'value', 'secondary'],
            ['report', 'title', $payload['report']['title'], ''],
            ['report', 'headline', $payload['report']['headline'], ''],
            ['summary', 'spend', (string) $detail['summary']['spend'], ''],
            ['summary', 'results', (string) $detail['summary']['results'], ''],
            ['summary', 'cpa_cpl', (string) ($detail['summary']['cpa_cpl'] ?? ''), ''],
            ['summary', 'open_alerts', (string) $detail['summary']['open_alerts'], ''],
            ['summary', 'open_recommendations', (string) $detail['summary']['open_recommendations'], ''],
        ];

        foreach ($detail['campaigns'] as $campaign) {
            $rows[] = ['campaign', $campaign['name'], (string) $campaign['spend'], (string) $campaign['results']];
        }

        foreach ($detail['alerts'] as $alert) {
            $rows[] = ['alert', $alert['summary'], $alert['impact_summary'], $alert['next_step']];
        }

        foreach ($detail['recommendations'] as $recommendation) {
            $rows[] = ['recommendation', $recommendation['summary'], $recommendation['client_view']['summary'], $recommendation['operator_view']['next_test'] ?? ''];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function campaignExportRows(array $payload, array $detail): array
    {
        $rows = [
            ['section', 'label', 'value', 'secondary'],
            ['report', 'title', $payload['report']['title'], ''],
            ['report', 'headline', $payload['report']['headline'], ''],
            ['summary', 'spend', (string) $detail['summary']['spend'], ''],
            ['summary', 'results', (string) $detail['summary']['results'], ''],
            ['summary', 'cpa_cpl', (string) ($detail['summary']['cpa_cpl'] ?? ''), ''],
            ['summary', 'open_alerts', (string) $detail['summary']['open_alerts'], ''],
            ['summary', 'open_recommendations', (string) $detail['summary']['open_recommendations'], ''],
        ];

        foreach ($detail['ad_sets'] as $adSet) {
            $rows[] = ['ad_set', $adSet['name'], (string) ($adSet['spend'] ?? ''), (string) ($adSet['results'] ?? '')];
        }

        foreach ($detail['ads'] as $ad) {
            $rows[] = ['ad', $ad['name'], (string) ($ad['spend'] ?? ''), (string) ($ad['results'] ?? '')];
        }

        $rows[] = ['creative_performance', 'ad_name', 'headline', 'cta', 'asset_type', 'spend', 'results', 'cpa_cpl', 'ctr', 'cpm', 'rank_label'];
        foreach ($detail['creative_performance'] ?? [] as $cp) {
            $rows[] = [
                'creative_performance',
                $cp['ad_name'],
                $cp['headline'] ?? '',
                $cp['call_to_action'] ?? '',
                $cp['asset_type'] ?? '',
                (string) ($cp['spend'] ?? ''),
                (string) ($cp['results'] ?? ''),
                (string) ($cp['cpa_cpl'] ?? ''),
                (string) ($cp['ctr'] ?? ''),
                (string) ($cp['cpm'] ?? ''),
                $cp['rank_label'] ?? '',
            ];
        }

        foreach ($detail['alerts'] as $alert) {
            $rows[] = ['alert', $alert['summary'], $alert['impact_summary'], $alert['next_step']];
        }

        foreach ($detail['recommendations'] as $recommendation) {
            $rows[] = ['recommendation', $recommendation['summary'], $recommendation['client_view']['summary'], $recommendation['operator_view']['next_test'] ?? ''];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function snapshotHistory(string $entityType, string $entityId): array
    {
        return ReportSnapshot::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'start_date', 'end_date', 'created_at'])
            ->map(fn (ReportSnapshot $snapshot): array => [
                'id' => $snapshot->id,
                'title' => $snapshot->title,
                'start_date' => $snapshot->start_date?->toDateString(),
                'end_date' => $snapshot->end_date?->toDateString(),
                'created_at' => $snapshot->created_at?->toDateTimeString(),
                'snapshot_url' => sprintf('/reports/snapshots/detail?id=%s', $snapshot->id),
                'export_csv_url' => sprintf('/api/v1/reports/snapshots/%s/export.csv', $snapshot->id),
            ])
            ->values()
            ->all();
    }
}
