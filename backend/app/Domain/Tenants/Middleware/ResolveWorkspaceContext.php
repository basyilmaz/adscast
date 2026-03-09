<?php

namespace App\Domain\Tenants\Middleware;

use App\Domain\Tenants\Support\WorkspaceContext;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspaceContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspaceId = $request->header('X-Workspace-Id')
            ?? $request->route('workspaceId')
            ?? $request->input('workspace_id');

        if (! $workspaceId) {
            return new JsonResponse([
                'message' => 'Workspace secimi zorunludur.',
                'error_code' => 'workspace_context_missing',
            ], 422);
        }

        $workspace = Workspace::query()->find($workspaceId);

        if (! $workspace) {
            return new JsonResponse([
                'message' => 'Workspace bulunamadi.',
                'error_code' => 'workspace_not_found',
            ], 404);
        }

        app(WorkspaceContext::class)->setWorkspace($workspace);

        return $next($request);
    }
}
