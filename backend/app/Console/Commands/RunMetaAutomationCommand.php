<?php

namespace App\Console\Commands;

use App\Domain\Meta\Services\MetaAutomationService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class RunMetaAutomationCommand extends Command
{
    protected $signature = 'adscast:run-meta-automation
        {--workspace-id= : Sadece belirtilen workspace icin calisir}
        {--connection-id= : Sadece belirtilen Meta connection icin calisir}
        {--only=assets,insights,rules,recommendations : Calisacak adimlarin csv listesi}
        {--force : Cadence kontrolunu atlayip tum adimlari calistirir}
        {--no-lock : Cache lock kullanmadan calistirir}';

    protected $description = 'Meta asset/insight sync, rules evaluation ve recommendation generation adimlarini zamanlanmis sekilde calistirir.';

    public function __construct(
        private readonly MetaAutomationService $automationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $steps = array_values(array_filter(array_map(
            static fn (string $step): string => trim($step),
            explode(',', (string) $this->option('only'))
        )));

        $runner = fn (): array => $this->automationService->run(
            workspaceId: $this->option('workspace-id') ?: null,
            connectionId: $this->option('connection-id') ?: null,
            steps: $steps,
            force: (bool) $this->option('force'),
        );

        try {
            $summary = $this->option('no-lock')
                ? $runner()
                : $this->runWithLock($runner);
        } catch (LockTimeoutException) {
            $this->warn('Meta automation zaten calisiyor. Bu tetik atlandi.');

            return self::SUCCESS;
        }

        $this->table(
            ['Alan', 'Deger'],
            [
                ['connections_considered', (string) $summary['connections_considered']],
                ['connections_processed', (string) $summary['connections_processed']],
                ['connections_failed', (string) $summary['connections_failed']],
                ['asset_sync_runs', (string) $summary['asset_sync_runs']],
                ['insights_sync_runs', (string) $summary['insights_sync_runs']],
                ['rules_evaluations', (string) $summary['rules_evaluations']],
                ['recommendation_generations', (string) $summary['recommendation_generations']],
            ],
        );

        foreach ($summary['results'] as $result) {
            $this->line(sprintf(
                '[%s] workspace=%s asset=%d insights=%d rules=%d ai=%d skipped=%s',
                $result['status'] ?? 'unknown',
                $result['workspace_slug'] ?? $result['workspace_id'] ?? 'n/a',
                (int) ($result['asset_sync_runs'] ?? 0),
                (int) ($result['insights_sync_runs'] ?? 0),
                (int) ($result['rules_evaluations'] ?? 0),
                (int) ($result['recommendation_generations'] ?? 0),
                implode('|', $result['skipped_steps'] ?? []),
            ));

            if (filled($result['error'] ?? null)) {
                $this->error((string) $result['error']);
            }
        }

        return $summary['connections_failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function runWithLock(callable $callback): array
    {
        $lock = Cache::lock(
            'adscast:run-meta-automation',
            max(60, (int) config('services.meta.schedule.lock_seconds', 3300)),
        );

        if (! $lock->get()) {
            throw new LockTimeoutException('Meta automation lock aktif.');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
