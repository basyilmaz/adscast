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
            'super_admin@adscast.local' => ['name' => 'Platform Super Admin', 'role' => 'super_admin'],
            'agency_admin@adscast.local' => ['name' => 'Ajans Admin', 'role' => 'agency_admin'],
            'manager@adscast.local' => ['name' => 'Hesap Yoneticisi', 'role' => 'account_manager'],
            'analyst@adscast.local' => ['name' => 'Performans Analisti', 'role' => 'analyst'],
            'client@adscast.local' => ['name' => 'Musteri Izleyici', 'role' => 'client_viewer'],
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

        $superAdmin = $createdUsers['super_admin@adscast.local'];

        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'demo-agency'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Demo Agency',
                'status' => 'active',
                'created_by' => $superAdmin->id,
            ]
        );

        $workspaceMain = Workspace::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'demo-workspace'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Demo Workspace',
                'timezone' => 'Europe/Istanbul',
                'currency' => 'USD',
                'is_active' => true,
                'created_by' => $superAdmin->id,
            ]
        );

        $workspaceAlt = Workspace::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'slug' => 'client-workspace'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Client Workspace',
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
                'user_id' => $createdUsers['manager@adscast.local']->id,
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
