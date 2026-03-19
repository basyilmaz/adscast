<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWorkspaceRole;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BootstrapWorkspaceCommand extends Command
{
    protected $signature = 'adscast:bootstrap-workspace
        {--admin-email=admin@castintech.com : Admin e-posta adresi}
        {--admin-name=AdsCast Platform Admin : Admin gorunen adi}
        {--admin-password= : Admin sifresi}
        {--organization-name=Castintech : Organization adi}
        {--organization-slug=castintech : Organization slug}
        {--workspace-name=Castintech Main : Workspace adi}
        {--workspace-slug=castintech-main : Workspace slug}
        {--role=agency_admin : Workspace rolu}
        {--timezone=Europe/Istanbul : Workspace timezone}
        {--currency=TRY : Workspace para birimi}
        {--force : Onay istemeden calistir}';

    protected $description = 'Ilk admin, organization ve workspace kaydini idempotent sekilde olusturur.';

    public function handle(): int
    {
        $adminEmail = (string) $this->option('admin-email');
        $adminName = (string) $this->option('admin-name');
        $adminPassword = (string) ($this->option('admin-password') ?: '');
        $organizationName = (string) $this->option('organization-name');
        $organizationSlug = (string) $this->option('organization-slug');
        $workspaceName = (string) $this->option('workspace-name');
        $workspaceSlug = (string) $this->option('workspace-slug');
        $roleCode = (string) $this->option('role');
        $timezone = (string) $this->option('timezone');
        $currency = (string) $this->option('currency');

        if ($adminPassword === '') {
            $this->error('--admin-password zorunludur.');

            return self::INVALID;
        }

        $role = Role::query()->where('code', $roleCode)->first();

        if (! $role) {
            $this->error("Rol bulunamadi: {$roleCode}");

            return self::INVALID;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Bootstrap islemi admin {$adminEmail}, org {$organizationSlug}, workspace {$workspaceSlug} icin uygulansin mi?",
            true,
        )) {
            $this->warn('Islem iptal edildi.');

            return self::INVALID;
        }

        [$admin, $organization, $workspace] = DB::transaction(function () use (
            $adminEmail,
            $adminName,
            $adminPassword,
            $organizationName,
            $organizationSlug,
            $workspaceName,
            $workspaceSlug,
            $role,
            $timezone,
            $currency,
        ): array {
            $admin = User::query()->updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password' => Hash::make($adminPassword),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'last_login_at' => now(),
                ],
            );

            $organization = Organization::query()->updateOrCreate(
                ['slug' => $organizationSlug],
                [
                    'name' => $organizationName,
                    'status' => 'active',
                    'created_by' => $admin->id,
                ],
            );

            $workspace = Workspace::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => $workspaceSlug,
                ],
                [
                    'name' => $workspaceName,
                    'timezone' => $timezone,
                    'currency' => $currency,
                    'is_active' => true,
                    'created_by' => $admin->id,
                ],
            );

            UserWorkspaceRole::query()->firstOrCreate(
                [
                    'user_id' => $admin->id,
                    'workspace_id' => $workspace->id,
                    'role_id' => $role->id,
                ],
                [
                    'assigned_by' => $admin->id,
                ],
            );

            return [$admin, $organization, $workspace];
        });

        $this->table(
            ['Alan', 'Deger'],
            [
                ['admin_email', $admin->email],
                ['organization_slug', $organization->slug],
                ['workspace_slug', $workspace->slug],
                ['workspace_id', $workspace->id],
                ['role', $roleCode],
            ],
        );

        return self::SUCCESS;
    }
}
