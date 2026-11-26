@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num   = fn ($v) => number_format((float) $v);
    $pct   = fn ($v) => number_format((float) $v, 0) . '%';
    $q     = fn (array $extra = []) => http_build_query(array_merge(array_filter([
                'app_id' => $filters['app_id'], 'campaign_id' => $filters['campaign_id'],
                'from' => $filters['from'], 'to' => $filters['to'],
            ], fn ($v) => $v !== null && $v !== ''), $extra));
@endphp

<div class="dash-subtitle" style="color:var(--text-muted);font-size:12.5px;margin-bottom:14px">
    @if ($singleDate)
        <strong style="color:var(--text)">{{ \Carbon\Carbon::parse($singleDate)->format('d M Y') }}</strong>
    @else
        {{ \Carbon\Carbon::parse($filters['from'])->format('d M Y') }} → {{ \Carbon\Carbon::parse($filters['to'])->format('d M Y') }}
    @endif
    · {{ $countryRows->count() }} countries
</div>

<div class="data-card">
    <div class="data-card-header">
        <span><i class="bi bi-flag"></i> Performance by Country</span>
    </div>
    <div class="table-wrap">
        <table class="ledger monthly sortable" style="width:100%;white-space:nowrap">
            <thead>
                <tr>
                    <th>COUNTRY</th><th>COST</th><th>TROAS</th><th>TOTAL_REV</th>
                    <th>AD_REV</th><th>CONVERT_REV</th><th>RENEW_REV</th>
                    <th>TRIAL</th><th>INSTALL</th><th>CPI</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($countryRows as $c)
                <tr>
                    <td data-sort="{{ $c->country_name }}">
                        <span style="color:var(--text-muted);font-size:11px">{{ $c->country_code }}</span>
                        {{ $c->country_name }}
                    </td>
                    <td>{{ $money($c->cost) }}</td>
                    <td><span class="{{ $c->troas >= 100 ? 'badge-profit' : 'badge-loss' }}">{{ $pct($c->troas) }}</span></td>
                    <td>{{ $money($c->total_rev) }}</td>
                    <td>{{ $money($c->ad_rev) }}</td>
                    <td>{{ $money($c->convert_rev) }}</td>
                    <td>{{ $money($c->renew_rev) }}</td>
                    <td>{{ $num($c->trial) }}</td>
                    <td>{{ $num($c->install) }}</td>
                    <td>{{ $money($c->cpi) }}</td>
                    <td>
                        @if ($c->geo_id)
                        <a href="{{ url('/?' . $q(['geo_id' => $c->geo_id])) }}" class="pill" style="text-decoration:none">DRILL IN</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:32px">No country data for these filters.</td></tr>
                @endforelse
            </tbody>
            @if ($countryRows->isNotEmpty())
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
                    <td>{{ $num($totals->install) }}</td>
                    <td>{{ $money($totals->cpi) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
