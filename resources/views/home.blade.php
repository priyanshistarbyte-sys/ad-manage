@extends('layouts.app')

@section('content')
<div style="max-width:640px;margin:40px auto;text-align:center">
    <div style="background:#12122a;border:1px solid var(--border);border-radius:14px;padding:40px 32px;box-shadow:0 20px 60px rgba(0,0,0,.4)">
        <i class="bi bi-graph-up-arrow" style="font-size:2.4rem;color:var(--purple)"></i>
        <h3 style="color:#fff;margin-top:12px">Welcome{{ currentUserName() !== '' ? ', '.currentUserName() : '' }} 👋</h3>
        <p style="color:var(--text-muted);font-size:13px;margin-top:8px">
            You're signed in to <strong style="color:#fff">ad-manage</strong>.
            Your session stays unlocked for 3 hours.
        </p>
    </div>
</div>
@endsection
