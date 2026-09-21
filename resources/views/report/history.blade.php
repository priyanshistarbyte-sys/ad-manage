@extends('layouts.app')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num   = fn ($v) => number_format((float) $v);
    $pct   = fn ($v) => number_format((float) $v, 0) . '%';
@endphp

<div style="max-width:1100px;margin:0 auto">
    <div class="dash-header" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div>
            <div class="dash-title" style="color:#fff"><i class="bi bi-clock-history" style="color:var(--purple)"></i> View History</div>
            <div class="dash-subtitle" style="color:var(--text-muted);font-size:12.5px">
                @if ($date) {{ \Carbon\Carbon::parse($date)->format('d M Y') }} @endif
                @if ($geoName) · {{ $geoName }} @endif
                @if ($campaignId) · campaign {{ $campaignId }} @endif
                — how the numbers changed across syncs
            </div>
        </div>
        <a href="{{ url()->previous() }}" class="btn-sm-custom" style="text-decoration:none;color:var(--text-muted);align-self:center">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="data-card">
        <div class="data-card-header"><span><i class="bi bi-layers"></i> Sync Snapshots</span>
            <span style="color:var(--text-muted);font-size:12px">{{ $snapshots->count() }} snapshot(s)</span>
        </div>
        <div class="table-wrap">
            <table class="ledger monthly" style="width:100%;white-space:nowrap">
                <thead>
                    <tr>
                        <th>CAPTURED</th><th>COST</th><th>TROAS</th><th>TOTAL_REV</th>
                        <th>AD_REV</th><th>CONVERT_REV</th><th>RENEW_REV</th>
                        <th>TRIAL</th><th>INSTALL</th><th>ROWS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($snapshots as $when => $s)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($when)->format('d M Y · H:i') }}</td>
                        <td>{{ $money($s->cost) }}</td>
                        <td><span class="{{ $s->troas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($s->troas) }}</span></td>
                        <td>{{ $money($s->total_rev) }}</td>
                        <td>{{ $money($s->ad_rev) }}</td>
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
</div>
@endsection
