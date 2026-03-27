<?php

namespace Tests\Feature\Domain\Meta;

use App\Models\MetaConnection;
use App\Models\Organization;
use App\Models\Workspace;
use App\Models\Campaign;
use App\Domain\Meta\Contracts\MetaApiAdapter;
use App\Domain\Meta\Adapters\MetaGraphV20Adapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaSyncMockTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_adapter_returns_expected_data()
    {
        $organization = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $workspace = Workspace::create(['organization_id' => $organization->id, 'name' => 'Test Workspace', 'slug' => 'test-workspace']);

        $connection = MetaConnection::create([
            'workspace_id' => $workspace->id,
            'name' => 'Mock Connection',
            'meta_business_id' => 'bus_123',
            'api_version' => 'v20.0',
            'access_token_encrypted' => encrypt('fake_token'),
        ]);

        $adapter = app(MetaApiAdapter::class);
        $this->assertInstanceOf(MetaGraphV20Adapter::class, $adapter);

        $accounts = $adapter->listAdAccounts($connection);
        $this->assertCount(1, $accounts);
        $this->assertEquals('act_1001', $accounts[0]['account_id']);

        $campaigns = $adapter->syncCampaigns($connection, 'act_1001');
        $this->assertCount(1, $campaigns);
        
        $campaignData = $campaigns[0];
        $account = \App\Models\MetaAdAccount::create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_1001',
            'name' => 'Test Account',
            'status' => 'active'
        ]);

        Campaign::create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'meta_campaign_id' => $campaignData['meta_campaign_id'],
            'name' => $campaignData['name'],
            'objective' => $campaignData['objective'],
            'status' => $campaignData['status'],
        ]);

        $this->assertDatabaseHas('campaigns', [
            'meta_campaign_id' => 'cmp_1001',
            'name' => 'Ilk Performans Kampanyasi',
        ]);
    }
}
