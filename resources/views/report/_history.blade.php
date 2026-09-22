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
        <table class="ledger monthly" style="width:100%;white-space:nowrap">
            <thead>
                <tr>
                    <th>AS OF</th><th>COST</th><th>TROAS</th><th>TOTAL_REV</th>
                    <th>Δ TOTAL_REV</th>
                    <th>AD_REV</th><th>CONVERT_REV</th><th>RENEW_REV</th>
                    <th>TRIAL</th><th>INSTALL</th><th>ROWS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($snapshots as $when => $s)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($when)->format('d M Y') }}</td>
                    <td>{{ $money($s->cost) }}</td>
                    <td><span class="{{ $s->troas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($s->troas) }}</span></td>
                    <td>{{ $money($s->total_rev) }}</td>
                    <td>
                        @if ($s->delta_rev === null)
                            <span style="color:var(--text-muted)">—</span>
                        @elseif ($s->delta_rev > 0)
                            <span style="color:#3fb950">▲ {{ $money($s->delta_rev) }}</span>
                        @elseif ($s->delta_rev < 0)
                            <span style="color:#f85149">▼ {{ $money(abs($s->delta_rev)) }}</span>
                        @else
                            <span style="color:var(--text-muted)">0.00</span>
                        @endif
                    </td>
                    <td>{{ $money($s->ad_rev) }}</td>
                    <td>{{ $money($s->convert_rev) }}</td>
                    <td>{{ $money($s->renew_rev) }}</td>
                    <td>{{ $num($s->trial) }}</td>
                    <td>{{ $num($s->install) }}</td>
                    <td>{{ $num($s->rows) }}</td>
                </tr>
                @empty
                <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:32px">
                    No history yet for this day. Snapshots are written on every Sync All.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
