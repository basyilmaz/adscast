<?php

namespace App\Domain\Meta\Jobs;

use App\Domain\Meta\Services\MetaSyncService;
use App\Models\MetaConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMetaAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $connectionId,
    ) {
    }

    public function handle(MetaSyncService $syncService): void
    {
        $connection = MetaConnection::query()->findOrFail($this->connectionId);
        $syncService->runAssetSync($connection);
    }
}
