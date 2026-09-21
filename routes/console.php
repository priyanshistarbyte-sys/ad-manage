<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// ── Auto Daily Sync (Step 14) ───────────────────────────────────────
// Requires the system cron entry:  * * * * * php /path/artisan schedule:run
Schedule::command('ads:sync --days=7')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();
