<?php

namespace App\Domain\Meta\Jobs;

use App\Models\MetaConnection;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshMetaTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 120;

    public function __construct(
        public readonly string $connectionId,
    ) {
    }

    public function handle(): void
    {
        $connection = MetaConnection::query()->findOrFail($this->connectionId);

        // TODO: Gercek token refresh cagrisi Meta OAuth exchange ile yapilacak.
        $connection->forceFill([
            'token_expires_at' => Carbon::now()->addDays(55),
        ])->save();

        Log::info('Meta token refresh stub executed.', [
            'connection_id' => $connection->id,
        ]);
    }
}
