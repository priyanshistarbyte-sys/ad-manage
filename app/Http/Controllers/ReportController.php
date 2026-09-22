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

        // daily_stat_history has no app_id column, so scope by the app's campaigns
        // (resolved from daily_stats) — keeps history consistent with the app the
        // dashboard was filtered to.
        $appCampaignIds = null;
        if ($appId !== null && $appId !== '') {
            $appCampaignIds = DailyStat::where('app_id', $appId)
                ->distinct()
                ->pluck('campaign_id')
                ->all();
        }

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
                ->when($appCampaignIds !== null, fn ($q) => $q->whereIn('campaign_id', $appCampaignIds ?: ['__none__']))
                ->when($geoId !== null && $geoId !== '', fn ($q) => $q->where('geo_id', (int) $geoId))
                ->orderBy('captured_at')
                ->limit(5000)
                ->get();

            // Each Sync All writes one snapshot (a captured_at) holding many
            // per-campaign/geo rows for the day. Sum those rows into one total
            // per sync run, so we compare like-with-like across runs.
            $perRun = $rows
                ->groupBy(fn ($h) => (string) $h->captured_at)
                ->map(function ($group) {
                    $sum = fn ($col) => (float) $group->sum($col);
                    $cost = $sum('cost');
                    $totalRev = $sum('total_rev');
                    return (object) [
                        'captured_at' => $group->first()->captured_at,
                        'cost'        => $cost,
                        'total_rev'   => $totalRev,
                        'troas'       => $cost > 0 ? $totalRev / $cost * 100 : 0,
                        'install'     => $sum('install'),
                        'trial'       => $sum('trial'),
                        'ad_rev'      => $sum('ad_rev'),
                        'convert_rev' => $sum('convert_rev'),
                        'renew_rev'   => $sum('renew_rev'),
                        'rows'        => $group->count(),
                    ];
                });

            // Collapse to one row per capture DAY — the latest sync of that day —
            // so the history reads date-wise: the selected date's value as it
            // stood on each day since (e.g. Sep 2's numbers as seen on 2, 3 … 22).
            $snapshots = $perRun
                ->groupBy(fn ($s) => Carbon::parse($s->captured_at)->format('Y-m-d'))
                ->map(fn ($runs) => $runs->sortByDesc('captured_at')->first())
                ->sortKeys();

            // Day-over-day change in total revenue (how much Google restated the
            // number since the previous capture day). First row has no baseline.
            $prevRev = null;
            $snapshots = $snapshots->map(function ($s) use (&$prevRev) {
                $s->delta_rev = $prevRev === null ? null : $s->total_rev - $prevRev;
                $prevRev = $s->total_rev;
                return $s;
            });
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
