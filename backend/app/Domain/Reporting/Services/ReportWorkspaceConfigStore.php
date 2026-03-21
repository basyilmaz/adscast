<?php

namespace App\Domain\Reporting\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportWorkspaceConfigStore
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(string $workspaceId, string $key): Collection
    {
        $setting = Setting::query()
            ->where('workspace_id', $workspaceId)
            ->where('key', $key)
            ->first();

        $items = $setting?->value;

        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => $item)
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function put(
        Workspace $workspace,
        string $key,
        array $items,
        ?User $actor = null,
    ): Setting {
        $setting = Setting::query()->firstOrNew([
            'workspace_id' => $workspace->id,
            'organization_id' => null,
            'key' => $key,
        ]);

        $setting->fill([
            'id' => $setting->id ?: (string) Str::uuid(),
            'value' => array_values($items),
            'is_encrypted' => false,
            'created_by' => $setting->created_by ?: $actor?->id,
        ]);

        $setting->save();

        return $setting;
    }
}
