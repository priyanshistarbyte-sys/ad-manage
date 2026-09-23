@extends('layouts.app')

@section('content')
<div style="max-width:760px;margin:0 auto">

    <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:22px 24px;margin-bottom:22px">
        <h5 style="color:#fff;margin:0 0 6px">
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

</div>
@endsection
