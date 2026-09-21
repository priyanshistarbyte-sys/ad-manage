@extends('layouts.app')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num   = fn ($v) => number_format((float) $v);
    $pct   = fn ($v) => number_format((float) $v, 0) . '%';
@endphp

<div style="max-width:1200px;margin:0 auto">

    {{-- Header + Sync button --}}
    <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;
                background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:20px 24px;margin-bottom:20px">
        <div>
            <h5 style="color:#fff;margin:0"><i class="bi bi-arrow-repeat" style="color:var(--purple)"></i> Sync All Campaigns</h5>
            <p style="color:var(--text-muted);font-size:12.5px;margin:6px 0 0">
                Pulls <strong>every campaign</strong> from every account your Ad Account credentials can access,
                for <strong>this month</strong> ({{ \Carbon\Carbon::parse($periodStart)->format('d M') }} → {{ \Carbon\Carbon::parse($periodEnd)->format('d M Y') }}).
                @if ($lastSynced)
                <br><span style="font-size:11px">Last synced: {{ \Carbon\Carbon::parse($lastSynced)->diffForHumans() }}</span>
                @endif
            </p>
        </div>
        <form method="post" action="{{ url('/sync-all') }}">
            @csrf
            <button type="submit" class="btn-primary-custom" style="background:#1a7f37;font-size:14px;padding:10px 20px"
                    onclick="this.innerHTML='<i class=\'bi bi-hourglass-split\'></i> Syncing…';this.disabled=true;this.form.submit();">
                <i class="bi bi-arrow-repeat"></i> Sync All Now
            </button>
        </form>
    </div>

    <div style="background:rgba(167,139,250,.06);border:1px solid #a78bfa33;border-radius:10px;
                padding:12px 18px;font-size:12.5px;color:var(--text-muted);margin-bottom:20px">
        <i class="bi bi-info-circle" style="color:#a78bfa"></i>
        Synced campaigns are matched to your apps automatically by <strong style="color:#fff">App ID</strong>
        (the campaign's target Play package). Add apps on the
        <a href="{{ url('/apps') }}" style="color:#a78bfa">Apps</a> page — no customer or campaign IDs needed.
    </div>

    @if ($connections->isEmpty())
    <div style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.3);color:#fbbf24;
                border-radius:8px;padding:12px 18px;font-size:13px;margin-bottom:18px">
        <i class="bi bi-exclamation-triangle"></i>
        No Ad Accounts yet. Add one with credentials on the
        <a href="{{ url('/connections') }}" style="color:#fcd34d">Ad Accounts</a> page first.
    </div>
    @endif

    {{-- Debug panel: full per-account sync response (Step: debugging Sync All) --}}
    @if (session('sync_debug'))
    @php $dbg = session('sync_debug'); @endphp
    <div class="data-card" style="margin-bottom:18px;border-color:#a78bfa55">
        <div class="data-card-header">
            <span><i class="bi bi-bug"></i> Sync Debug — last run</span>
            <span style="color:var(--text-muted);font-size:12px">
                {{ $dbg['range'] }} · {{ $dbg['accounts'] }} account(s) · {{ $dbg['campaigns'] }} campaign(s) · {{ $dbg['daily'] }} daily row(s)
            </span>
        </div>

        {{-- Discovery: what the credentials could reach --}}
        @foreach ($dbg['discovery'] as $d)
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-size:12px;color:var(--text-muted)">
            <strong style="color:#fff">{{ $d['connection'] }}</strong> —
            directly accessible: <span style="color:#a78bfa">{{ count($d['accessible']) }}</span>
            ({{ implode(', ', $d['accessible']) ?: '—' }}) ·
            managers found: <span style="color:#a78bfa">{{ count($d['found_managers']) }}</span>
            ({{ implode(', ', $d['found_managers']) ?: '—' }}) ·
            total targets queried: <span style="color:#fff">{{ $d['target_count'] }}</span>
        </div>
        @endforeach

        {{-- Per-account outcome --}}
        <div class="table-wrap">
            <table class="ledger" style="width:100%;white-space:nowrap;font-size:12px">
                <thead>
                    <tr><th>Customer ID</th><th>Login used</th><th>Name</th><th>Campaigns</th><th>Daily rows</th><th>Status</th><th>Error</th></tr>
                </thead>
                <tbody>
                    @forelse ($dbg['accounts_detail'] as $a)
                    <tr>
                        <td style="text-align:left;font-family:monospace">{{ $a['customer_id'] }}</td>
                        <td style="text-align:left;font-family:monospace">{{ $a['login'] }}</td>
                        <td style="text-align:left">{{ $a['name'] ?: '—' }}</td>
                        <td>{{ $a['campaigns'] }}</td>
                        <td>{{ $a['daily'] }}</td>
                        <td>
                            <span class="{{ $a['status'] === 'ok' ? 'badge-profit' : 'badge-loss' }}">{{ $a['status'] }}</span>
                        </td>
                        <td style="text-align:left;color:var(--red);white-space:normal;max-width:420px">{{ $a['error'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:20px">No accounts were queried.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (!empty($dbg['errors']))
        <div style="padding:12px 16px;border-top:1px solid var(--border)">
            <div style="color:var(--red);font-size:12px;font-weight:600;margin-bottom:6px">All errors ({{ count($dbg['errors']) }}):</div>
            <ul style="margin:0;padding-left:18px;color:var(--text-muted);font-size:11.5px">
                @foreach ($dbg['errors'] as $e)<li style="white-space:normal">{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif
    </div>
    @endif

    {{-- Results grouped by account --}}
    @forelse ($grouped as $accountLabel => $rows)
    <div class="data-card" style="margin-bottom:18px">
        <div class="data-card-header">
            <span><i class="bi bi-building"></i> {{ $accountLabel }}</span>
            <span style="color:var(--text-muted);font-size:12px">{{ $rows->count() }} campaign(s)</span>
        </div>
        <div class="table-wrap">
            <table class="ledger" style="width:100%;white-space:nowrap">
                <thead>
                    <tr>
                        <th>Campaign</th><th>Status</th><th>Type</th>
                        <th>Cost</th><th>Conversions</th><th>Conv. Value</th><th>ROAS</th>
                        <th>Impr.</th><th>Clicks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $s)
                    <tr>
                        <td style="text-align:left;font-weight:600;color:#fff">{{ $s->campaign_name ?: $s->campaign_id }}</td>
                        <td><span class="badge-nodata">{{ $s->status ?: '—' }}</span></td>
                        <td>{{ $s->channel_type ?: '—' }}</td>
                        <td>{{ $money($s->cost) }}</td>
                        <td>{{ $num($s->conversions) }}</td>
                        <td>{{ $money($s->conversions_value) }}</td>
                        <td><span class="{{ $s->roas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($s->roas) }}</span></td>
                        <td>{{ $num($s->impressions) }}</td>
                        <td>{{ $num($s->clicks) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @empty
    <div class="data-card">
        <div style="text-align:center;color:var(--text-muted);padding:36px">
            <i class="bi bi-inbox" style="font-size:1.8rem;display:block;margin-bottom:8px"></i>
            No campaigns synced yet. Click <strong style="color:#fff">Sync All Now</strong> to pull data from Google Ads.
        </div>
    </div>
    @endforelse
</div>
@endsection
