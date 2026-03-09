<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWorkspaceRole;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            'platform.owner@adscast.test' => ['name' => 'Platform Owner', 'role' => 'super_admin'],
            'agency.admin@adscast.test' => ['name' => 'Ajans Admin', 'role' => 'agency_admin'],
            'account.manager@adscast.test' => ['name' => 'Hesap Yoneticisi', 'role' => 'account_manager'],
            'performance.analyst@adscast.test' => ['name' => 'Performans Analisti', 'role' => 'analyst'],
            'client.viewer@adscast.test' => ['name' => 'Musteri Izleyici', 'role' => 'client_viewer'],
        ];

        $createdUsers = [];

        foreach ($users as $email => $meta) {
            $createdUsers[$email] = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $meta['name'],
                    'password' => Hash::make('Password123!'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'last_login_at' => now()->subDay(),
                ]
            );
        }

        $superAdmin = $createdUsers['platform.owner@adscast.test'];

        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'agency-main'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Agency Main',
                'status' => 'active',
                'created_by' => $superAdmin->id,
            ]
        );

        $workspaceMain = Workspace::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'operations-main'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Operations Main',
                'timezone' => 'Europe/Istanbul',
                'currency' => 'USD',
                'is_active' => true,
                'created_by' => $superAdmin->id,
            ]
        );

        $workspaceAlt = Workspace::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'client-alpha'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Client Alpha',
                'timezone' => 'Europe/Istanbul',
                'currency' => 'USD',
                'is_active' => true,
                'created_by' => $superAdmin->id,
            ]
        );

        foreach ($users as $email => $meta) {
            $user = $createdUsers[$email];
            $role = Role::query()->where('code', $meta['role'])->firstOrFail();

            UserWorkspaceRole::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'workspace_id' => $workspaceMain->id,
                    'role_id' => $role->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'assigned_by' => $superAdmin->id,
                ]
            );
        }

        $managerRoleId = Role::query()->where('code', 'account_manager')->value('id');

        UserWorkspaceRole::query()->firstOrCreate(
            [
                'user_id' => $createdUsers['account.manager@adscast.test']->id,
                'workspace_id' => $workspaceAlt->id,
                'role_id' => $managerRoleId,
            ],
            [
                'id' => (string) Str::uuid(),
                'assigned_by' => $superAdmin->id,
            ]
        );
    }
}
