@extends('layouts.app')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num   = fn ($v) => number_format((float) $v);
    $pct   = fn ($v) => number_format((float) $v, 0) . '%';
@endphp

<div style="max-width:1300px;margin:0 auto">
    <div class="dash-header" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px">
        <div>
            <div class="dash-title" style="color:#fff">Welcome{{ currentUserName() !== '' ? ', '.currentUserName() : '' }} 👋</div>
            <div class="dash-subtitle" style="color:var(--text-muted);font-size:12.5px">
                {{ \Carbon\Carbon::parse($filters['from'])->format('d M') }} → {{ \Carbon\Carbon::parse($filters['to'])->format('d M Y') }}
                @if ($lastSynced) · synced {{ \Carbon\Carbon::parse($lastSynced)->diffForHumans() }} @endif
            </div>
        </div>
        <a href="{{ url('/report') }}" class="btn-primary-custom" style="text-decoration:none"><i class="bi bi-table"></i> Full Report</a>
    </div>

    {{-- KPI cards --}}
    <div class="kpi-row">
        <div class="kpi-card"><div class="kpi-label">Total Cost</div><div class="kpi-value white">{{ $money($totals->cost) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Total Revenue</div><div class="kpi-value green">{{ $money($totals->total_rev) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">TROAS</div><div class="kpi-value {{ $totals->troas >= 100 ? 'green' : 'red' }}">{{ $pct($totals->troas) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Installs</div><div class="kpi-value purple">{{ $num($totals->install) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Cost / Install</div><div class="kpi-value gold">{{ $money($totals->cpi) }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Trials</div><div class="kpi-value orange">{{ $num($totals->trial) }}</div></div>
    </div>

    @if (!$hasData)
    <div class="data-card" style="text-align:center;padding:40px;color:var(--text-muted)">
        <i class="bi bi-bar-chart-line" style="font-size:2rem;display:block;margin-bottom:10px;color:var(--purple)"></i>
        No synced data yet. You have {{ $appCount }} app(s) and {{ $connCount }} active Ad Account(s).
        Head to <a href="{{ url('/sync-all') }}" style="color:#a78bfa">Sync All</a> to pull your first report.
    </div>
    @else
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

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
        @foreach (['profit' => ['Top Profit Countries', 'badge-profit'], 'loss' => ['Top Loss Countries', 'badge-loss']] as $key => [$title, $badge])
        <div class="data-card">
            <div class="data-card-header"><span>{{ $title }}</span></div>
            <div class="table-wrap">
                <table class="ledger" style="width:100%">
                    <thead><tr><th>Country</th><th>Cost</th><th>Total Rev</th><th>TROAS</th></tr></thead>
                    <tbody>
                        @forelse ($ranking[$key] as $c)
                        <tr><td style="text-align:left">{{ $c->country_name }}</td><td>{{ $money($c->cost) }}</td>
                            <td>{{ $money($c->total_rev) }}</td><td><span class="{{ $badge }}">{{ $pct($c->troas) }}</span></td></tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:18px">No data.</td></tr>
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
