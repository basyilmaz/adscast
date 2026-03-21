<?php

use App\Domain\Meta\Jobs\CheckStaleMetaConnectionsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

if (config('services.meta.schedule.enabled', true)) {
    Schedule::command('adscast:run-meta-automation')
        ->hourlyAt(5)
        ->withoutOverlapping(max(60, (int) ceil(config('services.meta.schedule.lock_seconds', 3300) / 60)))
        ->name('adscast-meta-automation');
}

if (config('services.reports.schedule.enabled', true)) {
    Schedule::command('adscast:run-report-deliveries')
        ->everyFifteenMinutes()
        ->withoutOverlapping(max(60, (int) ceil(config('services.reports.schedule.lock_seconds', 840) / 60)))
        ->name('adscast-report-deliveries');
}

Schedule::job(new CheckStaleMetaConnectionsJob())->hourlyAt(35);
