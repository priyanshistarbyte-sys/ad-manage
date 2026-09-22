@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
@endsection

@section('content')
@php
    $rangeFrom = \Carbon\Carbon::now()->startOfMonth()->toDateString();
    $rangeTo   = \Carbon\Carbon::now()->toDateString();
@endphp
<div style="max-width:1100px;margin:0 auto">

    {{-- Header + actions --}}
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div>
            <h5 style="color:#fff;margin:0"><i class="bi bi-google" style="color:var(--purple)"></i> Ad Accounts</h5>
            <p style="color:var(--text-muted);font-size:12px;margin:4px 0 0">
                Google Ads Manager (MCC) accounts and their API credentials.
            </p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
            {{-- Date range applied to Sync All AND each account's Sync button --}}
            <div class="entry-range-filter" style="width:230px" title="Date range used when syncing">
                <i class="bi bi-calendar-range entry-range-icon"></i>
                <input type="text" id="syncRange" class="form-control" placeholder="Sync date range"
                       autocomplete="off" readonly>
            </div>
            @if ($editing)
            <a href="{{ url('/connections') }}" class="btn-primary-custom" style="text-decoration:none">
                <i class="bi bi-plus-lg"></i> Add Account
            </a>
            @else
            <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#connModal">
                <i class="bi bi-plus-lg"></i> Add Account
            </button>
            @endif
            <form method="post" action="{{ url('/sync-all') }}" style="margin:0">
                @csrf
                <input type="hidden" name="from" class="js-sync-from" value="{{ $rangeFrom }}">
                <input type="hidden" name="to"   class="js-sync-to"   value="{{ $rangeTo }}">
                <button type="submit" class="btn-primary-custom" style="background:#1a7f37"
                        onclick="this.innerHTML='<i class=\'bi bi-hourglass-split\'></i> Syncing…';this.disabled=true;this.form.submit();">
                    <i class="bi bi-arrow-repeat"></i> Sync All
                </button>
            </form>
        </div>
    </div>

    {{-- Connection list --}}
    <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);color:#fff;font-weight:600">
            <i class="bi bi-google"></i> Ad Accounts <span style="color:var(--text-muted);font-weight:400">({{ $connections->count() }})</span>
        </div>
        <div class="table-wrap">
            <table class="ledger" style="width:100%">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Manager ID</th>
                        <th>Apps</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($connections as $c)
                    <tr>
                        <td style="font-weight:600;color:#fff">{{ $c->name }}</td>
                        <td>{{ $c->login_customer_id ?: '—' }}</td>
                        <td>{{ $c->apps_count }}</td>
                        <td>
                            @if ($c->isConfigured())
                                <span class="badge-profit">Configured</span>
                                @unless (\Illuminate\Support\Str::startsWith((string) $c->client_secret, 'GOCSPX-'))
                                <div style="color:#fbbf24;font-size:10px;margin-top:3px" title="Google OAuth client secrets start with GOCSPX-">
                                    <i class="bi bi-exclamation-triangle"></i> secret ≠ GOCSPX-…
                                </div>
                                @endunless
                            @else
                                <span class="badge-nodata">Incomplete credentials</span>
                            @endif
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            @if ($c->isConfigured())
                            <form method="post" action="{{ url('/connections/'.$c->id.'/sync') }}" style="display:inline"
                                  onsubmit="var b=this.querySelector('button');b.innerHTML='<i class=&quot;bi bi-hourglass-split&quot;></i> Syncing…';b.disabled=true;">
                                @csrf
                                <input type="hidden" name="from" class="js-sync-from" value="{{ $rangeFrom }}">
                                <input type="hidden" name="to"   class="js-sync-to"   value="{{ $rangeTo }}">
                                <button type="submit" class="btn-sm-custom" style="margin-right:8px;background:var(--purple);border:none;color:#fff;border-radius:6px;padding:3px 9px;font-size:11px;cursor:pointer" title="Sync only this ad account for the selected date range">
                                    <i class="bi bi-arrow-repeat"></i> Sync
                                </button>
                            </form>
                            <form method="post" action="{{ url('/connections/'.$c->id.'/test') }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn-sm-custom" style="margin-right:10px;background:#1a7f37;border:none;color:#fff;border-radius:6px;padding:3px 9px;font-size:11px;cursor:pointer" title="Test sign-in only">
                                    <i class="bi bi-plug"></i> Test
                                </button>
                            </form>
                            @endif
                            <a href="{{ url('/connections/'.$c->id.'/edit') }}" style="color:#a78bfa;margin-right:12px" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="{{ url('/connections/'.$c->id) }}"
                                  onsubmit="return confirm('Remove {{ $c->name }}? Apps using it will be unlinked.')" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:var(--red);cursor:pointer" title="Remove"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:26px">
                        No ad accounts yet. Click <strong style="color:#fff">Add Account</strong> to add your first Google Ads Manager account.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add / edit Ad Account modal --}}
