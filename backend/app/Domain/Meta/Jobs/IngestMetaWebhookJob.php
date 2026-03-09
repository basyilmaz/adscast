<?php

namespace App\Domain\Meta\Jobs;

use App\Models\RawApiPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class IngestMetaWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $workspaceId,
        public readonly array $payload,
    ) {
    }

    public function handle(): void
    {
        $serialized = json_encode($this->payload, JSON_THROW_ON_ERROR);

        RawApiPayload::query()->create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'meta_connection_id' => null,
            'sync_run_id' => null,
            'resource_type' => 'webhook_event',
            'resource_key' => $this->payload['entry'][0]['id'] ?? null,
            'payload' => $this->payload,
            'payload_hash' => hash('sha256', $serialized),
            'captured_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);
    }
}
