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

class BackfillMetaInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 300;

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
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        while ($start->lte($end)) {
            $windowEnd = $start->copy()->addDays(6);
            if ($windowEnd->gt($end)) {
                $windowEnd = $end->copy();
            }

            $syncService->runInsightsSync($connection, $this->accountId, $start, $windowEnd);
            $start->addWeek();
        }
    }
}
