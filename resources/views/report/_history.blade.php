@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num   = fn ($v) => number_format((float) $v);
    $pct   = fn ($v) => number_format((float) $v, 0) . '%';
@endphp

<div class="dash-subtitle" style="color:var(--text-muted);font-size:12.5px;margin-bottom:14px">
    @if ($date) <strong style="color:var(--text)">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</strong> @endif
    @if ($appName ?? null) · {{ $appName }} @endif
    @if ($geoName) · {{ $geoName }} @endif
    @if ($campaignId) · campaign {{ $campaignId }} @endif
    — this date's numbers as they stood on each day since
</div>

<div class="data-card">
    <div class="data-card-header"><span><i class="bi bi-layers"></i> Daily Snapshots</span>
        <span style="color:var(--text-muted);font-size:12px">{{ $snapshots->count() }} day(s)</span>
    </div>
    <div class="table-wrap">
        <table class="ledger monthly sortable" style="width:100%;white-space:nowrap">
            <thead>
                <tr>
                    <th>AS OF</th><th>COST</th><th>TROAS</th><th>TOTAL_REV</th>
                    <th>AD_REV</th><th>CONVERT_REV</th><th>RENEW_REV</th>
                    <th>TRIAL</th><th>INSTALL</th><th>ROWS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($snapshots as $when => $s)
                <tr>
                    <td data-sort="{{ \Carbon\Carbon::parse($when)->format('Y-m-d') }}">{{ \Carbon\Carbon::parse($when)->format('d M Y') }}</td>
                    <td>{{ $money($s->cost) }}</td>
                    @if ($s->troas === null)
                    {{-- Snapshot taken before Conv. Value was stored — revenue unknown. --}}
                    <td data-sort="-1"><span style="color:var(--text-muted)" title="Revenue wasn't recorded in snapshots before 28 Sep 2026">—</span></td>
                    <td data-sort="-1"><span style="color:var(--text-muted);font-size:11px" title="Revenue wasn't recorded in snapshots before 28 Sep 2026">not recorded</span></td>
                    <td data-sort="-1"><span style="color:var(--text-muted);font-size:11px" title="Revenue wasn't recorded in snapshots before 28 Sep 2026">not recorded</span></td>
                    @else
                    <td><span class="{{ $s->troas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($s->troas) }}</span></td>
                    <td>{{ $money($s->total_rev) }}</td>
                    <td>{{ $money($s->ad_rev) }}</td>
                    @endif
                    <td>{{ $money($s->convert_rev) }}</td>
                    <td>{{ $money($s->renew_rev) }}</td>
                    <td>{{ $num($s->trial) }}</td>
                    <td>{{ $num($s->install) }}</td>
                    <td>{{ $num($s->rows) }}</td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px">
                    No history yet for this day. Snapshots are written on every Sync All.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if ($snapshots->contains(fn ($s) => $s->troas === null))
<div style="color:var(--text-muted);font-size:11.5px;margin-top:10px">
    <i class="bi bi-info-circle"></i> Snapshots before 28 Sep 2026 saved cost and installs but not Conv. Value, so their TROAS / revenue can't be shown. Every sync from now on records it.
</div>
@endif
