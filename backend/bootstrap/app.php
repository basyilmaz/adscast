<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Auth\Middleware\EnsureWorkspacePermission;
use App\Domain\Tenants\Middleware\EnsureWorkspaceMember;
use App\Domain\Tenants\Middleware\ResolveWorkspaceContext;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->alias([
            'workspace.resolve' => ResolveWorkspaceContext::class,
            'workspace.member' => EnsureWorkspaceMember::class,
            'workspace.permission' => EnsureWorkspacePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Kimlik dogrulamasi gerekli.',
                    'error_code' => 'unauthenticated',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return null;
        });
    })->create();
