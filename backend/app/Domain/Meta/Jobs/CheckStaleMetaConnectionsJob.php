<?php

namespace App\Domain\Meta\Jobs;

use App\Models\MetaConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckStaleMetaConnectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $staleConnections = MetaConnection::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', Carbon::now()->subHours(24));
            })
            ->get();

        foreach ($staleConnections as $connection) {
            Log::warning('Meta connection stale detected.', [
                'connection_id' => $connection->id,
                'workspace_id' => $connection->workspace_id,
            ]);
        }
    }
}
