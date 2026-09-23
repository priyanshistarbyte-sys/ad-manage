@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
<style>
    /* Date-range input box */
    .entry-range-filter { position: relative; display: block; }
    .entry-range-filter .form-control {
        width: 100%; padding-left: 32px; cursor: pointer;
        background: var(--card-bg); border: 1px solid var(--border); color: #fff;
    }
    .entry-range-filter .form-control[readonly] { background: var(--card-bg); color: #fff; }
    .entry-range-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
        font-size: 13px; color: var(--text-muted); pointer-events: none; }

    /* daterangepicker re-skinned to the ad-manage dark theme */
    .daterangepicker {
        background: var(--card-bg); border: 1px solid var(--border); color: var(--text);
        font-family: inherit; box-shadow: 0 12px 34px rgba(0,0,0,.55); border-radius: 8px;
    }
    .daterangepicker:before { border-bottom-color: var(--border); }
    .daterangepicker:after  { border-bottom-color: var(--card-bg); }
    .daterangepicker.drop-up:before { border-top-color: var(--border); }
    .daterangepicker.drop-up:after  { border-top-color: var(--card-bg); }
    .daterangepicker .calendar-table { background: var(--card-bg); border-color: var(--card-bg); }
    .daterangepicker.show-ranges.ltr .drp-calendar.left { border-left: 1px solid var(--border); }
    .daterangepicker .calendar-table th,
    .daterangepicker .calendar-table td { color: var(--text); border-radius: 6px; }
    .daterangepicker .calendar-table th.month { color: #fff; font-weight: 600; }
    .daterangepicker .calendar-table .next span,
    .daterangepicker .calendar-table .prev span { border-color: var(--text-muted); }
    .daterangepicker td.available:hover,
    .daterangepicker th.available:hover { background: #1a1a42; color: #fff; }
    .daterangepicker td.off,
    .daterangepicker td.off.in-range,
    .daterangepicker td.off.start-date,
    .daterangepicker td.off.end-date { background: transparent; color: #55557a; }
    .daterangepicker td.disabled, .daterangepicker option.disabled { color: #55557a; opacity: .5; }
    .daterangepicker td.in-range { background: #23234d; color: #fff; border-radius: 0; }
    .daterangepicker td.active, .daterangepicker td.active:hover { background: var(--purple); color: #fff; }
    .daterangepicker td.start-date { border-radius: 6px 0 0 6px; }
    .daterangepicker td.end-date { border-radius: 0 6px 6px 0; }
    .daterangepicker td.start-date.end-date { border-radius: 6px; }
    .daterangepicker .ranges li { color: var(--text); border-radius: 6px; margin: 2px 6px; }
    .daterangepicker .ranges li:hover { background: #1a1a42; color: #fff; }
    .daterangepicker .ranges li.active { background: var(--purple); color: #fff; }
    .daterangepicker select.monthselect, .daterangepicker select.yearselect {
        background: var(--card-bg); color: var(--text); border: 1px solid var(--border);
        border-radius: 4px; padding: 1px 2px;
    }
    .daterangepicker .drp-buttons { border-top: 1px solid var(--border); }
    .daterangepicker .drp-selected { color: var(--text-muted); }
    .daterangepicker .drp-buttons .btn { font-size: 12px; padding: 4px 12px; }
    .daterangepicker .drp-buttons .cancelBtn {
        background: transparent; color: var(--text); border: 1px solid var(--border);
    }
    .daterangepicker .drp-buttons .applyBtn { background: var(--purple); border-color: var(--purple); color: #fff; }
</style>
@endsection

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

    {{-- Filters — apply to the KPI cards, every chart, the daily table and the
         country rankings below. Submitting reloads the dashboard (GET /) so all
         sections share one filter set. --}}
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
            <div style="grid-column:span 2">
                <label class="form-label">Date range</label>
                <div class="entry-range-filter">
                    <i class="bi bi-calendar-range entry-range-icon"></i>
                    <input type="text" id="dateRange" class="form-control" placeholder="Select date range"
                           autocomplete="off" readonly title="Filter by date range">
                </div>
                <input type="hidden" name="from" id="fromInput" value="{{ $filters['from'] }}">
                <input type="hidden" name="to"   id="toInput"   value="{{ $filters['to'] }}">
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

    {{-- KPI cards (reflect the filters above) --}}
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
                        <td><button type="button" class="pill drill-btn" style="font:inherit"
                                data-title="History · {{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}"
                                data-url="{{ url('/report/history?' . $q(['date' => $d, 'partial' => 1])) }}">VIEW HISTORY</button></td>
                        <td><button type="button" class="pill drill-btn" style="font:inherit"
                                data-title="Country · {{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}"
                                data-url="{{ url('/report/country?' . $q(['date' => $d, 'partial' => 1])) }}">COUNTRY</button></td>
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
            <div class="data-card-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                <span><i class="bi {{ $icon }}"></i> {{ $title }}</span>
                @if ($key === 'profit')
                <select class="rank-sort" style="background:#1a1a42;border:1px solid var(--border);color:var(--text-muted);
                        border-radius:6px;padding:3px 8px;font-size:11.5px;cursor:pointer">
                    <option value="troas:desc">High to Low TROAS</option>
                    <option value="cost:desc">High to Low Cost</option>
                    <option value="total_rev:desc">High to Low Total Rev</option>
                </select>
                @else
                <select class="rank-sort" style="background:#1a1a42;border:1px solid var(--border);color:var(--text-muted);
                        border-radius:6px;padding:3px 8px;font-size:11.5px;cursor:pointer">
                    <option value="troas:asc">Low to High TROAS</option>
                    <option value="cost:desc">High to Low Cost</option>
                    <option value="total_rev:desc">High to Low Total Rev</option>
                </select>
                @endif
            </div>
            <div class="table-wrap">
                <table class="ledger" style="width:100%">
                    <thead><tr><th>Country</th><th>Cost</th><th>Total Rev</th><th>TROAS</th></tr></thead>
                    <tbody>
                        @forelse ($ranking[$key] as $c)
                        <tr data-cost="{{ (float) $c->cost }}" data-total_rev="{{ (float) $c->total_rev }}" data-troas="{{ (float) $c->troas }}">
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

{{-- Drill-in modal: loads the History / Country partials without leaving the dashboard --}}
<div class="modal fade" id="drillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content" style="background:var(--card-bg);border:1px solid var(--border)">
            <div class="modal-header" style="border-color:var(--border)">
                <h5 class="modal-title" style="color:#fff" id="drillModalTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="drillModalBody"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- Date-range picker (jQuery + moment + daterangepicker) --}}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
<script>
    jQuery(function ($) {
        var $box  = $('#dateRange');
        var $from = $('#fromInput'), $to = $('#toInput');
        var start = $from.val() ? moment($from.val(), 'YYYY-MM-DD') : moment().startOf('month');
        var end   = $to.val()   ? moment($to.val(),   'YYYY-MM-DD') : moment();

        function apply(s, e) {
            $from.val(s.format('YYYY-MM-DD'));
            $to.val(e.format('YYYY-MM-DD'));
            $box.val(s.format('DD MMM YYYY') + ' - ' + e.format('DD MMM YYYY'));
        }

        $box.daterangepicker({
            startDate: start,
            endDate: end,
            autoUpdateInput: false,
            showDropdowns: true,
            alwaysShowCalendars: true,
            opens: 'left',
            linkedCalendars: false,
            ranges: {
                'Today':        [moment(), moment()],
                'Yesterday':    [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days':  [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month':   [moment().startOf('month'), moment().endOf('month')],
                'Last Month':   [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            },
            locale: { format: 'DD MMM YYYY', applyLabel: 'Apply', cancelLabel: 'Clear' }
        });

        apply(start, end); // show the current filter range in the box

        // Applying a range submits the filter form so every section — KPI cards,
        // charts, the daily table and country rankings — reloads for that range.
        $box.on('apply.daterangepicker', function (e, p) {
            apply(p.startDate, p.endDate);
            $box.closest('form').trigger('submit');
        });

        // Country ranking: re-sort the rows by the chosen metric + direction
        // (value is "key:asc" or "key:desc").
        $('.rank-sort').on('change', function () {
            var parts = String($(this).val()).split(':');
            var key   = parts[0];
            var asc   = parts[1] === 'asc';
            var $body = $(this).closest('.data-card').find('tbody');
            var $rows = $body.find('tr').filter(function () {
                return $(this).data(key) !== undefined;
            });
            if (!$rows.length) return;
            $rows.sort(function (a, b) {
                var diff = parseFloat($(a).data(key)) - parseFloat($(b).data(key));
                return asc ? diff : -diff;
            }).appendTo($body);
        });

        // Drill-in modal — load the History / Country partial in place instead of
        // navigating to a separate page.
        var drillModal = new bootstrap.Modal(document.getElementById('drillModal'));
        var $drillBody = $('#drillModalBody'), $drillTitle = $('#drillModalTitle');

        $(document).on('click', '.drill-btn', function () {
            var url = $(this).data('url');
            $drillTitle.text($(this).data('title') || '');
            $drillBody.html('<div style="text-align:center;padding:44px;color:var(--text-muted)">' +
                '<span class="spinner-border spinner-border-sm"></span> Loading…</div>');
            drillModal.show();
            $.get(url)
                .done(function (html) { $drillBody.html(html); })
                .fail(function () {
                    $drillBody.html('<div style="text-align:center;padding:44px;color:var(--red)">' +
                        'Could not load. Please try again.</div>');
                });
        });
    });
</script>

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
