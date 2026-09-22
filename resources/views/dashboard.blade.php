@extends('layouts.app')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num   = fn ($v) => number_format((float) $v);
    $pct   = fn ($v) => number_format((float) $v, 0) . '%';
    // Build a querystring carrying the current filters (for drill-in / export links).
    $q     = fn (array $extra = []) => http_build_query(array_merge(array_filter([
                'app_id' => $filters['app_id'], 'campaign_id' => $filters['campaign_id'],
                'geo_id' => $filters['geo_id'], 'from' => $filters['from'], 'to' => $filters['to'],
            ], fn ($v) => $v !== null && $v !== ''), $extra));
@endphp

<div style="max-width:1400px;margin:0 auto">

    {{-- KPI cards --}}
    <div class="kpi-row">
        <div class="kpi-card"><div class="kpi-label">Total Cost</div><div class="kpi-value white">{{ $money($totals->cost) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Total Revenue</div><div class="kpi-value green">{{ $money($totals->total_rev) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">TROAS</div><div class="kpi-value {{ $totals->troas >= 100 ? 'green' : 'red' }}">{{ $pct($totals->troas) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Installs</div><div class="kpi-value purple">{{ $num($totals->install) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Cost / Install</div><div class="kpi-value gold">{{ $money($totals->cpi) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Trials</div><div class="kpi-value orange">{{ $num($totals->trial) }}</div></div>
    </div>

    {{-- Filters — apply to every chart, the daily table and the country rankings below.
         Submitting reloads the dashboard (GET /) so all sections share one filter set. --}}
    <form method="get" action="{{ url('/') }}" class="data-card" style="padding:14px 16px;margin-bottom:16px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end">
            <div>
                <label class="form-label">Filter — App</label>
                <select name="app_id" class="form-select">
                    <option value="">All apps</option>
                    @foreach ($options['apps'] as $app)
                        <option value="{{ $app->id }}" @selected($filters['app_id'] == $app->id)>{{ $app->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Filter — Campaign</label>
                <select name="campaign_id" class="form-select">
                    <option value="">All campaigns</option>
                    @foreach ($options['campaigns'] as $c)
                        <option value="{{ $c->campaign_id }}" @selected($filters['campaign_id'] == $c->campaign_id)>
                            {{ $c->campaign_name ?: $c->campaign_id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Filter — Country</label>
                <select name="geo_id" class="form-select">
                    <option value="">All countries</option>
                    @foreach ($options['countries'] as $c)
                        <option value="{{ $c->geo_id }}" @selected($filters['geo_id'] === $c->geo_id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
            </div>
            <div class="d-flex gap-2">
                <button class="btn-primary-custom" type="submit"><i class="bi bi-funnel"></i> Apply</button>
                <a href="{{ url('/') }}" class="btn-sm-custom" style="text-decoration:none;color:var(--text-muted);align-self:center">Reset</a>
            </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-top:12px">
            <div style="color:var(--text-muted);font-size:12px">
                {{ \Carbon\Carbon::parse($filters['from'])->format('d M Y') }} → {{ \Carbon\Carbon::parse($filters['to'])->format('d M Y') }}
                @if ($lastSynced) · synced {{ \Carbon\Carbon::parse($lastSynced)->diffForHumans() }} @endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ url('/report/export?' . $q()) }}" class="btn-sm-custom" style="background:#1a7f37;color:#fff;text-decoration:none">
                    <i class="bi bi-filetype-csv"></i> Export CSV
                </a>
                <a href="{{ url('/sync-all') }}" class="btn-sm-custom" style="text-decoration:none">
                    <i class="bi bi-arrow-repeat"></i> Sync
                </a>
            </div>
        </div>
    </form>

    @if (!$hasData)
    <div class="data-card" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="bi bi-bar-chart-line" style="font-size:2rem;display:block;margin-bottom:10px;color:var(--purple)"></i>
        No data for these filters. You have {{ $appCount }} app(s) and {{ $connCount }} active Ad Account(s).
        Head to <a href="{{ url('/sync-all') }}" style="color:#a78bfa">Sync All</a> to pull your first report.
    </div>
    @else
    {{-- Charts: Cost vs Revenue + TROAS Trend (honour the filters above) --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px" class="dash-grid">
        <div class="data-card" style="padding:16px">
            <div class="data-card-header" style="border:0;padding:0 0 10px"><span><i class="bi bi-graph-up"></i> Cost vs Revenue</span></div>
            <canvas id="costRevChart" height="120"></canvas>
        </div>
        <div class="data-card" style="padding:16px">
            <div class="data-card-header" style="border:0;padding:0 0 10px"><span><i class="bi bi-activity"></i> TROAS Trend</span></div>
            <canvas id="troasChart" height="120"></canvas>
        </div>
    </div>

    {{-- Daily performance table (the Excel-style report, moved here) --}}
    <div class="data-card" style="margin-bottom:16px">
        <div class="data-card-header">
            <span><i class="bi bi-calendar3"></i> Daily Performance</span>
            <span style="color:var(--text-muted);font-size:12px">{{ $rows->count() }} day(s)</span>
        </div>
        <div class="table-wrap">
            <table class="ledger monthly" style="width:100%;white-space:nowrap">
                <thead>
                    <tr>
                        <th>DATE</th><th>COST</th><th>TROAS</th><th>TOTAL_REV</th>
                        <th>AD_REV</th><th>CONVERT_REV</th><th>RENEW_REV</th>
                        <th>TRIAL</th><th>TRIAL_CONV%</th><th>REPEAT</th>
                        <th>INSTALL</th><th>CPI</th><th></th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                    @php $d = \Carbon\Carbon::parse($r->date)->format('Y-m-d'); @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($r->date)->format('d-m-Y') }}</td>
                        <td>{{ $money($r->cost) }}</td>
                        <td><span class="{{ $r->troas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($r->troas) }}</span></td>
                        <td>{{ $money($r->total_rev) }}</td>
                        <td>{{ $money($r->ad_rev) }}</td>
                        <td>{{ $money($r->convert_rev) }}</td>
                        <td>{{ $money($r->renew_rev) }}</td>
                        <td>{{ $num($r->trial) }}</td>
                        <td>{{ $pct($r->trial_convert_perc) }}</td>
                        <td>{{ $num($r->repeat_count) }}</td>
                        <td>{{ $num($r->install) }}</td>
                        <td>{{ $money($r->cpi) }}</td>
                        <td><a href="{{ url('/report/history?' . $q(['date' => $d])) }}" class="pill" style="text-decoration:none">VIEW HISTORY</a></td>
                        <td><a href="{{ url('/report/country?' . $q(['date' => $d])) }}" class="pill" style="text-decoration:none">COUNTRY</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="14" style="text-align:center;color:var(--text-muted);padding:32px">No data for these filters.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td>TOTAL</td>
                        <td>{{ $money($totals->cost) }}</td>
                        <td><span class="{{ $totals->troas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($totals->troas) }}</span></td>
                        <td>{{ $money($totals->total_rev) }}</td>
                        <td>{{ $money($totals->ad_rev) }}</td>
                        <td>{{ $money($totals->convert_rev) }}</td>
                        <td>{{ $money($totals->renew_rev) }}</td>
                        <td>{{ $num($totals->trial) }}</td>
                        <td>{{ $pct($totals->trial_convert_perc) }}</td>
                        <td>{{ $num($totals->repeat_count) }}</td>
                        <td>{{ $num($totals->install) }}</td>
                        <td>{{ $money($totals->cpi) }}</td>
                        <td></td><td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Country TROAS rankings (honour the filters above) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
        @foreach (['profit' => ['Top Profit Countries (TROAS)', 'bi-graph-up-arrow', 'badge-profit'], 'loss' => ['Top Loss Countries (TROAS)', 'bi-graph-down-arrow', 'badge-loss']] as $key => [$title, $icon, $badge])
        <div class="data-card">
            <div class="data-card-header"><span><i class="bi {{ $icon }}"></i> {{ $title }}</span></div>
            <div class="table-wrap">
                <table class="ledger" style="width:100%">
                    <thead><tr><th>Country</th><th>Cost</th><th>Total Rev</th><th>TROAS</th></tr></thead>
                    <tbody>
                        @forelse ($ranking[$key] as $c)
                        <tr>
                            <td style="text-align:left">
                                <a href="{{ url('/?' . $q(['geo_id' => $c->geo_id])) }}" style="color:#fff;text-decoration:none">{{ $c->country_name }}</a>
                            </td>
                            <td>{{ $money($c->cost) }}</td>
                            <td>{{ $money($c->total_rev) }}</td>
                            <td><span class="{{ $badge }}">{{ $pct($c->troas) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:18px">No country data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection

@section('scripts')
@if ($hasData)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const gridColor = 'rgba(255,255,255,.06)', tick = '#9aa0b5';
    const base = { responsive:true, plugins:{legend:{labels:{color:tick}}},
        scales:{x:{ticks:{color:tick},grid:{color:gridColor}},y:{ticks:{color:tick},grid:{color:gridColor}}} };

    new Chart(document.getElementById('costRevChart'), {
        type:'line',
        data:{ labels:@json($chart['labels']),
            datasets:[
                {label:'Cost', data:@json($chart['costs']), borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,.15)', tension:.3, fill:true},
                {label:'Revenue', data:@json($chart['revenue']), borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,.15)', tension:.3, fill:true},
            ]}, options: base });

    new Chart(document.getElementById('troasChart'), {
        type:'line',
        data:{ labels:@json($chart['labels']),
            datasets:[{label:'TROAS %', data:@json($chart['troas']), borderColor:'#a78bfa', backgroundColor:'rgba(167,139,250,.15)', tension:.3, fill:true}]},
        options: base });
</script>
@endif
@endsection
