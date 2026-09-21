@extends('layouts.app')

@section('content')
<div style="max-width:1100px;margin:0 auto">

    @if ($connections->isEmpty())
    <div style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.3);color:#fbbf24;
                border-radius:8px;padding:10px 16px;font-size:12.5px;margin-bottom:18px">
        <i class="bi bi-exclamation-triangle"></i>
        No ad accounts yet. Add a Google Ads Manager account on the
        <a href="{{ url('/connections') }}" style="color:#fcd34d">Ad Accounts</a> page so a sync can pull data. Apps here are
        matched to campaigns automatically by their App ID.
    </div>
    @endif

    {{-- Add / edit app --}}
    <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:22px 24px;margin-bottom:22px">
        <h5 style="color:#fff;margin:0 0 6px">
            <i class="bi bi-{{ $editing ? 'pencil-square' : 'plus-circle' }}" style="color:var(--purple)"></i>
            {{ $editing ? 'Edit App' : 'Add App' }}
        </h5>
        <p style="color:var(--text-muted);font-size:12px;margin:0 0 16px">
            Just the app name and its App ID. When you sync an ad account, every campaign that promotes this App ID
            is matched to it automatically and its data flows into the report and dashboard.
        </p>
        <form method="post" action="{{ $editing ? url('/apps/'.$editing->id) : url('/apps') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">App Name</label>
                    <input type="text" name="name" class="form-control" required maxlength="150"
                           placeholder="e.g. SmartConnect Messages"
                           value="{{ old('name', $editing->name ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">App ID (Play package)</label>
                    <input type="text" name="package_id" class="form-control" required maxlength="191"
                           placeholder="com.example.app"
                           value="{{ old('package_id', $editing->package_id ?? '') }}">
                </div>
            </div>

            @if ($errors->any())
            <div style="color:var(--red);font-size:12px;margin-top:10px">{{ $errors->first() }}</div>
            @endif

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
                <button type="submit" class="btn-primary-custom">
                    <i class="bi bi-{{ $editing ? 'check-lg' : 'plus-lg' }}"></i> {{ $editing ? 'Save Changes' : 'Add App' }}
                </button>
                @if ($editing)
                <a href="{{ url('/apps') }}" style="color:var(--text-muted);font-size:12px;text-decoration:none">Cancel</a>
                @endif
            </div>
        </form>
    </div>

    {{-- App list --}}
    <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);color:#fff;font-weight:600">
            <i class="bi bi-grid-3x3-gap"></i> Your Apps <span style="color:var(--text-muted);font-weight:400">({{ $apps->count() }})</span>
        </div>
        <div class="table-wrap">
            <table class="ledger" style="width:100%">
                <thead>
                    <tr>
                        <th>App</th>
                        <th>App ID</th>
                        <th>Data</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($apps as $app)
                    <tr>
                        <td style="font-weight:600;color:#fff">{{ $app->name }}</td>
                        <td><code style="color:#a78bfa">{{ $app->package_id }}</code></td>
                        <td>
                            @if (in_array($app->id, $syncedAppIds))
                                <span class="badge-profit" title="Campaigns matched to this App ID have synced data">Synced</span>
                            @else
                                <span class="badge-nodata" title="No campaign promoting this App ID has been synced yet">Awaiting sync</span>
                            @endif
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <a href="{{ url('/apps/'.$app->id.'/edit') }}" style="color:#a78bfa;margin-right:12px" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="{{ url('/apps/'.$app->id) }}"
                                  onsubmit="return confirm('Remove {{ $app->name }}?')" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:var(--red);cursor:pointer" title="Remove"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:26px">
                        No apps yet. Add your first app above.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
