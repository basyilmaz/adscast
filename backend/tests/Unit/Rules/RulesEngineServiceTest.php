<?php

namespace Tests\Unit\Rules;

use App\Domain\Rules\Services\RulesEngineService;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\InsightDaily;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RulesEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rules_engine_generates_alerts_for_low_performance(): void
    {
        $organization = Organization::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Rules Org',
            'slug' => 'rules-org',
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'name' => 'Rules Workspace',
            'slug' => 'rules-workspace',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $connection = MetaConnection::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'provider' => 'meta',
            'api_version' => 'v20.0',
            'status' => 'active',
            'access_token_encrypted' => 'token',
        ]);

        $account = MetaAdAccount::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_rule_1',
            'name' => 'Rule Account',
            'status' => 'active',
            'is_active' => true,
        ]);

        $campaign = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_rule_1',
            'name' => 'Rule Campaign',
            'status' => 'active',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 7; $i++) {
            InsightDaily::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'level' => 'campaign',
                'entity_id' => $campaign->id,
                'entity_external_id' => $campaign->meta_campaign_id,
                'date' => Carbon::now()->subDays(14 - $i)->toDateString(),
                'spend' => 70,
                'results' => 10,
                'ctr' => 2.0,
                'cpm' => 20,
                'frequency' => 2.0,
                'impressions' => 2000,
                'clicks' => 100,
                'source' => 'meta',
            ]);
        }

        for ($i = 0; $i < 7; $i++) {
            InsightDaily::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'level' => 'campaign',
                'entity_id' => $campaign->id,
                'entity_external_id' => $campaign->meta_campaign_id,
                'date' => Carbon::now()->subDays(6 - $i)->toDateString(),
                'spend' => 150,
                'results' => 0,
                'ctr' => 1.0,
                'cpm' => 35,
                'frequency' => 3.5,
                'impressions' => 2200,
                'clicks' => 80,
                'source' => 'meta',
            ]);
        }

        $service = app(RulesEngineService::class);
        $alerts = $service->evaluateWorkspace(
            $workspace->id,
            Carbon::now()->subDays(6),
            Carbon::now(),
        );

        $codes = collect($alerts)->pluck('code')->all();

        $this->assertContains('spend_no_result', $codes);
        $this->assertContains('falling_ctr', $codes);
        $this->assertContains('rising_cpm', $codes);
    }

    public function test_sibling_performance_only_compares_ad_sets_within_same_campaign(): void
    {
        $organization = Organization::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Sibling Scope Org',
            'slug' => 'sibling-scope-org',
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'name' => 'Sibling Scope Workspace',
            'slug' => 'sibling-scope-workspace',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $connection = MetaConnection::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'provider' => 'meta',
            'api_version' => 'v20.0',
            'status' => 'active',
            'access_token_encrypted' => 'token',
        ]);

        $account = MetaAdAccount::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_sibling_1',
            'name' => 'Sibling Account',
            'status' => 'active',
            'is_active' => true,
        ]);

        $campaignA = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_sibling_a',
            'name' => 'Campaign A',
            'status' => 'active',
            'is_active' => true,
        ]);

        $campaignB = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => 'cmp_sibling_b',
            'name' => 'Campaign B',
            'status' => 'active',
            'is_active' => true,
        ]);

        $adSetA = AdSet::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaignA->id,
            'meta_ad_set_id' => 'adset_sibling_a',
            'name' => 'Ad Set A',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'optimization_goal' => 'LEAD_GENERATION',
            'billing_event' => 'IMPRESSIONS',
            'daily_budget' => 100,
        ]);

        $adSetB = AdSet::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaignB->id,
            'meta_ad_set_id' => 'adset_sibling_b',
            'name' => 'Ad Set B',
            'status' => 'active',
            'effective_status' => 'ACTIVE',
            'optimization_goal' => 'LEAD_GENERATION',
            'billing_event' => 'IMPRESSIONS',
            'daily_budget' => 100,
        ]);

        foreach ([
            [$adSetA, 320, 4],
            [$adSetB, 120, 10],
        ] as [$adSet, $spend, $results]) {
            InsightDaily::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'level' => 'adset',
                'entity_id' => $adSet->id,
                'entity_external_id' => $adSet->meta_ad_set_id,
                'date' => Carbon::now()->toDateString(),
                'spend' => $spend,
                'results' => $results,
                'ctr' => 2.0,
                'cpm' => 18,
                'frequency' => 1.2,
                'impressions' => 1000,
                'clicks' => 50,
                'source' => 'meta',
            ]);
        }

        $alerts = app(RulesEngineService::class)->evaluateWorkspace(
            $workspace->id,
            Carbon::now(),
            Carbon::now(),
        );

        $weakSiblingAlerts = collect($alerts)->filter(
            fn (array $alert): bool => $alert['code'] === 'weak_winner_loser' && $alert['entity_type'] === 'ad_set'
        );

        $this->assertCount(0, $weakSiblingAlerts);
    }
}
