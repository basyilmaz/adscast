<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Services\ReportDeliveryScheduleService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class RunReportDeliveriesCommand extends Command
{
    protected $signature = 'adscast:run-report-deliveries
        {--workspace-id= : Sadece belirtilen workspace altindaki schedule kayitlarini alir}
        {--schedule-id= : Sadece belirtilen schedule kaydini calistirir}
        {--force : Due kontrolunu atlayip secilen aktif schedule kayitlarini calistirir}
        {--no-lock : Cache lock kullanmadan calistirir}';

    protected $description = 'Kaydedilmis rapor schedule kayitlari icin snapshot hazirlama ve delivery foundation run akisini calistirir.';

    public function __construct(
        private readonly ReportDeliveryScheduleService $reportDeliveryScheduleService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runner = fn (): array => $this->reportDeliveryScheduleService->runDueSchedules(
            workspaceId: $this->option('workspace-id') ?: null,
            scheduleId: $this->option('schedule-id') ?: null,
            force: (bool) $this->option('force'),
        );

        try {
            $summary = $this->option('no-lock')
                ? $runner()
                : $this->runWithLock($runner);
        } catch (LockTimeoutException) {
            $this->warn('Report delivery scheduler zaten calisiyor. Bu tetik atlandi.');

            return self::SUCCESS;
        }

        $this->table(
            ['Alan', 'Deger'],
            [
                ['schedules_considered', (string) $summary['schedules_considered']],
                ['schedules_processed', (string) $summary['schedules_processed']],
                ['schedules_failed', (string) $summary['schedules_failed']],
                ['snapshots_created', (string) $summary['snapshots_created']],
            ],
        );

        foreach ($summary['results'] as $result) {
            $this->line(sprintf(
                '[%s] schedule=%s snapshot=%s next=%s',
                $result['status'] ?? 'unknown',
                $result['schedule_id'] ?? 'n/a',
                $result['snapshot_id'] ?? '-',
                $result['next_run_at'] ?? '-',
            ));

            if (filled($result['error'] ?? null)) {
                $this->error((string) $result['error']);
            }
        }

        return $summary['schedules_failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param callable(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function runWithLock(callable $callback): array
    {
        $lock = Cache::lock(
            'adscast:run-report-deliveries',
            max(60, (int) config('services.reports.schedule.lock_seconds', 840)),
        );

        if (! $lock->get()) {
            throw new LockTimeoutException('Report delivery lock aktif.');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
