<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Auth\Http\Requests\LoginRequest;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Tenants\Http\Resources\WorkspaceResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return new JsonResponse([
                'message' => 'E-posta veya sifre hatali.',
                'error_code' => 'invalid_credentials',
            ], 422);
        }

        if (! $user->is_active) {
            return new JsonResponse([
                'message' => 'Hesap pasif durumda.',
                'error_code' => 'user_inactive',
            ], 403);
        }

        $tokenName = $request->string('device_name')->toString() ?: 'api-token';
        $token = $user->createToken($tokenName)->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $workspaces = $user->workspaces()->with('organization')->get();

        $this->auditLogService->log(
            actor: $user,
            action: 'login',
            targetType: 'user',
            targetId: $user->id,
            request: $request,
            metadata: [
                'device_name' => $tokenName,
            ],
        );

        return new JsonResponse([
            'token' => $token,
            'user' => UserResource::make($user),
            'workspaces' => WorkspaceResource::collection($workspaces),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $workspaces = $user->workspaces()->with('organization')->get();

        return new JsonResponse([
            'user' => UserResource::make($user),
            'workspaces' => WorkspaceResource::collection($workspaces),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        $this->auditLogService->log(
            actor: $user,
            action: 'logout',
            targetType: 'user',
            targetId: $user->id,
            request: $request,
        );

        return new JsonResponse([
            'message' => 'Cikis yapildi.',
        ]);
    }
}
