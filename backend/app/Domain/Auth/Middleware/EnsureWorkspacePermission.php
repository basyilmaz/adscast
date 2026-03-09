<?php

namespace App\Domain\Auth\Middleware;

use App\Domain\Auth\Services\WorkspacePermissionService;
use App\Domain\Tenants\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspacePermission
{
    public function __construct(
        private readonly WorkspacePermissionService $permissionService,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $user = $request->user();
        $workspaceId = app(WorkspaceContext::class)->getWorkspaceId();

        if (! $user || ! $workspaceId) {
            return new JsonResponse([
                'message' => 'Yetki baglami olusmadi.',
                'error_code' => 'permission_context_missing',
            ], 403);
        }

        if (! $this->permissionService->hasPermission($user, $workspaceId, $permissionCode)) {
            return new JsonResponse([
                'message' => 'Bu islem icin yetkiniz bulunmuyor.',
                'error_code' => 'permission_denied',
            ], 403);
        }

        return $next($request);
    }
}
