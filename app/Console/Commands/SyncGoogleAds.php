<?php

namespace App\Console\Commands;

use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily automated Sync All. Pulls a rolling window (default: last 7 days, to
 * catch late-attributed conversions) into daily_stats. Wire to cron via the
 * scheduler (see routes/console.php) or call directly: `php artisan ads:sync`.
 */
class SyncGoogleAds extends Command
{
    protected $signature = 'ads:sync {--days=7 : How many trailing days to (re)sync}';

    protected $description = 'Sync Google Ads campaign + daily/country stats into MySQL';

    public function handle(GoogleAdsService $ads): int
    {
        $days  = max(1, (int) $this->option('days'));
        $start = Carbon::now()->subDays($days - 1)->toDateString();
        $end   = Carbon::now()->toDateString();

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
