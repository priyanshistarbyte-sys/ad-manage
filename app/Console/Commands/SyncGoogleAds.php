<?php

namespace App\Console\Commands;

use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily automated Sync All. By default pulls a rolling window (last 7 days, to
 * catch late-attributed conversions) into daily_stats. With --yesterday it syncs
 * only the previous calendar day (used by the 1 AM cron). Wire to cron via the
 * scheduler (see routes/console.php) or call directly: `php artisan ads:sync`.
 */
class SyncGoogleAds extends Command
{
    protected $signature = 'ads:sync
        {--days=7 : How many trailing days to (re)sync (ignored when --yesterday/--date is set)}
        {--yesterday : Sync only the previous calendar day}
        {--date= : Sync a single specific date (Y-m-d); overrides --days and --yesterday}';

    protected $description = 'Sync Google Ads campaign + daily/country stats into MySQL';

    public function handle(GoogleAdsService $ads): int
    {
        if ($date = $this->option('date')) {
            $start = $end = Carbon::parse($date)->toDateString();
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
