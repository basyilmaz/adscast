<?php

namespace App\Domain\Meta\Jobs;

use App\Domain\Meta\Services\MetaSyncService;
use App\Models\MetaConnection;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMetaInsightsDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $backoff = 120;

    public function __construct(
        public readonly string $connectionId,
        public readonly string $accountId,
        public readonly string $startDate,
        public readonly string $endDate,
    ) {
    }

    public function handle(MetaSyncService $syncService): void
    {
        $connection = MetaConnection::query()->findOrFail($this->connectionId);

        $syncService->runInsightsSync(
            $connection,
            $this->accountId,
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate),
        );
    }
}
