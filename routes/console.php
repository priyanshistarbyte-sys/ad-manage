<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// ── Auto Daily Sync (Step 14) ───────────────────────────────────────
// Requires the system cron entry:  * * * * * php /path/artisan schedule:run
// Runs at 01:00 every day and syncs the previous calendar day's data.
Schedule::command('ads:sync --yesterday')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();
