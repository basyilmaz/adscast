<?php

namespace App\Domain\Meta\Http\Controllers;

use App\Domain\Meta\Jobs\IngestMetaWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MetaWebhookController
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub.mode');
        $verifyToken = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        if ($mode === 'subscribe' && $verifyToken === env('META_WEBHOOK_VERIFY_TOKEN')) {
            return response((string) $challenge, 200, [
                'Content-Type' => 'text/plain',
            ]);
        }

        return new JsonResponse([
            'message' => 'Webhook dogrulama basarisiz.',
        ], 403);
    }

    public function ingest(Request $request): JsonResponse
    {
        $workspaceId = $request->string('workspace_id')->toString();

        if ($workspaceId === '') {
            Log::warning('Meta webhook payload missing workspace_id.');
            return new JsonResponse([
                'message' => 'workspace_id zorunludur.',
            ], 422);
        }

        IngestMetaWebhookJob::dispatch($workspaceId, $request->all());

        return new JsonResponse([
            'message' => 'Webhook eventi kuyruğa alindi.',
        ], 202);
    }
}
