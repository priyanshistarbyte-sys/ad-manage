<?php

namespace App\Console\Commands;

use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily automated Sync All. The 1 AM cron uses --scheduled, which re-syncs the
 * last N days (ending yesterday) where N is the `history_visible_days` setting
 * from the Settings page (the same value that bounds the View History page).
 * Also supports --days / --yesterday / --date for manual runs. Wire to cron via
 * the scheduler (see routes/console.php).
 */
class SyncGoogleAds extends Command
{
    protected $signature = 'ads:sync
        {--scheduled : Use the History-visible-days setting; re-sync that many trailing days ending yesterday (the daily cron)}
        {--days=7 : How many trailing days to (re)sync (ignored when --scheduled/--yesterday/--date is set)}
        {--yesterday : Sync only the previous calendar day}
        {--date= : Sync a single specific date (Y-m-d); overrides the others}';

    protected $description = 'Sync Google Ads campaign + daily/country stats into MySQL';

    public function handle(GoogleAdsService $ads): int
    {
        if ($date = $this->option('date')) {
            $start = $end = Carbon::parse($date)->toDateString();
        } elseif ($this->option('scheduled')) {
            // Cron mode: window driven by the History-visible-days setting, ending
            // yesterday. 0 means "show all" on the History page, so fall back to
            // the default window for the sync (syncing "everything" isn't sensible).
            $days = (int) getSetting('history_visible_days', (string) \App\Http\Controllers\SettingsController::HISTORY_DAYS_DEFAULT);
            if ($days < 1) {
                $days = \App\Http\Controllers\SettingsController::HISTORY_DAYS_DEFAULT;
            }
            $end   = Carbon::yesterday();
            $start = $end->copy()->subDays($days - 1)->toDateString();
            $end   = $end->toDateString();
        } elseif ($this->option('yesterday')) {
            $start = $end = Carbon::yesterday()->toDateString();
        } else {
            $days  = max(1, (int) $this->option('days'));
            $start = Carbon::now()->subDays($days - 1)->toDateString();
            $end   = Carbon::now()->toDateString();
        }

        $this->info("Syncing Google Ads {$start} → {$end} …");

        $r = $ads->syncAll($start, $end);

        $this->info(sprintf(
            'Done: %d campaign(s), %d daily row(s), %d account(s).',
            $r['campaigns'] ?? 0, $r['daily'] ?? 0, $r['accounts'] ?? 0
        ));

        foreach (array_slice(array_unique($r['errors'] ?? []), 0, 10) as $err) {
            $this->warn('  ! ' . $err);
        }

        return self::SUCCESS;
    }
}
