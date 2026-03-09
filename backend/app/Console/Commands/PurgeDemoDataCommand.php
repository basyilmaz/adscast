<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class PurgeDemoDataCommand extends Command
{
    protected $signature = 'adscast:purge-demo-data
        {--dry-run : Kayitlari silmeden once etkisini gosterir}
        {--force : Onay istemeden siler}';

    protected $description = 'Gecmis demo seed verisini (legacy) kalici olarak temizler.';

    public function handle(): int
    {
        $organizationIds = Organization::query()
            ->withTrashed()
            ->where('slug', 'demo-agency')
            ->pluck('id');

        $workspaceIds = Workspace::query()
            ->withTrashed()
            ->whereIn('slug', ['demo-workspace', 'client-workspace'])
            ->orWhereIn('organization_id', $organizationIds)
            ->pluck('id')
            ->unique()
            ->values();

        $organizationIds = Organization::query()
            ->withTrashed()
            ->whereIn('id', $organizationIds)
            ->orWhereIn('id', function ($query) use ($workspaceIds) {
                $query->select('organization_id')
                    ->from('workspaces')
                    ->whereIn('id', $workspaceIds)
                    ->whereNotNull('organization_id');
            })
            ->pluck('id')
            ->unique()
            ->values();

        $userIds = User::query()
            ->withTrashed()
            ->where('email', 'like', '%@adscast.local')
            ->pluck('id')
            ->unique()
            ->values();

        $counts = $this->buildCounts($organizationIds, $workspaceIds, $userIds);

        $this->line('Bulunan legacy demo kayitlari:');
        $this->table(['Kalem', 'Adet'], collect($counts)
            ->map(fn (int $count, string $label) => [$label, $count])
            ->values()
            ->all());

        if ($this->option('dry-run')) {
            $this->info('Dry-run tamamlandi. Silme islemi uygulanmadi.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Legacy demo kayitlari kalici olarak silinsin mi?', false)) {
            $this->warn('Islem kullanici tarafindan iptal edildi.');

            return self::INVALID;
        }

        DB::transaction(function () use ($organizationIds, $workspaceIds, $userIds): void {
            if ($organizationIds->isNotEmpty() || $workspaceIds->isNotEmpty() || $userIds->isNotEmpty()) {
                AuditLog::query()
                    ->whereIn('organization_id', $organizationIds)
                    ->orWhereIn('workspace_id', $workspaceIds)
                    ->orWhereIn('actor_id', $userIds)
                    ->delete();

                Setting::query()
                    ->whereIn('organization_id', $organizationIds)
                    ->orWhereIn('workspace_id', $workspaceIds)
                    ->orWhereIn('created_by', $userIds)
                    ->delete();
            }

            if ($userIds->isNotEmpty()) {
                PersonalAccessToken::query()
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();
            }

            if ($workspaceIds->isNotEmpty()) {
                Workspace::query()
                    ->withTrashed()
                    ->whereIn('id', $workspaceIds)
                    ->get()
                    ->each
                    ->forceDelete();
            }

            if ($organizationIds->isNotEmpty()) {
                Organization::query()
                    ->withTrashed()
                    ->whereIn('id', $organizationIds)
                    ->get()
                    ->each
                    ->forceDelete();
            }

            if ($userIds->isNotEmpty()) {
                User::query()
                    ->withTrashed()
                    ->whereIn('id', $userIds)
                    ->get()
                    ->each
                    ->forceDelete();
            }
        });

        $this->info('Legacy demo verisi kalici olarak temizlendi.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function buildCounts(Collection $organizationIds, Collection $workspaceIds, Collection $userIds): array
    {
        return [
            'organizations' => Organization::query()->withTrashed()->whereIn('id', $organizationIds)->count(),
            'workspaces' => Workspace::query()->withTrashed()->whereIn('id', $workspaceIds)->count(),
            'users' => User::query()->withTrashed()->whereIn('id', $userIds)->count(),
            'personal_access_tokens' => PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->count(),
            'audit_logs' => AuditLog::query()
                ->whereIn('organization_id', $organizationIds)
                ->orWhereIn('workspace_id', $workspaceIds)
                ->orWhereIn('actor_id', $userIds)
                ->count(),
            'settings' => Setting::query()
                ->whereIn('organization_id', $organizationIds)
                ->orWhereIn('workspace_id', $workspaceIds)
                ->orWhereIn('created_by', $userIds)
                ->count(),
        ];
    }
}
