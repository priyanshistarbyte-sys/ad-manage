<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\DailyStat;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    /** The report now lives on the Dashboard — keep this URL working by redirecting
     *  there with the same filters. */
    public function index(Request $request)
    {
        return redirect('/' . ($request->getQueryString() ? '?' . $request->getQueryString() : ''));
    }

    /** Per-country breakdown honouring the same filters (+ optional single date). */
    public function country(Request $request)
    {
        $f = $this->reports->filters($request);

        // A single-date drill-in from the report's "Country Report" link.
        if ($date = $request->query('date')) {
            $f['from'] = $f['to'] = $date;
        }

        return view($request->boolean('partial') ? 'report._country' : 'report.country', [
            'activePage'  => 'report',
            'pageTitle'   => 'Country Report',
            'filters'     => $f,
            'options'     => $this->reports->filterOptions(),
            'countryRows' => $this->reports->countryRows($f),
            'totals'      => $this->reports->totals($f),
            'singleDate'  => $date,
        ]);
    }

    /** Sync-over-time history for a specific date (optionally campaign/country). */
    public function history(Request $request)
    {
        $date       = $request->query('date');
        $appId      = $request->query('app_id');
        $campaignId = $request->query('campaign_id');
        $geoId      = $request->query('geo_id');

        // daily_stat_history has no app_id column, so scope by campaigns resolved
        // from daily_stats — the chosen app's, or every tracked app's (the
        // dashboard hides untracked rows, so history must too).
        $appCampaignIds = DailyStat::query()
            ->when($appId !== null && $appId !== '', fn ($q) => $q->where('app_id', $appId), fn ($q) => $q->whereNotNull('app_id'))
            ->distinct()
            ->pluck('campaign_id')
            ->all();

        // How many capture-days of history to show (0 = all). Storage is never
        // touched — this only bounds the query window, which also keeps the page
        // fast as the table grows. Configurable on the Settings page.
        $visibleDays = (int) getSetting('history_visible_days', (string) \App\Http\Controllers\SettingsController::HISTORY_DAYS_DEFAULT);
        $since = $visibleDays > 0 ? Carbon::today()->subDays($visibleDays)->startOfDay() : null;

        $snapshots = collect();
        if ($date) {
            $rows = DailyStat::query()->getConnection()->table('daily_stat_history')
                ->where('date', $date) // plain '=' uses the (date, campaign_id, geo_id) index; whereDate() would wrap it in DATE() and skip it
                ->when($since, fn ($q) => $q->where('captured_at', '>=', $since))
                ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
                ->whereIn('campaign_id', $appCampaignIds ?: ['__none__'])
                ->when($geoId !== null && $geoId !== '', fn ($q) => $q->where('geo_id', (int) $geoId))
                ->orderBy('captured_at')
                ->limit(50000)
                ->get();

            // One row per capture DAY, so the history reads date-wise: the selected
            // date's value as it stood on each day since. Every ad account is synced
            // separately (its own captured_at), so for each day take the latest
            // snapshot of every account/campaign/geo row and sum those — not just
            // the day's last run, which would only cover one account.
            $snapshots = $rows
                ->groupBy(fn ($h) => Carbon::parse($h->captured_at)->format('Y-m-d'))
                ->map(function ($dayRows) {
                    $latest = $dayRows
                        ->groupBy(fn ($h) => "{$h->connection_id}|{$h->customer_id}|{$h->campaign_id}|{$h->geo_id}")
                        ->map(fn ($g) => $g->last()); // rows are ordered by captured_at
                    $sum = fn ($col) => (float) $latest->sum($col);
                    $cost = $sum('cost');
                    // Same rules as the report: AD_REV = Conv. Value, CONVERT_REV
                    // excluded (0), TOTAL_REV = AD_REV + RENEW_REV, TROAS = Conv.
                    // Value ÷ Cost. Snapshots taken before Conv. Value was stored
                    // have it NULL — revenue is unknown there, so report null (the
                    // view shows "not recorded") instead of a misleading 0%.
                    $known    = $latest->every(fn ($h) => $h->conversions_value !== null);
                    $adRev    = $known ? $sum('conversions_value') : null;
                    $renewRev = $sum('renew_rev');
                    return (object) [
                        'captured_at' => $dayRows->last()->captured_at,
                        'cost'        => $cost,
                        'total_rev'   => $known ? $adRev + $renewRev : null,
                        'troas'       => $known ? ($cost > 0 ? $adRev / $cost * 100 : 0) : null,
                        'install'     => $sum('install'),
                        'trial'       => $sum('trial'),
                        'ad_rev'      => $adRev,
                        'convert_rev' => 0.0,
                        'renew_rev'   => $renewRev,
                        'rows'        => $latest->count(),
                    ];
                })
                ->sortKeys();
        }

        $appName = null;
        if ($appId !== null && $appId !== '') {
            $appName = \App\Models\App::find($appId)?->name;
        }

        return view($request->boolean('partial') ? 'report._history' : 'report.history', [
            'activePage' => 'report',
            'pageTitle'  => 'View History',
            'date'       => $date,
            'campaignId' => $campaignId,
            'geoId'      => $geoId,
            'geoName'    => ($geoId !== null && $geoId !== '') ? Country::nameFor((int) $geoId) : null,
            'appName'    => $appName,
            'snapshots'  => $snapshots,
        ]);
    }

    /** Download the current filtered report as a proper .csv file. */
    public function export(Request $request): StreamedResponse
    {
        $f       = $this->reports->filters($request);
        $rows    = $this->reports->dailyRows($f);
        $totals  = $this->reports->totals($f);
        $ranking = $this->reports->countryRanking($f);

        $filename = 'report_' . $f['from'] . '_to_' . $f['to'] . '.csv';

        $money = fn ($v) => number_format((float) $v, 2, '.', '');
        $num   = fn ($v) => number_format((float) $v, 0, '.', '');
        $pct   = fn ($v) => number_format((float) $v, 0) . '%';

        return response()->streamDownload(function () use ($rows, $totals, $ranking, $money, $num, $pct) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens accented country names correctly.
            fprintf($out, "\xEF\xBB\xBF");

            // Main daily table.
            fputcsv($out, [
                'DATE', 'COST', 'TROAS', 'TOTAL_REV', 'AD_REV', 'CONVERT_REV', 'RENEW_REV',
                'TRIAL', 'TRIAL_CONVERT_PERC', 'REPEAT_COUNT', 'INSTALL', 'COST_PER_INSTALL',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    \Carbon\Carbon::parse($r->date)->format('d-m-Y'),
                    $money($r->cost), $pct($r->troas), $money($r->total_rev),
                    $money($r->ad_rev), $money($r->convert_rev), $money($r->renew_rev),
                    $num($r->trial), $pct($r->trial_convert_perc), $num($r->repeat_count),
                    $num($r->install), $money($r->cpi),
                ]);
            }
            fputcsv($out, [
                'TOTAL', $money($totals->cost), $pct($totals->troas), $money($totals->total_rev),
                $money($totals->ad_rev), $money($totals->convert_rev), $money($totals->renew_rev),
                $num($totals->trial), $pct($totals->trial_convert_perc), $num($totals->repeat_count),
                $num($totals->install), $money($totals->cpi),
            ]);

            // Country rankings.
            foreach (['profit' => 'PROFIT_COUNTRIES (TROAS) - top 5', 'loss' => 'LOSS_COUNTRIES (TROAS) - top 5'] as $key => $title) {
                fputcsv($out, []);
                fputcsv($out, [$title]);
                fputcsv($out, ['Country', 'Cost', 'Total Rev', 'TROAS']);
                foreach ($ranking[$key] as $c) {
                    fputcsv($out, [$c->country_name, $money($c->cost), $money($c->total_rev), $pct($c->troas)]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
