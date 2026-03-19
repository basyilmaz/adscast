<?php

namespace App\Domain\Meta\Http\Controllers;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Meta\Http\Requests\ExchangeMetaOAuthCodeRequest;
use App\Domain\Meta\Http\Resources\MetaConnectionResource;
use App\Domain\Meta\Services\MetaOAuthService;
use App\Domain\Tenants\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaOAuthController
{
    public function __construct(
        private readonly MetaOAuthService $oauthService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function start(Request $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $user = $request->user();

        $payload = $this->oauthService->start($user, $workspace);

        $this->auditLogService->log(
            actor: $user,
            action: 'connection_oauth_started',
            targetType: 'workspace',
            targetId: $workspace->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'provider' => 'meta',
                'api_version' => $payload['api_version'],
                'scopes' => $payload['scopes'],
            ],
        );

        return new JsonResponse([
            'message' => 'Meta OAuth baslatildi.',
            'data' => $payload,
        ]);
    }

    public function exchange(ExchangeMetaOAuthCodeRequest $request): JsonResponse
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();
        $user = $request->user();

        $connection = $this->oauthService->exchangeAuthorizationCode(
            $user,
            $workspace,
            $request->string('code')->toString(),
            $request->string('state')->toString(),
        );

        $this->auditLogService->log(
            actor: $user,
            action: 'connection_created_or_refreshed',
            targetType: 'meta_connection',
            targetId: $connection->id,
            organizationId: $workspace->organization_id,
            workspaceId: $workspace->id,
            request: $request,
            metadata: [
                'provider' => 'meta',
                'api_version' => $connection->api_version,
                'connection_source' => 'oauth',
            ],
        );

        return new JsonResponse([
            'message' => 'Meta OAuth baglantisi tamamlandi.',
            'data' => MetaConnectionResource::make($connection->loadCount(['adAccounts', 'pages', 'pixels', 'businesses'])),
        ], 201);
    }
}
