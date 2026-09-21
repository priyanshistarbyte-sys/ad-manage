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
        return DailyStat::query()
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
            ->selectRaw('MAX(campaign_name) as campaign_name, campaign_id')
            ->groupBy('campaign_id')
            ->orderBy('campaign_name')
            ->get();

        $geoIds = DailyStat::query()->whereNotNull('geo_id')->distinct()->pluck('geo_id');
        $countries = $geoIds
            ->map(fn ($id) => (object) ['geo_id' => (int) $id, 'name' => Country::nameFor((int) $id)])
            ->sortBy('name')->values();

        return [
            'apps'      => App::orderBy('name')->get(['id', 'name']),
            'campaigns' => $campaigns,
            'countries' => $countries,
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
        $r->total_rev          = $r->ad_rev + $r->convert_rev + $r->renew_rev;
        $r->troas              = $r->cost > 0 ? $r->total_rev / $r->cost * 100 : 0;
        $r->cpi                = $r->install > 0 ? $r->cost / $r->install : 0;
        $r->trial_convert_perc = $r->trial > 0 ? $r->trial_convert / $r->trial * 100 : 0;
        return $r;
    }
}
