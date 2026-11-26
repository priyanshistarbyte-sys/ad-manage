<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// ── Auto Daily Sync (Step 14) ───────────────────────────────────────
// Requires the system cron entry:  * * * * * php /path/artisan schedule:run
// Runs at 01:00 every day. --scheduled re-syncs the last N days (ending
// yesterday), where N is the `sync_days` setting on the Settings page.
Schedule::command('ads:sync --scheduled')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();
