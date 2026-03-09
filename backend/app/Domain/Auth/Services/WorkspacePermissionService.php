<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use App\Models\UserWorkspaceRole;

class WorkspacePermissionService
{
    public function hasPermission(User $user, string $workspaceId, string $permissionCode): bool
    {
        return UserWorkspaceRole::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->whereHas('role.permissions', function ($query) use ($permissionCode): void {
                $query->where('code', $permissionCode);
            })
            ->exists();
    }
}
