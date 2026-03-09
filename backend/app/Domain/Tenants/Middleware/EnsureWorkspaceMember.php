<?php

namespace App\Domain\Tenants\Middleware;

use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\UserWorkspaceRole;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceMember
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        if (! $user || ! $workspaceId) {
            return new JsonResponse([
                'message' => 'Workspace erisim dogrulanamadi.',
                'error_code' => 'workspace_membership_precondition_failed',
            ], 403);
        }

        $isMember = UserWorkspaceRole::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->exists();

        if (! $isMember) {
            return new JsonResponse([
                'message' => 'Bu workspace icin yetkiniz yok.',
                'error_code' => 'workspace_membership_missing',
            ], 403);
        }

        return $next($request);
    }
}
