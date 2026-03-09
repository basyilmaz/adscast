<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingController
{
    public function index(): JsonResponse
    {
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        $settings = Setting::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('key')
            ->get([
                'id',
                'key',
                'value',
                'is_encrypted',
                'updated_at',
            ]);

        return new JsonResponse([
            'data' => $settings,
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:191'],
            'value' => ['nullable', 'array'],
            'is_encrypted' => ['nullable', 'boolean'],
        ]);

        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();
        $user = $request->user();

        $setting = Setting::query()->firstOrNew([
            'workspace_id' => $workspaceId,
            'organization_id' => null,
            'key' => $validated['key'],
        ]);

        $setting->fill([
            'id' => $setting->id ?: (string) Str::uuid(),
            'value' => $validated['value'] ?? null,
            'is_encrypted' => (bool) ($validated['is_encrypted'] ?? false),
            'created_by' => $user?->id,
        ]);

        $setting->save();

        return new JsonResponse([
            'message' => 'Ayar kaydedildi.',
            'data' => $setting,
        ]);
    }
}
