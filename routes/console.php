<?php

use App\Jobs\ExpireStaleInvoices;
use App\Jobs\SnapshotDailyBalances;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled Jobs ───────────────────────────────────────────────────────
Schedule::job(new SnapshotDailyBalances())->dailyAt('00:00');
Schedule::job(new ExpireStaleInvoices())->dailyAt('01:00');
