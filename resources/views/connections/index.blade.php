@extends('layouts.app')

@section('content')
<div style="max-width:1100px;margin:0 auto">

    {{-- Add / edit connection --}}
    <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:22px 24px;margin-bottom:22px">
        <h5 style="color:#fff;margin:0 0 4px">
            <i class="bi bi-{{ $editing ? 'pencil-square' : 'plus-circle' }}" style="color:var(--purple)"></i>
            {{ $editing ? 'Edit Ad Account' : 'Add Ad Account (Google Ads Manager)' }}
        </h5>
        <p style="color:var(--text-muted);font-size:12px;margin:0 0 18px">
            Each Google Ads Manager (MCC) account has its own API credentials. Campaigns from every account these credentials reach are matched to your Apps by App ID.
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
                    <label class="form-label">Login Customer ID <small style="text-transform:none;color:#6b6b8a">(Manager acct)</small> <button type="button" class="cred-q" data-guide-step="2" title="How do I get this?"><i class="bi bi-question-circle"></i></button></label>
                    <input type="text" name="login_customer_id" class="form-control" maxlength="20"
                           placeholder="123-456-7890"
                           value="{{ old('login_customer_id', $editing->login_customer_id ?? '') }}">
                    <small class="field-hint">Top-right of your Manager account in Google Ads.</small>
                </div>
            </div>

            <div style="border-top:1px solid var(--border);margin:18px 0 14px;padding-top:14px">
                <span style="color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:600">
                    <i class="bi bi-key"></i> API Credentials
                </span>
            </div>

            {{-- How to get each credential --}}
            {{-- Opens the step-by-step credential guide popup --}}
            <div class="cred-help-bar">
                <i class="bi bi-lightbulb"></i>
                <span>Don't have these yet? It takes about 10 minutes, one time.</span>
                <button type="button" class="cred-help-btn" data-guide-step="1">
                    <i class="bi bi-journal-text"></i> Step-by-step guide
                </button>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Developer Token <button type="button" class="cred-q" data-guide-step="3" title="How do I get this?"><i class="bi bi-question-circle"></i></button></label>
                    <input type="text" name="developer_token" class="form-control" maxlength="191"
                           placeholder="Manager account → API Center"
                           value="{{ old('developer_token', $editing->developer_token ?? '') }}">
                    <small class="field-hint">Manager account → Admin → API Center.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">OAuth Client ID <button type="button" class="cred-q" data-guide-step="6" title="How do I get this?"><i class="bi bi-question-circle"></i></button></label>
                    <input type="text" name="client_id" class="form-control" maxlength="191"
                           placeholder="…apps.googleusercontent.com"
                           value="{{ old('client_id', $editing->client_id ?? '') }}">
                    <small class="field-hint">Google Cloud Console → Credentials → OAuth client (Web application).</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">OAuth Client Secret <small style="text-transform:none;color:#6b6b8a">(starts with GOCSPX-)</small> <button type="button" class="cred-q" data-guide-step="6" title="How do I get this?"><i class="bi bi-question-circle"></i></button></label>
                    <input type="password" name="client_secret" class="form-control" autocomplete="new-password" spellcheck="false"
                           placeholder="{{ $editing && filled($editing->client_secret) ? '•••••• saved — leave blank to keep' : 'GOCSPX-…' }}">
                    <small class="field-hint">Shown next to the Client ID in Google Cloud Console.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Refresh Token <small style="text-transform:none;color:#6b6b8a">(starts with 1//)</small> <button type="button" class="cred-q" data-guide-step="7" title="How do I get this?"><i class="bi bi-question-circle"></i></button></label>
                    <input type="password" name="refresh_token" class="form-control" autocomplete="new-password" spellcheck="false"
                           placeholder="{{ $editing && filled($editing->refresh_token) ? '•••••• saved — leave blank to keep' : '1//…' }}">
                    <small class="field-hint">OAuth 2.0 Playground with scope <code>…/auth/adwords</code>.</small>
                </div>
            </div>

            @if ($errors->any())
            <div style="color:var(--red);font-size:12px;margin-top:12px">{{ $errors->first() }}</div>
            @endif

            <div style="margin-top:18px;display:flex;gap:10px;align-items:center">
                <button type="submit" class="btn-primary-custom">
                    <i class="bi bi-{{ $editing ? 'check-lg' : 'plus-lg' }}"></i> {{ $editing ? 'Save Changes' : 'Add Account' }}
                </button>
                @if ($editing)
                <a href="{{ url('/connections') }}" style="color:var(--text-muted);font-size:12px;text-decoration:none">Cancel</a>
                @endif
            </div>
        </form>
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
                        <th>Apps with data</th>
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
                            <form method="post" action="{{ url('/connections/'.$c->id.'/test') }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn-sm-custom" style="margin-right:10px;background:#1a7f37;border:none;color:#fff;border-radius:6px;padding:3px 9px;font-size:11px;cursor:pointer" title="Test sign-in only">
                                    <i class="bi bi-plug"></i> Test
                                </button>
                            </form>
                            @endif
                            <a href="{{ url('/connections/'.$c->id.'/edit') }}" style="color:#a78bfa;margin-right:12px" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="{{ url('/connections/'.$c->id) }}"
                                  onsubmit="return confirm({{ \Illuminate\Support\Js::from('Remove '.$c->name.'? Its synced report data will be deleted too.') }})" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:var(--red);cursor:pointer" title="Remove"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:26px">
                        No ad accounts yet. Add your first Google Ads Manager account above.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@include('connections._guide')
@endsection