<div class="modal fade" id="connModal" tabindex="-1" aria-labelledby="connModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="connModalLabel">
                    <i class="bi bi-{{ $editing ? 'pencil-square' : 'plus-circle' }}" style="color:var(--purple)"></i>
                    {{ $editing ? 'Edit Ad Account' : 'Add Ad Account (Google Ads Manager)' }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="color:var(--text-muted);font-size:12px;margin:0 0 18px">
                    Each Google Ads Manager (MCC) account has its own API credentials. Apps then pick which account they belong to.
                </p>

                <form method="post" action="{{ $editing ? url('/connections/'.$editing->id) : url('/connections') }}">
                    @csrf
                    @if ($editing) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Account Name / Label</label>
                            <input type="text" name="name" class="form-control" required maxlength="150"
                                   placeholder="e.g. Agency MCC — Messaging apps"
                                   value="{{ old('name', $editing->name ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Login Customer ID <small style="text-transform:none;color:#6b6b8a">(Manager acct)</small></label>
                            <input type="text" name="login_customer_id" class="form-control" maxlength="20"
                                   placeholder="123-456-7890"
                                   value="{{ old('login_customer_id', $editing->login_customer_id ?? '') }}">
                        </div>
                    </div>

                    <div style="border-top:1px solid var(--border);margin:18px 0 14px;padding-top:14px">
                        <span style="color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:600">
                            <i class="bi bi-key"></i> API Credentials
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Developer Token</label>
                            <input type="text" name="developer_token" class="form-control" maxlength="191"
                                   placeholder="Manager account → API Center"
                                   value="{{ old('developer_token', $editing->developer_token ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">OAuth Client ID</label>
                            <input type="text" name="client_id" class="form-control" maxlength="191"
                                   placeholder="…apps.googleusercontent.com"
                                   value="{{ old('client_id', $editing->client_id ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">OAuth Client Secret <small style="text-transform:none;color:#6b6b8a">(starts with GOCSPX-)</small></label>
                            <input type="text" name="client_secret" class="form-control" autocomplete="off" spellcheck="false"
                                   placeholder="GOCSPX-…" value="{{ old('client_secret', $editing->client_secret ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Refresh Token <small style="text-transform:none;color:#6b6b8a">(starts with 1//)</small></label>
                            <input type="text" name="refresh_token" class="form-control" autocomplete="off" spellcheck="false"
                                   placeholder="1//…" value="{{ old('refresh_token', $editing->refresh_token ?? '') }}">
                        </div>
                    </div>

                    @if ($errors->any())
                    <div style="color:var(--red);font-size:12px;margin-top:12px">{{ $errors->first() }}</div>
                    @endif

                    <div style="margin-top:20px;display:flex;gap:10px;align-items:center">
                        <button type="submit" class="btn-primary-custom">
                            <i class="bi bi-{{ $editing ? 'check-lg' : 'plus-lg' }}"></i> {{ $editing ? 'Save Changes' : 'Add Account' }}
                        </button>
                        @if ($editing)
                        <a href="{{ url('/connections') }}" style="color:var(--text-muted);font-size:12px;text-decoration:none">Cancel</a>
                        @else
                        <button type="button" class="btn-sm-custom" data-bs-dismiss="modal">Cancel</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- Sync date-range picker: sets from/to on every sync form (Sync All + per-account) --}}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
<script>
    jQuery(function ($) {
        var $box  = $('#syncRange');
        var start = moment('{{ $rangeFrom }}', 'YYYY-MM-DD');
        var end   = moment('{{ $rangeTo }}', 'YYYY-MM-DD');

        function apply(s, e) {
            $('.js-sync-from').val(s.format('YYYY-MM-DD'));
            $('.js-sync-to').val(e.format('YYYY-MM-DD'));
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

        apply(start, end); // seed the box + all hidden inputs with the default range
        $box.on('apply.daterangepicker', function (e, p) { apply(p.startDate, p.endDate); });
    });
</script>

@if ($editing || $errors->any())
{{-- Re-open the modal after an edit click or a validation error --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('connModal');
        if (el && window.bootstrap) { new bootstrap.Modal(el).show(); }
    });
</script>
@endif
@endsection
