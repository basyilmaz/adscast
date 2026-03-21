<?php

namespace Tests\Feature\Reporting;

use App\Models\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportContactBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_contacts_can_be_created_listed_updated_toggled_and_deleted(): void
    {
        [$workspace, $token] = $this->bootstrapWorkspaceContext();

        $storeResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson('/api/v1/reports/contacts', [
                'name' => 'Merve Kaya',
                'email' => 'merve@castintech.com',
                'company_name' => 'Castintech',
                'role_label' => 'Marka Yoneticisi',
                'tags' => ['musteri', 'haftalik'],
                'notes' => 'Haftalik raporun ilk alicisi.',
                'is_primary' => true,
            ]);

        $contactId = $storeResponse->json('data.id');

        $storeResponse->assertCreated()
            ->assertJsonPath('data.name', 'Merve Kaya')
            ->assertJsonPath('data.email', 'merve@castintech.com')
            ->assertJsonPath('data.is_primary', true);

        $indexResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexResponse->assertOk()
            ->assertJsonPath('data.contact_summary.total_contacts', 1)
            ->assertJsonPath('data.contact_summary.primary_contacts', 1)
            ->assertJsonPath('data.contact_segment_summary.total_segments', 2)
            ->assertJsonPath('data.contacts.0.id', $contactId)
            ->assertJsonPath('data.contacts.0.tags.0', 'musteri')
            ->assertJsonPath('data.contact_segments.0.tag', 'haftalik');

        $updateResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->putJson("/api/v1/reports/contacts/{$contactId}", [
                'name' => 'Merve Kaya Guncel',
                'email' => 'merve@castintech.com',
                'company_name' => 'Castintech',
                'role_label' => 'CMO',
                'tags' => ['musteri', 'aylik'],
                'notes' => 'Aylik rapora da eklendi.',
                'is_primary' => false,
                'is_active' => true,
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Merve Kaya Guncel')
            ->assertJsonPath('data.role_label', 'CMO')
            ->assertJsonPath('data.is_primary', false);

        $toggleResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->postJson("/api/v1/reports/contacts/{$contactId}/toggle", [
                'is_active' => false,
            ]);

        $toggleResponse->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->deleteJson("/api/v1/reports/contacts/{$contactId}")
            ->assertOk();

        $this->assertDatabaseMissing('report_contacts', [
            'id' => $contactId,
        ]);

        $indexAfterDelete = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $workspace->id)
            ->getJson('/api/v1/reports');

        $indexAfterDelete->assertOk()
            ->assertJsonPath('data.contact_summary.total_contacts', 0)
            ->assertJsonPath('data.contact_segment_summary.total_segments', 0)
            ->assertJsonPath('data.contacts', []);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_contact_created',
            'target_id' => $contactId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_contact_updated',
            'target_id' => $contactId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_contact_toggled',
            'target_id' => $contactId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'report_contact_deleted',
            'target_id' => $contactId,
        ]);
    }

    /**
     * @return array{0: Workspace, 1: string}
     */
    private function bootstrapWorkspaceContext(): array
    {
        $this->seed([
            RolePermissionSeeder::class,
            TenantSeeder::class,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'agency.admin@adscast.test',
            'password' => 'Password123!',
            'device_name' => 'phpunit',
        ]);

        $token = $loginResponse->json('token');
        $workspaceId = $loginResponse->json('workspaces.0.id');

        return [Workspace::query()->findOrFail($workspaceId), $token];
    }
}
