<?php

namespace App\Services;

use App\Models\App;
use App\Models\Country;
use App\Models\DailyStat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Turns the raw daily_stats grain into the Excel-style report: daily aggregate
 * rows, a grand total, and country TROAS rankings — all honouring the App /
 * Date / Campaign / Country filters. Shared by the report page, country report,
 * dashboard and Excel export so every surface agrees.
 */
class ReportService
{
    /** Raw SUM() columns pulled from daily_stats before deriving TROAS/CPI/etc. */
    private const SUMS = [
        'cost', 'impressions', 'clicks', 'conversions', 'conversions_value',
        'install', 'trial', 'trial_convert', 'repeat_count',
        'ad_rev', 'convert_rev', 'renew_rev',
    ];

    /** Normalise request filters into a plain array with sane date defaults. */
    public function filters(Request $request): array
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        // Default window: the span present in the data, else current month.
        if (!$from || !$to) {
            $min = DailyStat::min('date');
            $max = DailyStat::max('date');
            $from = $from ?: ($min ?: Carbon::now()->startOfMonth()->toDateString());
            $to   = $to   ?: ($max ?: Carbon::now()->toDateString());
        }

        return [
            'app_id'      => $request->query('app_id') ?: null,
            'campaign_id' => $request->query('campaign_id') ?: null,
            'geo_id'      => $request->query('geo_id') !== null && $request->query('geo_id') !== ''
                                ? (int) $request->query('geo_id') : null,
            'from'        => $from,
            'to'          => $to,
        ];
    }

    public function baseQuery(array $f): Builder
    {
        // Only surface data for apps added in this panel — rows synced from the
        // account that don't match a tracked App (app_id null) are hidden.
        return DailyStat::query()
            ->whereNotNull('app_id')
            ->when($f['app_id'],      fn ($q, $v) => $q->where('app_id', $v))
            ->when($f['campaign_id'], fn ($q, $v) => $q->where('campaign_id', $v))
            ->when($f['geo_id'] !== null, fn ($q) => $q->where('geo_id', $f['geo_id']))
            ->when($f['from'],        fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($f['to'],          fn ($q, $v) => $q->whereDate('date', '<=', $v));
    }

    /** One aggregated row per DATE (the Excel main table), newest date first? No — ascending. */
    public function dailyRows(array $f): Collection
    {
        return $this->baseQuery($f)
            ->selectRaw('date, ' . $this->sumSelect())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => $this->derive($r));
    }

    /** Grand total across the whole filtered set (the TOTAL row). */
    public function totals(array $f): object
    {
        $row = $this->baseQuery($f)->selectRaw($this->sumSelect())->first();
        return $this->derive($row ?? (object) []);
    }

    /**
     * Country TROAS ranking. Returns ['profit' => top N by TROAS desc,
     * 'loss' => bottom N by TROAS asc], only countries with spend.
     */
    public function countryRanking(array $f, int $limit = 5): array
    {
        $rows = $this->baseQuery($f)
            ->selectRaw('geo_id, ' . $this->sumSelect())
            ->groupBy('geo_id')
            ->get()
            ->map(function ($r) {
                $d = $this->derive($r);
                $d->geo_id       = $r->geo_id;
                $d->country_name = Country::nameFor($r->geo_id ? (int) $r->geo_id : null);
                $d->country_code = Country::codeFor($r->geo_id ? (int) $r->geo_id : null);
                return $d;
            })
            ->filter(fn ($d) => $d->cost > 0);

        $profit = $rows->sortByDesc('troas')->take($limit)->values();
        $loss   = $rows->sortBy('troas')->take($limit)->values();

        return ['profit' => $profit, 'loss' => $loss];
    }

    /** Per-country rows for the Country Report page (all countries, TROAS desc). */
    public function countryRows(array $f): Collection
    {
        return $this->baseQuery($f)
            ->selectRaw('geo_id, ' . $this->sumSelect())
            ->groupBy('geo_id')
            ->get()
            ->map(function ($r) {
                $d = $this->derive($r);
                $d->geo_id       = $r->geo_id ? (int) $r->geo_id : null;
                $d->country_name = Country::nameFor($d->geo_id);
                $d->country_code = Country::codeFor($d->geo_id);
                return $d;
            })
            ->sortByDesc('troas')
            ->values();
    }

    /** Dropdown option data for the filter bar. */
    public function filterOptions(): array
    {
        $campaigns = DailyStat::query()
            ->whereNotNull('app_id')
            ->selectRaw('MAX(campaign_name) as campaign_name, campaign_id')
            ->groupBy('campaign_id')
            ->orderBy('campaign_name')
            ->get();

        $geoIds = DailyStat::query()->whereNotNull('app_id')
            ->whereNotNull('geo_id')->distinct()->pluck('geo_id');
        $countries = $geoIds
            ->map(fn ($id) => (object) ['geo_id' => (int) $id, 'name' => Country::nameFor((int) $id)])
            ->sortBy('name')->values();

        return [
            'apps'      => App::orderBy('name')->get(['id', 'name']),
            'campaigns' => $campaigns,
            'countries' => $countries,
        ];
    }

    /**
     * Loss analytics (Analytics page): for every app, the countries losing money —
     * cost > $minCost and TROAS < $maxTroas — with Cost / TROAS / Loss for three
     * windows: the picked range $from–$to (often a single day), and the last 30
     * and last 90 days ending on $to. A country is listed when it breaches the thresholds in the window
     * chosen by $basis ('all' = every one of the three windows [default],
     * 'date' | '30' | '90' = that window only, 'any' = at least one).
     * Loss = Cost − Conv. Value (negative = profit), matching the TROAS definition.
     */
    public function lossAnalytics(string $from, string $to, float $minCost = 100, float $maxTroas = 100, string $basis = 'all'): array
    {
        $end    = Carbon::parse($to)->toDateString();
        $start  = min(Carbon::parse($from)->toDateString(), $end);
        $from30 = Carbon::parse($end)->subDays(29)->toDateString();
        $from90 = Carbon::parse($end)->subDays(89)->toDateString();

        // 'date' window = the picked range (one day or many); 30/90 days end on its last day.
        $rows = DailyStat::query()
            ->whereNotNull('app_id')
            ->whereBetween('date', [min($start, $from90), $end])
            ->selectRaw('app_id, geo_id,
                COALESCE(SUM(CASE WHEN date >= ? THEN cost              ELSE 0 END),0) as d_cost,
                COALESCE(SUM(CASE WHEN date >= ? THEN conversions_value ELSE 0 END),0) as d_val,
                COALESCE(SUM(CASE WHEN date >= ? THEN cost              ELSE 0 END),0) as m_cost,
                COALESCE(SUM(CASE WHEN date >= ? THEN conversions_value ELSE 0 END),0) as m_val,
                COALESCE(SUM(CASE WHEN date >= ? THEN cost              ELSE 0 END),0) as q_cost,
                COALESCE(SUM(CASE WHEN date >= ? THEN conversions_value ELSE 0 END),0) as q_val',
                [$start, $start, $from30, $from30, $from90, $from90])
            ->groupBy('app_id', 'geo_id')
            ->get();

        $period = function (float $cost, float $val) use ($minCost, $maxTroas): object {
            $troas = $cost > 0 ? $val / $cost * 100 : 0;
            return (object) [
                'cost'  => $cost,
                'value' => $val,
                'troas' => $troas,
                'loss'  => $cost - $val,
                'flag'  => $cost > $minCost && $troas < $maxTroas,
            ];
        };
        $sumPeriods = function (Collection $countries, string $key) use ($period): object {
            return $period(
                (float) $countries->sum(fn ($c) => $c->periods[$key]->cost),
                (float) $countries->sum(fn ($c) => $c->periods[$key]->value),
            );
        };

        $countries = $rows->map(function ($r) use ($period) {
            $geo = $r->geo_id ? (int) $r->geo_id : null;
            return (object) [
                'app_id'       => (int) $r->app_id,
                'geo_id'       => $geo,
                'country_name' => Country::nameFor($geo),
                'country_code' => Country::codeFor($geo),
                'periods'      => [
                    'date' => $period((float) $r->d_cost, (float) $r->d_val),
                    '30'   => $period((float) $r->m_cost, (float) $r->m_val),
                    '90'   => $period((float) $r->q_cost, (float) $r->q_val),
                ],
            ];
        })->filter(fn ($c) => match ($basis) {
            'all'   => collect($c->periods)->every(fn ($p) => $p->flag),
            'any'   => collect($c->periods)->contains(fn ($p) => $p->flag),
            default => $c->periods[$basis]->flag ?? false,
        });

        $byApp = $countries->groupBy('app_id');
        $keys  = ['date', '30', '90'];

        $apps = App::orderBy('name')->get(['id', 'name', 'package_id'])->map(function ($app) use ($byApp, $keys, $sumPeriods) {
            $list = ($byApp[$app->id] ?? collect())
                ->sortByDesc(fn ($c) => $c->periods['90']->loss)->values();
            return (object) [
                'id'         => $app->id,
                'name'       => $app->name,
                'package_id' => $app->package_id,
                'countries'  => $list,
                'totals'     => collect($keys)->mapWithKeys(fn ($k) => [$k => $sumPeriods($list, $k)])->all(),
            ];
        })
            // Apps with losses first (biggest 90-day loss on top), clean apps after.
            ->sortByDesc(fn ($a) => [$a->countries->isNotEmpty() ? 1 : 0, $a->totals['90']->loss])
            ->values();

        return [
            'apps'   => $apps,
            'totals' => collect($keys)->mapWithKeys(fn ($k) => [$k => $sumPeriods($countries->values(), $k)])->all(),
            'ranges' => ['date' => [$start, $end], '30' => [$from30, $end], '90' => [$from90, $end]],
        ];
    }

    // ── internals ───────────────────────────────────────────────────────
    private function sumSelect(): string
    {
        return collect(self::SUMS)
            ->map(fn ($c) => "COALESCE(SUM({$c}),0) as {$c}")
            ->implode(', ');
    }

    /** Attach derived metrics (total_rev, troas, cpi, trial_convert_perc). */
    private function derive(object $r): object
    {
        foreach (self::SUMS as $c) {
            $r->$c = (float) ($r->$c ?? 0);
        }
        // Ad revenue = Conv. Value straight from the report (Google Ads conversions_value).
        $r->ad_rev             = $r->conversions_value;
        // CONVERT_REV is excluded from the report — its conversion-action mapping is
        // unreliable, so it's forced to 0 and left out of TOTAL_REV. (Raw synced
        // values stay in the DB, so this is reversible.)
        $r->convert_rev        = 0.0;
        $r->total_rev          = $r->ad_rev + $r->renew_rev;
        // TROAS (%) = Conv. Value ÷ Cost × 100.
        $r->troas              = $r->cost > 0 ? $r->conversions_value / $r->cost * 100 : 0;
        $r->cpi                = $r->install > 0 ? $r->cost / $r->install : 0;
        $r->trial_convert_perc = $r->trial > 0 ? $r->trial_convert / $r->trial * 100 : 0;
        return $r;
    }
}
