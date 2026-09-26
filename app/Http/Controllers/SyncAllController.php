<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\CampaignStat;
use App\Models\Connection;
use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncAllController extends Controller
{
    public function index()
    {
        return view('sync-all', [
            'activePage'   => 'sync-all',
            'pageTitle'    => 'Sync All',
            'grouped'      => $this->campaignsByMonth(),
            'connections'  => Connection::where('active', true)->get(),
            'apps'         => App::with('connection')->orderBy('name')->get(),
            'lastSynced'   => CampaignStat::max('synced_at'),
            'periodStart'  => Carbon::now()->startOfMonth()->toDateString(),
            'periodEnd'    => Carbon::now()->toDateString(),
        ]);
    }

    /**
     * Campaign totals per calendar month, newest month first ("Y-m" => rows).
     * Metrics come from daily_stats (summed over days + countries); name /
     * status / type come from campaign_stats. Campaigns synced this month with
     * no spend yet (e.g. paused) still appear in the current month with zeros.
     */
    private function campaignsByMonth()
    {
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";

        $meta = CampaignStat::with('connection')->get()
            ->keyBy(fn ($s) => "{$s->connection_id}|{$s->customer_id}|{$s->campaign_id}");

        $rows = DB::table('daily_stats')
            ->selectRaw("{$monthExpr} as month, connection_id, customer_id, campaign_id,
                         MAX(campaign_name) as campaign_name, MAX(account_name) as account_name,
                         SUM(cost) as cost, SUM(conversions) as conversions,
                         SUM(conversions_value) as conversions_value,
                         SUM(impressions) as impressions, SUM(clicks) as clicks")
            ->groupByRaw("{$monthExpr}, connection_id, customer_id, campaign_id")
            ->get()
            ->map(function ($r) use ($meta) {
                $m = $meta->get("{$r->connection_id}|{$r->customer_id}|{$r->campaign_id}");
                return (new CampaignStat([
                    'month'             => $r->month,
                    'connection_id'     => $r->connection_id,
                    'customer_id'       => $r->customer_id,
                    'campaign_id'       => $r->campaign_id,
                    'campaign_name'     => $m->campaign_name ?? $r->campaign_name,
                    'account_name'      => $m->account_name ?? $r->account_name,
                    'status'            => $m->status ?? null,
                    'channel_type'      => $m->channel_type ?? null,
                    'cost'              => $r->cost,
                    'conversions'       => $r->conversions,
                    'conversions_value' => $r->conversions_value,
                    'impressions'       => $r->impressions,
                    'clicks'            => $r->clicks,
                ]))->setRelation('connection', $m->connection ?? Connection::find($r->connection_id));
            });

        // Zero-fill this month's synced campaigns that have no daily rows yet.
        $current = Carbon::now()->format('Y-m');
        $seen    = $rows->where('month', $current)
            ->map(fn ($r) => "{$r->connection_id}|{$r->customer_id}|{$r->campaign_id}")->flip();
        foreach ($meta as $key => $m) {
            if (!$seen->has($key) && $m->period_end && $m->period_end->format('Y-m') === $current) {
                $zero = $m->replicate()->fill(['cost' => 0, 'conversions' => 0, 'conversions_value' => 0, 'impressions' => 0, 'clicks' => 0]);
                $zero->month = $current;
                $rows->push($zero);
            }
        }

        return $rows->sortByDesc('cost')
            ->groupBy('month')
            ->sortKeysDesc();
    }

    public function run(Request $request, GoogleAdsService $ads)
    {
        [$start, $end] = $this->syncDateRange($request);

        $r = $ads->syncAll($start, $end);

        // Return to whichever page triggered the sync (Sync All or Ad Accounts).
        $back = redirect()->back(302, [], url('/sync-all'));

        if (!empty($r['campaigns']) || !empty($r['daily'])) {
            return $back->with('flash', sprintf(
                'Synced %d campaign(s) + %d daily row(s) for %s → %s.',
                $r['campaigns'] ?? 0, $r['daily'] ?? 0, $start, $end
            ));
        }

        // Nothing synced — explain WHY instead of a bare "no campaigns found".
        $accounts  = (int) ($r['accounts'] ?? 0);
        $probe     = (int) ($r['probe_campaigns'] ?? 0);
        $untracked = (int) ($r['skipped_untracked'] ?? 0);
        $errors    = array_slice(array_values(array_unique($r['errors'] ?? [])), 0, 3);

        if ($accounts === 0 && !empty($errors)) {
            // No account was reachable at all — surface the API error.
            return $back->with('flash_error', 'Sync failed: ' . implode(' · ', $errors));
        }

        if ($probe > 0) {
            // Campaigns exist in the accounts, but none resolved to an App on the
            // Apps page — either the App ID doesn't match the campaign's target
            // package, or the campaigns aren't App campaigns.
            $hint = $untracked > 0
                ? 'Some are App campaigns whose App ID does not match any app on your Apps page.'
                : 'None expose a matchable App ID (they may not be App campaigns).';
            $msg = sprintf(
                'Scanned %d account(s) and found %d campaign(s), but none match an app on your Apps page. %s '
                . 'Add each app with the exact App ID (target package, e.g. com.example.app) its campaigns promote, then sync again.',
                $accounts, $probe, $hint
            );
            if (!empty($errors)) {
                $msg .= ' Notes: ' . implode(' · ', $errors);
            }
            return $back->with('flash_error', $msg);
        }

        $msg = "Sync finished — no campaigns found for {$start} → {$end}.";
        if (!empty($errors)) {
            $msg .= ' Notes: ' . implode(' · ', $errors);
        }
        return $back->with('flash', $msg);
    }
}
