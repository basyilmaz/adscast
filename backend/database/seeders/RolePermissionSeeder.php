<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'meta.manage' => 'Meta baglanti ve senkron yonetimi',
            'reporting.view' => 'Dashboard ve raporlari goruntuleme',
            'alerts.view' => 'Alert listesini goruntuleme',
            'alerts.manage' => 'Rules engine tetikleme ve alert yonetimi',
            'recommendations.view' => 'Onerileri goruntuleme',
            'recommendations.generate' => 'AI onerisi uretme',
            'drafts.view' => 'Draftlari goruntuleme',
            'drafts.manage' => 'Draft olusturma/guncelleme',
            'approvals.view' => 'Onay kuyrugu goruntuleme',
            'approvals.review' => 'Onay/ret islemleri',
            'approvals.publish' => 'Approval sonrasi publish denemesi',
            'audit.view' => 'Audit log goruntuleme',
            'settings.view' => 'Ayar goruntuleme',
            'settings.manage' => 'Ayar degistirme',
        ];

        $permissionIds = [];

        foreach ($permissions as $code => $description) {
            $permission = Permission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'id' => (string) Str::uuid(),
                    'name' => str_replace('.', ' ', $code),
                    'description' => $description,
                ]
            );

            $permissionIds[$code] = $permission->id;
        }

        $roles = [
            'super_admin' => [
                'name' => 'Super Admin',
                'scope' => 'platform',
                'permissions' => array_keys($permissions),
            ],
            'agency_admin' => [
                'name' => 'Agency Admin',
                'scope' => 'workspace',
                'permissions' => array_keys($permissions),
            ],
            'account_manager' => [
                'name' => 'Account Manager',
                'scope' => 'workspace',
                'permissions' => [
                    'meta.manage',
                    'reporting.view',
                    'alerts.view',
                    'alerts.manage',
                    'recommendations.view',
                    'recommendations.generate',
                    'drafts.view',
                    'drafts.manage',
                    'approvals.view',
                ],
            ],
            'analyst' => [
                'name' => 'Analyst',
                'scope' => 'workspace',
                'permissions' => [
                    'reporting.view',
                    'alerts.view',
                    'alerts.manage',
                    'recommendations.view',
                    'recommendations.generate',
                    'drafts.view',
                ],
            ],
            'client_viewer' => [
                'name' => 'Client Viewer',
                'scope' => 'workspace',
                'permissions' => [
                    'reporting.view',
                    'alerts.view',
                    'recommendations.view',
                    'drafts.view',
                ],
            ],
        ];

        foreach ($roles as $code => $roleData) {
            $role = Role::query()->firstOrCreate(
                ['code' => $code],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $roleData['name'],
                    'scope' => $roleData['scope'],
                    'description' => $roleData['name'],
                ]
            );

            foreach ($roleData['permissions'] as $permissionCode) {
                RolePermission::query()->firstOrCreate(
                    [
                        'role_id' => $role->id,
                        'permission_id' => $permissionIds[$permissionCode],
                    ],
                    [
                        'id' => (string) Str::uuid(),
                    ]
                );
            }
        }
    }
}
