@extends('layouts.app')

@section('content')
<div style="max-width:760px;margin:0 auto">

    <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:22px 24px;margin-bottom:22px">
        <h5 style="color:var(--text-strong);margin:0 0 6px">
            <i class="bi bi-clock-history" style="color:var(--purple)"></i> History View Window
        </h5>
        <p style="color:var(--text-muted);font-size:12px;margin:0 0 16px">
            How many days of snapshots the <strong>View History</strong> page shows for a date, and how many
            trailing days the <strong>daily 01:00 auto-sync</strong> re-pulls from Google Ads (ending yesterday).
            <strong>No data is ever deleted</strong> — every snapshot is always kept; this only bounds the History
            page's view window and the cron's sync window.
        </p>

        <form method="post" action="{{ url('/settings') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">History visible days</label>
                    <input type="number" name="history_visible_days" class="form-control"
                           required min="0" max="3650"
                           value="{{ old('history_visible_days', $historyDays) }}">
                    <div style="color:var(--text-muted);font-size:11.5px;margin-top:6px">
                        Default 90. Use <strong>0</strong> to show the full history on the History page
                        (the auto-sync then falls back to 90 days).
                    </div>
                </div>
            </div>

            @if ($errors->any())
            <div style="color:var(--red);font-size:12px;margin-top:10px">{{ $errors->first() }}</div>
            @endif

            <div style="margin-top:16px">
                <button type="submit" class="btn-primary-custom">
                    <i class="bi bi-check-lg"></i> Save Settings
                </button>
            </div>
        </form>
    </div>

    <div id="profile" style="background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:22px 24px;margin-bottom:22px">
        <h5 style="color:var(--text-strong);margin:0 0 6px">
            <i class="bi bi-person-gear" style="color:var(--purple)"></i> Profile
        </h5>
        <p style="color:var(--text-muted);font-size:12px;margin:0 0 16px">
            Your name. Leave the password fields empty to keep your current password —
            the password is the <strong>6-digit PIN</strong> you sign in with.
        </p>

        @php $pErr = $errors->getBag('profile'); @endphp
        <form method="post" action="{{ url('/settings/profile') }}" autocomplete="off">
            @csrf
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required maxlength="100"
                           value="{{ old('name', $profile['name'] ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Current Password</label>
                    <div style="position:relative">
                        <input type="password" name="current_password" class="form-control" inputmode="numeric"
                               maxlength="6" pattern="\d{6}" placeholder="••••••" autocomplete="current-password"
                               style="padding-right:34px">
                        <button type="button" class="btn-eye-inline js-toggle-pw" title="Show / hide"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">New Password</label>
                    <div style="position:relative">
                        <input type="password" name="password" class="form-control" inputmode="numeric"
                               maxlength="6" pattern="\d{6}" placeholder="6 digits" autocomplete="new-password"
                               style="padding-right:34px">
                        <button type="button" class="btn-eye-inline js-toggle-pw" title="Show / hide"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirm Password</label>
                    <div style="position:relative">
                        <input type="password" name="password_confirmation" class="form-control" inputmode="numeric"
                               maxlength="6" pattern="\d{6}" placeholder="6 digits" autocomplete="new-password"
                               style="padding-right:34px">
                        <button type="button" class="btn-eye-inline js-toggle-pw" title="Show / hide"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
            </div>

            @if ($pErr->any())
            <div style="color:var(--red);font-size:12px;margin-top:10px">{{ $pErr->first() }}</div>
            @endif

            <div style="margin-top:16px">
                <button type="submit" class="btn-primary-custom">
                    <i class="bi bi-check-lg"></i> Save Profile
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.js-toggle-pw').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
    // Digits only in the PIN fields
    document.querySelectorAll('input[pattern="\\d{6}"]').forEach(i =>
        i.addEventListener('input', () => { i.value = i.value.replace(/\D/g, '').slice(0, 6); }));
</script>
@endsection
