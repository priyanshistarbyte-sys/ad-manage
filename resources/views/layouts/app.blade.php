<!DOCTYPE html>
<html lang="en" data-root-url="{{ rtrim(url('/'), '/') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'ad-manage' }} · ad-manage</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body>
@php $active = $activePage ?? ''; @endphp
<nav class="navbar navbar-dark navbar-main">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ url('/') }}">
            <i class="bi bi-graph-up-arrow me-2"></i>ad-manage
        </a>
        <div class="nav-links d-none d-md-flex align-items-center gap-1 ms-3 me-auto">
            <a href="{{ url('/') }}"           class="nav-btn {{ $active==='home'        ? 'active':'' }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="{{ url('/apps') }}"       class="nav-btn {{ $active==='apps'        ? 'active':'' }}"><i class="bi bi-grid-3x3-gap"></i> Apps</a>
            <a href="{{ url('/connections') }}" class="nav-btn {{ $active==='connections' ? 'active':'' }}"><i class="bi bi-google"></i> Ad Accounts</a>
            <a href="{{ url('/sync-all') }}"   class="nav-btn {{ $active==='sync-all'    ? 'active':'' }}"><i class="bi bi-arrow-repeat"></i> Sync All</a>
        </div>
        <div class="d-flex align-items-center gap-3">
            @if (currentUserName() !== '')
            <span style="color:var(--text-muted);font-size:12px">
                <i class="bi bi-person-circle"></i>
                {{ currentUserName() }}@if (isAdmin()) <span style="color:#a78bfa">(admin)</span>@endif
            </span>
            @endif
            <a href="{{ url('/logout') }}" class="btn-primary-custom" style="text-decoration:none">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<main class="container-fluid" style="padding:24px 20px">
    @if (session('flash'))
    <div style="margin:0 auto 16px;background:var(--green-bg);border:1px solid #00552255;color:var(--green);
                border-radius:8px;padding:10px 16px;font-size:13px">
        <i class="bi bi-check-circle"></i> {{ session('flash') }}
    </div>
    @endif
    @if (session('flash_error'))
    <div style="margin:0 auto 16px;background:var(--red-bg);border:1px solid #55000033;color:var(--red);
                border-radius:8px;padding:10px 16px;font-size:13px">
        <i class="bi bi-exclamation-octagon"></i> {{ session('flash_error') }}
    </div>
    @endif
    @yield('content')
</main>

<script src="{{ asset('assets/js/app.js') }}"></script>
@yield('scripts')
</body>
</html>
