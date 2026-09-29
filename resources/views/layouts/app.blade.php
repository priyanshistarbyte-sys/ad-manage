<!DOCTYPE html>
<html lang="en" data-root-url="{{ rtrim(url('/'), '/') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'ad-manage' }} · ad-manage</title>
    {{-- Theme must be applied before the first paint, or the page flashes the
         wrong palette. Deliberately inline and before the stylesheets. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('ad-theme');
                var theme = saved || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    {{-- Page-specific CDN styles (e.g. daterangepicker) load first… --}}
    @yield('head')
    {{-- …so our theme (style.css) loads last and always wins the cascade. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body>
@php $active = $activePage ?? ''; @endphp
<nav class="navbar navbar-dark navbar-main">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ url('/') }}">
            <i class="bi bi-graph-up-arrow me-2"></i>ads-manage
        </a>

        {{-- Hamburger — shown only below md (CSS) --}}
        <button class="nav-toggle" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#navMenu" aria-controls="navMenu" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>

        {{-- Inline on desktop, slide-in sidebar on mobile (Bootstrap offcanvas-md) --}}
        <div class="nav-collapse offcanvas-md offcanvas-end" tabindex="-1" id="navMenu" aria-labelledby="navMenuLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="navMenuLabel"><i class="bi bi-graph-up-arrow me-2"></i>ads-manage</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        data-bs-target="#navMenu" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="nav-links">
                    <a href="{{ url('/') }}"            class="nav-btn {{ $active==='home'        ? 'active':'' }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="{{ url('/analytics') }}"   class="nav-btn {{ $active==='analytics'   ? 'active':'' }}"><i class="bi bi-pie-chart"></i> Analytics</a>
                    <a href="{{ url('/apps') }}"        class="nav-btn {{ $active==='apps'        ? 'active':'' }}"><i class="bi bi-grid-3x3-gap"></i> Apps</a>
                    <a href="{{ url('/connections') }}" class="nav-btn {{ $active==='connections' ? 'active':'' }}"><i class="bi bi-google"></i> Ad Accounts</a>
                    <a href="{{ url('/sync-all') }}"    class="nav-btn {{ $active==='sync-all'    ? 'active':'' }}"><i class="bi bi-arrow-repeat"></i> Sync All</a>
                    <a href="{{ url('/settings') }}"    class="nav-btn {{ $active==='settings'    ? 'active':'' }}"><i class="bi bi-gear"></i> Settings</a>
                </div>
                <div class="nav-user">
                    {{-- Account menu: the name is the trigger, sign-out lives inside it --}}
                    <div class="nav-dropdown dropdown user-dropdown">
                        <a href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                           class="nav-btn nav-user-badge dropdown-toggle" title="Signed in as {{ currentUserName() }}">
                            <i class="bi bi-person-circle"></i> {{ currentUserName() ?: 'Account' }}
                            @if (isAdmin())<span class="admin-tag">admin</span>@endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end nav-dropdown-menu">
                            <li class="dropdown-header">{{ isAdmin() ? 'Admin' : 'Account' }}</li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/settings#profile') }}">
                                    <i class="bi bi-person-gear"></i> Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ url('/logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item danger">
                                        <i class="bi bi-box-arrow-right"></i> Log out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>

                    {{-- Light / dark switch. The icon shown is the theme you'd switch TO. --}}
                    <button type="button" id="themeToggle" class="nav-btn nav-btn-icon theme-toggle"
                            title="Switch theme" aria-label="Switch between light and dark theme">
                        <i class="bi bi-moon-stars theme-icon-dark"></i>
                        <i class="bi bi-sun theme-icon-light"></i>
                    </button>
                </div>
            </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<!-- Theme switch -->
<script>
    (function () {
        var toggle = document.getElementById('themeToggle');
        if (!toggle) return;
        toggle.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('ad-theme', next); } catch (e) { /* private mode — session only */ }
            // Canvas charts can't read CSS variables live; let pages repaint them.
            document.dispatchEvent(new CustomEvent('themechange', { detail: next }));
        });
    })();
</script>
@yield('scripts')
</body>
</html>
