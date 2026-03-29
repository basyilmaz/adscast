<?php

namespace Tests\Feature;

use App\Domain\Meta\Adapters\MetaGraphV20Adapter;
use App\Models\CampaignDraft;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaDraftPublishAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_campaign_draft_rejects_non_country_location_before_meta_calls(): void
    {
        config()->set('services.meta.mode', 'live');

        Http::fake();

        [$connection, $draft] = $this->seedDraftFixture('Istanbul');

        $response = app(MetaGraphV20Adapter::class)->publishCampaignDraft($connection, $draft);

        $this->assertFalse($response['success']);
        $this->assertSame('error', $response['status']);
        $this->assertStringContainsString('ISO ulke kodu', $response['message']);

        Http::assertNothingSent();
    }

    public function test_publish_campaign_draft_normalizes_country_aliases_for_meta_targeting(): void
    {
        config()->set('services.meta.mode', 'live');
        config()->set('services.meta.graph_base_url', 'https://graph.facebook.com');

        Http::fake([
            'graph.facebook.com/v20.0/act_123456789/campaigns' => Http::response(['id' => 'meta_campaign_1'], 200),
            'graph.facebook.com/v20.0/act_123456789/adsets' => Http::response(['id' => 'meta_adset_1'], 200),
        ]);

        [$connection, $draft] = $this->seedDraftFixture('Türkiye');

        $response = app(MetaGraphV20Adapter::class)->publishCampaignDraft($connection, $draft);

        $this->assertTrue($response['success']);
        $this->assertSame('published', $response['status']);
        $this->assertSame('meta_campaign_1', data_get($response, 'meta_reference.campaign_id'));
        $this->assertSame('meta_adset_1', data_get($response, 'meta_reference.ad_set_id'));

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/adsets')) {
                return true;
            }

            $payload = $request->data();

            return is_array($payload)
                && data_get(json_decode((string) ($payload['targeting'] ?? '{}'), true), 'geo_locations.countries.0') === 'TR';
        });
    }

    public function test_publish_campaign_draft_attempts_campaign_cleanup_when_ad_set_creation_fails(): void
    {
        config()->set('services.meta.mode', 'live');
        config()->set('services.meta.graph_base_url', 'https://graph.facebook.com');

        Http::fake([
            'graph.facebook.com/v20.0/act_123456789/campaigns' => Http::response(['id' => 'meta_campaign_cleanup'], 200),
            'graph.facebook.com/v20.0/act_123456789/adsets' => Http::response([
                'error' => ['message' => 'Invalid targeting payload.'],
            ], 400),
            'graph.facebook.com/v20.0/meta_campaign_cleanup' => Http::response(['success' => true], 200),
        ]);

        [$connection, $draft] = $this->seedDraftFixture('TR');

        $response = app(MetaGraphV20Adapter::class)->publishCampaignDraft($connection, $draft);

        $this->assertFalse($response['success']);
        $this->assertSame('error', $response['status']);
        $this->assertSame('meta_campaign_cleanup', data_get($response, 'meta_reference.campaign_id'));
        $this->assertTrue((bool) data_get($response, 'cleanup.attempted'));
        $this->assertTrue((bool) data_get($response, 'cleanup.success'));
        $this->assertSame('meta_campaign_cleanup', data_get($response, 'cleanup.deleted_campaign_id'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/v20.0/meta_campaign_cleanup'));
    }

    /**
     * @return array{0: MetaConnection, 1: CampaignDraft}
     */
    private function seedDraftFixture(string $location): array
    {
        $workspace = Workspace::factory()->create();

        $connection = MetaConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'access_token_encrypted' => Crypt::encryptString('meta_test_token'),
        ]);

        $account = MetaAdAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_connection_id' => $connection->id,
            'account_id' => '123456789',
        ]);

        $draft = CampaignDraft::factory()->create([
            'workspace_id' => $workspace->id,
            'meta_ad_account_id' => $account->id,
            'objective' => 'LEADS',
            'location' => $location,
            'budget_min' => 50,
        ]);

        return [$connection, $draft->fresh(['adAccount'])];
    }
}
