<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Connection;
use App\Models\DailyStat;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $f      = $this->reports->filters($request);
        $rows   = $this->reports->dailyRows($f);
        $totals = $this->reports->totals($f);
        $rank   = $this->reports->countryRanking($f, 5);

        // Chart series: cost vs total revenue, and TROAS trend, by date.
        $labels  = $rows->map(fn ($r) => \Carbon\Carbon::parse($r->date)->format('d M'))->values();
        $costs   = $rows->map(fn ($r) => round($r->cost, 2))->values();
        $revenue = $rows->map(fn ($r) => round($r->total_rev, 2))->values();
        $troas   = $rows->map(fn ($r) => round($r->troas, 1))->values();

        return view('dashboard', [
            'activePage' => 'home',
            'pageTitle'  => 'Dashboard',
            'filters'    => $f,
            'options'    => $this->reports->filterOptions(),
            'rows'       => $rows,
            'totals'     => $totals,
            'ranking'    => $rank,
            'hasData'    => $rows->isNotEmpty(),
            'appCount'   => App::count(),
            'connCount'  => Connection::where('active', true)->count(),
            'lastSynced' => DailyStat::max('synced_at'),
            'chart'      => [
                'labels'  => $labels,
                'costs'   => $costs,
                'revenue' => $revenue,
                'troas'   => $troas,
            ],
        ]);
    }
}
