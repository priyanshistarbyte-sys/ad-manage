@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
{{-- Font Awesome: toolbar icons of the notes editor (same set as sb-hrm) --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    /* ── Rich text editor (ported from sb-hrm) ── */
    .rich-editor { border: 1px solid var(--border); border-radius: 6px; background: var(--header-bg); overflow: hidden; }
    .rich-editor-toolbar {
        display: flex; flex-wrap: wrap; align-items: center; gap: 2px;
        padding: 6px; background: var(--navy-mid); border-bottom: 1px solid var(--border);
    }
    .rich-editor-toolbar button {
        background: transparent; border: 1px solid transparent; border-radius: 4px;
        color: var(--text-muted); width: 28px; height: 26px; font-size: 12px; cursor: pointer;
    }
    .rich-editor-toolbar button:hover { background: var(--surface-btn-hover); color: var(--text-strong); border-color: var(--border); }
    .rich-editor-sep { width: 1px; height: 18px; background: var(--border); margin: 0 4px; }
    .rich-editor-area {
        min-height: 150px; max-height: 340px; overflow-y: auto;
        padding: 12px; color: var(--text); font-size: 13px; line-height: 1.6; outline: none;
    }
    .rich-editor-area:empty::before { content: attr(data-placeholder); color: var(--text-faint); }
    .rich-editor-area:focus { box-shadow: inset 0 0 0 2px rgba(108,63,197,.35); }
    /* Same formatting inside the editor and in saved notes */
    .rich-editor-area h3, .an-note-text h3 { font-size: 15px; color: var(--text-strong); margin: 0 0 6px; }
    .rich-editor-area ul, .rich-editor-area ol, .an-note-text ul, .an-note-text ol { padding-left: 22px; margin-bottom: 8px; }
    .rich-editor-area blockquote, .an-note-text blockquote {
        border-left: 3px solid var(--purple); margin: 8px 0; padding: 2px 0 2px 12px; color: var(--text-muted);
    }
    .rich-editor-area a, .an-note-text a { color: var(--info); }
    .an-note-text p { margin: 0 0 6px; }
    .an-note-text > :last-child { margin-bottom: 0; }

    /* One table per app: Country | Date (Cost, TROAS) | 30d (Cost, TROAS) | 90d (Cost, TROAS) | Notes */
    table.an-tbl { width: 100%; font-size: 13px; border-collapse: collapse; white-space: nowrap; }
    table.an-tbl th, table.an-tbl td { border: 1px solid var(--border); padding: 8px 12px; }
    table.an-tbl thead th {
        background: var(--header-bg); color: var(--text-muted); font-size: 11px; font-weight: 600;
        text-transform: uppercase; letter-spacing: .4px; text-align: center;
    }
    table.an-tbl thead tr:first-child th { color: var(--text-strong); font-size: 12px; }
    table.an-tbl thead th.grp { border-bottom-color: var(--border-strong); }
    table.an-tbl tbody tr:hover td { background: var(--row-hover); }
    table.an-tbl th.an-sort { cursor: pointer; user-select: none; }
    table.an-tbl th.an-sort:hover { color: var(--accent-soft); }
    table.an-tbl th.an-sort::after { content: ' ⇅'; opacity: .4; }
    table.an-tbl th.an-sort.sort-asc,
    table.an-tbl th.an-sort.sort-desc { color: var(--accent-soft); }
    table.an-tbl th.an-sort.sort-asc::after  { content: ' ↑'; opacity: 1; }
    table.an-tbl th.an-sort.sort-desc::after { content: ' ↓'; opacity: 1; }
    table.an-tbl td { color: var(--text); text-align: center; vertical-align: middle; }
    table.an-tbl td.country { text-align: left; font-weight: 600; color: var(--text-strong); }
    table.an-tbl td.country .code { color: var(--text-muted); font-size: 11px; font-weight: 500; margin-left: 4px; }
    table.an-tbl td.troas { text-align: center; }
    table.an-tbl td.troas .loss { display: inline-block; font-size: 12px; margin-left: 6px; vertical-align: middle; }
    table.an-tbl td.flag { background: var(--flag-bg); }
    table.an-tbl td.notes { text-align: center; }
    /* Notes icons: ✓ = Solved (direct toggle), ✎ = Mark as read / notes popup */
    .an-icon-btn {
        background: transparent; border: 1px solid var(--border); border-radius: 6px;
        width: 30px; height: 28px; margin: 0 2px; color: var(--text-muted); font-size: 15px;
        cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all .15s;
    }
    .an-icon-btn:hover { color: var(--text-strong); border-color: var(--purple); background: var(--surface-btn); }
    .an-icon-btn:disabled { opacity: .5; cursor: wait; }
    /* Solved icon: plain tick, same style as the edit pencil; yellow once solved */
    .an-solve { border: none; background: transparent; color: var(--accent-soft); font-size: 17px; }
    .an-solve:hover { border: none; background: transparent; color: var(--accent-soft-hover); }
    .an-solve.on, .an-solve.on:hover { color: var(--gold); }
    /* Edit (notes) icon: plain purple pencil, same as the Apps page's edit action */
    .an-edit { border: none; background: transparent; color: var(--accent-soft); font-size: 14px; }
    .an-edit:hover { border: none; background: transparent; color: var(--accent-soft-hover); }
    .an-edit.on { color: var(--info); }
    /* Solved countries: whole row in yellow */
    table.an-tbl tr.solved td,
    table.an-tbl tr.solved td.flag { background: var(--solved-bg); }
    table.an-tbl tr.solved td.country,
    table.an-tbl tr.solved td:not(.troas) { color: var(--gold); }
    /* Notes popup */
    .an-notes-day { margin-top: 16px; }
    .an-notes-day h6 { color: var(--accent-soft); font-size: 12px; font-weight: 700; margin: 0 0 8px; letter-spacing: .4px; }
    .an-note-item { background: var(--surface-alt); border: 1px solid var(--border); border-radius: 8px; padding: 9px 12px; margin-bottom: 8px; }
    .an-note-item.solved { border-color: var(--solved-bd); background: var(--solved-bg-soft); }
    .an-note-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; color: var(--text-muted); font-size: 11.5px; }
    .an-note-text { color: var(--text); font-size: 13px; line-height: 1.6; margin-top: 5px; word-break: break-word; }
    .an-note-item.solved .an-note-text { color: var(--gold); }
    .badge-solved { background: var(--solved-bg); color: var(--gold); border: 1px solid var(--solved-bd); border-radius: 5px; padding: 1px 7px; font-size: 10.5px; font-weight: 700; }
    .badge-read   { background: var(--info-bg); color: var(--info); border: 1px solid var(--info-bd); border-radius: 5px; padding: 1px 7px; font-size: 10.5px; font-weight: 700; }
    .an-toggle { display: inline-flex; align-items: center; gap: 6px; margin-right: 18px; color: var(--text); font-size: 13px; cursor: pointer; }
    .an-toggle input { width: 16px; height: 16px; accent-color: var(--purple); }
    .an-toggle.solved input { accent-color: var(--gold); }
    table.an-tbl tfoot td { font-weight: 700; color: var(--text-strong); background: var(--navy-mid); }
    table.an-tbl tfoot td:first-child { text-align: left; }
    .an-app-sum { margin-left: auto; display: flex; gap: 14px; flex-wrap: wrap; font-size: 11.5px; font-weight: 500; color: var(--text-muted); }
    .an-app-sum b { font-weight: 700; }
    .an-empty { padding: 16px 18px; color: var(--text-muted); font-size: 12.5px; }
</style>
@endsection

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $pct   = fn ($v) => number_format((float) $v, 0) . '%';
    $dmy   = fn ($d) => \Carbon\Carbon::parse($d)->format('d-m-Y');
    // Loss = Cost − Conv. Value; a negative loss is a profit (shown green with "+").
    $loss  = fn ($v) => $v > 0
        ? '<span class="c-red">−' . number_format($v, 2) . '</span>'
        : '<span class="c-green">+' . number_format(abs($v), 2) . '</span>';
    // First period = the picked range: "28-09-2026" for one day, "01-09-2026 → 28-09-2026" for several.
    $rangeLabel = $from === $to ? $dmy($to) : $dmy($from) . ' → ' . $dmy($to);
    $labels = [
        'date' => $rangeLabel,
        '30'   => 'Last 30 days',
        '90'   => 'Last 90 days',
    ];
    $tips = collect($data['ranges'])->map(fn ($r) => $dmy($r[0]) . ' → ' . $dmy($r[1]));
    $lossApps = $data['apps']->filter(fn ($a) => $a->countries->isNotEmpty());
@endphp

<div style="max-width:1400px;margin:0 auto">

    {{-- Filters --}}
    <form method="get" action="{{ url('/analytics') }}" id="anFilters" class="data-card" style="padding:14px 16px;margin-bottom:16px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end">
            <div>
                <label class="form-label">Date range</label>
                <div class="entry-range-filter">
                    <i class="bi bi-calendar-range entry-range-icon"></i>
                    <input type="text" id="dateRange" class="form-control" placeholder="Select date range"
                           autocomplete="off" readonly title="Filter by date range">
                </div>
                <input type="hidden" name="from" id="fromInput" value="{{ $from }}">
                <input type="hidden" name="to"   id="toInput"   value="{{ $to }}">
            </div>
            <div>
                <label class="form-label">Cost greater than</label>
                <input type="number" step="any" min="0" name="min_cost" class="form-control" value="{{ $minCost + 0 }}">
            </div>
            <div>
                <label class="form-label">TROAS less than (%)</label>
                <input type="number" step="any" min="0" name="max_troas" class="form-control" value="{{ $maxTroas + 0 }}">
            </div>
            <div>
                <label class="form-label">Loss in</label>
                <select name="basis" class="form-select">
                    <option value="all"  @selected($basis === 'all')>All 3 periods</option>
                    <option value="date" @selected($basis === 'date')>Selected date</option>
                    <option value="any"  @selected($basis === 'any')>Any period</option>
                    <option value="30"   @selected($basis === '30')>Last 30 days</option>
                    <option value="90"   @selected($basis === '90')>Last 90 days</option>
                </select>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <a href="{{ url('/analytics') }}" class="btn-sm-custom" style="text-decoration:none;color:var(--text-muted)">Reset</a>
            </div>
        </div>
        <div style="margin-top:12px;color:var(--text-muted);font-size:12px">
            Showing countries with <strong>Cost &gt; {{ $minCost + 0 }}</strong> and <strong>TROAS &lt; {{ $maxTroas + 0 }}%</strong>
            on <strong>{{ ['date' => $rangeLabel, '30' => 'the last 30 days', '90' => 'the last 90 days', 'any' => 'any period', 'all' => $rangeLabel . ', last 30 days and last 90 days'][$basis] }}</strong>
            · 30 days = {{ $tips['30'] }} · 90 days = {{ $tips['90'] }}
            @if ($latest) · latest data {{ $dmy($latest) }} @endif
        </div>
    </form>

    {{-- Overall loss across every listed country of every app --}}
    <div class="kpi-row">
        @foreach ($labels as $k => $label)
        @php $t = $data['totals'][$k]; @endphp
        <div class="kpi-card" title="{{ $tips[$k] }}">
            <div class="kpi-label">Total loss · {{ $label }}</div>
            <div class="kpi-value {{ $t->loss > 0 ? 'red' : 'green' }}">{!! $loss($t->loss) !!}</div>
            <div style="color:var(--text-muted);font-size:11.5px;margin-top:4px">
                Cost {{ $money($t->cost) }} · TROAS {{ $pct($t->troas) }}
            </div>
        </div>
        @endforeach
        <div class="kpi-card">
            <div class="kpi-label">Loss-making</div>
            <div class="kpi-value white">{{ $lossApps->sum(fn ($a) => $a->countries->count()) }}</div>
            <div style="color:var(--text-muted);font-size:11.5px;margin-top:4px">
                countries across {{ $lossApps->count() }} of {{ $data['apps']->count() }} app(s)
            </div>
        </div>
    </div>

    @forelse ($data['apps'] as $app)
    {{-- Collapsible like the Dashboard's month blocks: first app open (−), the rest collapsed (+). --}}
    @php $open = $loop->first; @endphp
    <div class="data-card an-group">
        <div class="data-card-header an-group-toggle" style="flex-wrap:wrap;cursor:pointer;user-select:none">
            <span>
                <i class="bi {{ $open ? 'bi-dash-square' : 'bi-plus-square' }} toggle-icon" style="color:var(--purple);margin-right:6px"></i>
                 {{ $app->name }}
                <span style="color:var(--text-muted);font-weight:500;font-size:11.5px;margin-left:6px">{{ $app->package_id }}</span>
                <span class="{{ $app->countries->isNotEmpty() ? 'badge-loss' : 'badge-profit' }}" style="margin-left:8px">
                    {{ $app->countries->count() }} loss {{ Str::plural('country', $app->countries->count()) }}
                </span>
            </span>
            @if ($app->countries->isNotEmpty())
            <span class="an-app-sum">
                @foreach ($labels as $k => $label)
                <span title="{{ $tips[$k] }}">{{ $label }}: <b>{!! $loss($app->totals[$k]->loss) !!}</b></span>
                @endforeach
            </span>
            @endif
        </div>

        <div class="an-group-body" style="{{ $open ? '' : 'display:none' }}">
        @if ($app->countries->isEmpty())
        <div class="an-empty"><i class="bi bi-check-circle c-green"></i> No country matches the loss condition for this app.</div>
        @else
        <div class="table-wrap">
            <table class="an-tbl">
                <thead>
                    <tr>
                        <th rowspan="2" class="an-sort" data-col="0" data-type="text" style="text-align:left;vertical-align:middle">Country</th>
                        @foreach ($labels as $k => $label)
                        <th colspan="2" class="grp" title="{{ $tips[$k] }}">{{ $k === 'date' ? ($from === $to ? 'Current date · ' : 'Selected range · ') . $label : $label }}</th>
                        @endforeach
                        <th rowspan="2" style="vertical-align:middle">Notes
                            <div style="font-size:10px;color:var(--text-muted);font-weight:500;margin-top:3px">Solved · Note</div>
                        </th>
                    </tr>
                    <tr>
                        {{-- Default order: current-date TROAS, highest first (column 2). --}}
                        @foreach ($labels as $k => $label)
                        <th class="an-sort" data-col="{{ $loop->index * 2 + 1 }}">Cost</th>
                        <th class="an-sort {{ $k === 'date' ? 'sort-desc' : '' }}" data-col="{{ $loop->index * 2 + 2 }}">TROAS</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($app->countries->sortByDesc(fn ($c) => $c->periods['date']->troas) as $c)
                    @php $st = $status[$app->id . '|' . ($c->geo_id ?? '')] ?? null; @endphp
                    <tr class="{{ $st?->solved ? 'solved' : '' }}" data-app="{{ $app->id }}" data-geo="{{ $c->geo_id }}"
                        data-solved="{{ $st?->solved ? 1 : 0 }}" data-read="{{ $st?->read ? 1 : 0 }}"
                        data-title="{{ $app->name }} · {{ $c->country_name }}">
                        {{-- <td class="country" data-sort="{{ $c->country_name }}">{{ $c->country_name }}<span class="code">{{ $c->country_code }}</span></td> --}}
                        <td class="country" data-sort="{{ $c->country_name }}">
                            <span class="code">{{ $c->country_code }}</span>
                            {{ $c->country_name }}
                        </td>
                        @foreach ($labels as $k => $label)
                        @php $p = $c->periods[$k]; @endphp
                        <td data-sort="{{ $p->cost }}">{{ $money($p->cost) }}</td>
                        <td class="troas {{ $p->flag ? 'flag' : '' }}" data-sort="{{ $p->troas }}">
                            @if ($p->cost > 0)
                            <span class="{{ $p->troas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($p->troas) }}</span>
                            <span class="loss">({!! $loss($p->loss) !!})</span>
                            @else
                            <span class="badge-nodata">—</span>
                            @endif
                        </td>
                        @endforeach
                        <td class="notes">
                            <button type="button" class="an-icon-btn an-solve {{ $st?->solved ? 'on' : '' }}"
                                    title="{{ $st?->solved ? 'Solved — click to mark unsolved' : 'Mark as solved' }}">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button" class="an-icon-btn an-edit {{ $st?->read ? 'on' : '' }}"
                                    title="{{ $st?->read ? 'Read — open notes' : 'Mark as read / add note' }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>TOTAL ({{ $app->countries->count() }})</td>
                        @foreach ($labels as $k => $label)
                        @php $t = $app->totals[$k]; @endphp
                        <td>{{ $money($t->cost) }}</td>
                        <td style="text-align:center">
                            <span class="{{ $t->troas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($t->troas) }}</span>
                            <span style="display:inline-block;font-size:12px;margin-left:6px;vertical-align:middle">({!! $loss($t->loss) !!})</span>
                        </td>
                        @endforeach
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
        </div>
    </div>
    @empty
    <div class="data-card" style="text-align:center;padding:40px;color:var(--text-muted)">
        No apps yet. Add one on the <a href="{{ url('/apps') }}" style="color:var(--accent-soft)">Apps</a> page.
    </div>
    @endforelse
</div>

{{-- Notes popup: Solved / Read status + textarea, and every saved note below, grouped by date --}}
<div class="modal fade" id="notesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-journal-text" style="color:var(--purple)"></i> <span id="notesTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="notesForm">
                    <div style="margin-bottom:10px">
                        <label class="an-toggle solved"><input type="checkbox" id="noteSolved"> Solved</label>
                        <label class="an-toggle"><input type="checkbox" id="noteRead"> Mark as read</label>
                    </div>
                    {{-- Rich text editor (same as sb-hrm's Assign Work → Description) --}}
                    <div class="rich-editor" data-rich-editor id="noteEditor">
                        <div class="rich-editor-toolbar">
                            <button type="button" data-cmd="bold" title="Bold"><i class="fas fa-bold"></i></button>
                            <button type="button" data-cmd="italic" title="Italic"><i class="fas fa-italic"></i></button>
                            <button type="button" data-cmd="underline" title="Underline"><i class="fas fa-underline"></i></button>
                            <button type="button" data-cmd="strikeThrough" title="Strikethrough"><i class="fas fa-strikethrough"></i></button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" data-cmd="formatBlock" data-value="h3" title="Heading"><i class="fas fa-heading"></i></button>
                            <button type="button" data-cmd="insertUnorderedList" title="Bullet list"><i class="fas fa-list-ul"></i></button>
                            <button type="button" data-cmd="insertOrderedList" title="Numbered list"><i class="fas fa-list-ol"></i></button>
                            <button type="button" data-cmd="formatBlock" data-value="blockquote" title="Quote"><i class="fas fa-quote-right"></i></button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" data-cmd="createLink" title="Insert link"><i class="fas fa-link"></i></button>
                            <button type="button" data-cmd="unlink" title="Remove link"><i class="fas fa-link-slash"></i></button>
                            <button type="button" data-cmd="removeFormat" title="Clear formatting"><i class="fas fa-eraser"></i></button>
                        </div>
                        <div class="rich-editor-area" contenteditable="true" data-rich-area data-placeholder="Write a note…"></div>
                        <textarea id="noteText" data-rich-input hidden></textarea>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:10px">
                        <button type="submit" class="btn-primary-custom" id="noteSave"><i class="bi bi-check-lg"></i> Submit</button>
                        <span id="noteError" class="c-red" style="font-size:12px"></span>
                    </div>
                </form>
                <div id="notesList"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/rich-editor.js') }}"></script>
{{-- Date-range picker — same setup as the Dashboard (jQuery + moment + daterangepicker) --}}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
<script>
    jQuery(function ($) {
        var $box  = $('#dateRange');
        var $from = $('#fromInput'), $to = $('#toInput');
        var start = moment($from.val(), 'YYYY-MM-DD');
        var end   = moment($to.val(),   'YYYY-MM-DD');

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
            opens: 'right',
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

        apply(start, end); // show the current range in the box

        // Applying a range reloads the page for it (like the Dashboard).
        $box.on('apply.daterangepicker', function (e, p) {
            apply(p.startDate, p.endDate);
            $box.closest('form').trigger('submit');
        });

        // Filters apply instantly: the "Loss in" dropdown reloads on pick; the
        // Cost / TROAS boxes reload on 'change' (Enter, spinner arrows or leaving
        // the box) so typing "100" doesn't reload after every digit. The form is
        // dimmed while the page loads so a second change isn't lost.
        $('#anFilters select, #anFilters input[type=number]').on('change', function () {
            $('#anFilters').css({ opacity: .6, pointerEvents: 'none' }).trigger('submit');
        });
    });
</script>
<script>
    // Open / close an app block by clicking its header (same as the Dashboard month blocks).
    document.querySelectorAll('.an-group-toggle').forEach(function (head) {
        head.addEventListener('click', function () {
            const body = head.closest('.an-group').querySelector('.an-group-body');
            const willOpen = body.style.display === 'none';
            body.style.display = willOpen ? '' : 'none';
            const icon = head.querySelector('.toggle-icon');
            icon.classList.toggle('bi-dash-square', willOpen);
            icon.classList.toggle('bi-plus-square', !willOpen);
        });
    });

    // Column sorting. The header has two rows (period groups over Cost/TROAS), so each
    // sortable <th> carries its body column in data-col and cells carry raw data-sort values.
    // Numbers start high→low, Country starts A→Z; clicking again flips the direction.
    document.querySelectorAll('table.an-tbl th.an-sort').forEach(function (th) {
        th.addEventListener('click', function () {
            const table = th.closest('table');
            const tbody = table.tBodies[0];
            const col   = +th.dataset.col;
            const isText = th.dataset.type === 'text';
            let dir;
            if (th.classList.contains('sort-desc')) dir = 1;
            else if (th.classList.contains('sort-asc')) dir = -1;
            else dir = isText ? 1 : -1;

            table.querySelectorAll('th.an-sort').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            th.classList.add(dir === 1 ? 'sort-asc' : 'sort-desc');

            const key = r => r.cells[col].dataset.sort;
            Array.from(tbody.rows)
                .sort((a, b) => isText
                    ? key(a).localeCompare(key(b)) * dir
                    : (parseFloat(key(a)) - parseFloat(key(b))) * dir)
                .forEach(r => tbody.appendChild(r));
        });
    });

    // Notes: clicking Solved / Read in a row opens the popup for that app + country
    // (the clicked box pre-toggled). Submit saves the note + status to the server;
    // the list below the form shows every note, grouped by date.
    (function () {
        const URL_NOTES = @json(url('/analytics/notes'));
        const CSRF   = document.querySelector('meta[name="csrf-token"]').content;
        const modal  = new bootstrap.Modal(document.getElementById('notesModal'));
        const $ = id => document.getElementById(id);
        let row = null;   // the <tr> being edited

        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

        function renderList(data) {
            if (!data.days.length) {
                $('notesList').innerHTML = '<div class="an-empty" style="padding:16px 0 0">No notes yet.</div>';
                return;
            }
            $('notesList').innerHTML = data.days.map(day =>
                '<div class="an-notes-day"><h6><i class="bi bi-calendar3"></i> ' + esc(day.date) + '</h6>' +
                day.notes.map(n =>
                    '<div class="an-note-item ' + (n.solved ? 'solved' : '') + '">' +
                        '<div class="an-note-meta"><span><i class="bi bi-clock"></i> ' + esc(n.time) + '</span>' +
                        (n.user ? '<span><i class="bi bi-person"></i> ' + esc(n.user) + '</span>' : '') +
                        (n.solved ? '<span class="badge-solved">Solved</span>' : '') +
                        (n.read ? '<span class="badge-read">Read</span>' : '') + '</div>' +
                        // n.note is rich-text HTML, already cleaned server-side by sanitizeHtml().
                        (n.note ? '<div class="an-note-text">' + n.note + '</div>' : '') +
                    '</div>').join('') +
                '</div>').join('');
        }

        function clearEditor() {
            $('noteEditor').querySelector('[data-rich-area]').innerHTML = '';
            $('noteText').value = '';
        }

        // Reflect the saved status on the table row (icons + yellow when solved).
        function applyStatus(tr, data) {
            tr.dataset.solved = data.solved ? 1 : 0;
            tr.dataset.read   = data.read ? 1 : 0;
            tr.classList.toggle('solved', data.solved);

            const solve = tr.querySelector('.an-solve');
            solve.classList.toggle('on', data.solved);
            solve.title = data.solved ? 'Solved — click to mark unsolved' : 'Mark as solved';

            const edit = tr.querySelector('.an-edit');
            edit.classList.toggle('on', data.read);
            edit.title = data.read ? 'Read — open notes' : 'Mark as read / add note';
        }

        // ✓ Solved: toggles straight away — no popup. Saved as a status-only entry,
        // so it still shows in the country's notes history.
        document.querySelectorAll('.an-solve').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const tr = btn.closest('tr');
                btn.disabled = true;
                fetch(URL_NOTES, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({
                        app_id: +tr.dataset.app,
                        geo_id: tr.dataset.geo === '' ? null : +tr.dataset.geo,
                        note:   '',
                        solved: tr.dataset.solved !== '1',
                        read:   tr.dataset.read === '1',
                    }),
                })
                    .then(r => r.json().then(d => { if (!r.ok) throw new Error(d.message); return d; }))
                    .then(d => applyStatus(tr, d))
                    .catch(err => alert(err.message || 'Could not update Solved.'))
                    .finally(() => { btn.disabled = false; });
            });
        });

        // ✎ Mark as read: opens the notes popup ("Mark as read" pre-ticked).
        document.querySelectorAll('.an-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                row = btn.closest('tr');
                $('noteSolved').checked = row.dataset.solved === '1';
                $('noteRead').checked   = true;
                clearEditor();
                $('noteError').textContent = '';
                $('notesTitle').textContent = row.dataset.title;
                $('notesList').innerHTML = '<div class="an-empty" style="padding:16px 0 0"><span class="spinner-border spinner-border-sm"></span> Loading…</div>';
                modal.show();

                fetch(URL_NOTES + '?app_id=' + row.dataset.app + '&geo_id=' + row.dataset.geo, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json()).then(renderList)
                    .catch(() => { $('notesList').innerHTML = '<div class="c-red" style="padding-top:16px">Could not load notes.</div>'; });
            });
        });

        $('notesForm').addEventListener('submit', function (e) {
            e.preventDefault();
            if (!row) return;
            window.syncRichEditor($('noteEditor'));   // flush the editor's HTML into #noteText
            $('noteSave').disabled = true;
            $('noteError').textContent = '';
            fetch(URL_NOTES, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    app_id: +row.dataset.app,
                    geo_id: row.dataset.geo === '' ? null : +row.dataset.geo,
                    note:   $('noteText').value,
                    solved: $('noteSolved').checked,
                    read:   $('noteRead').checked,
                }),
            })
                .then(r => r.json().then(d => ({ ok: r.ok, d })))
                .then(({ ok, d }) => {
                    if (!ok) { $('noteError').textContent = d.message || 'Could not save.'; return; }
                    clearEditor();
                    applyStatus(row, d);
                    renderList(d);
                })
                .catch(() => { $('noteError').textContent = 'Could not save.'; })
                .finally(() => { $('noteSave').disabled = false; });
        });
    })();
</script>
@endsection
