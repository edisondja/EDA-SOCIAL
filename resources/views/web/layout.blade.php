<!DOCTYPE html>
<html lang="es" style="--menu-color: {{ $branding['menu_color'] ?? '#d83a7c' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', ($branding['site_name'] ?? 'EDA_SOCIAL'))</title>
    <link rel="stylesheet" href="{{ asset('css/eda-social.css') }}">
    <style>
        .blade-flash { padding: 10px 14px; margin: 0 18px 12px; border-radius: 10px; font-size: 14px; }
        .blade-flash--ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .blade-flash--err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .blade-nav-active { box-shadow: inset 0 0 0 2px rgba(255,255,255,.35); pointer-events: none; }
    </style>
</head>
<body>
@php
    $logo = $branding['logo_url'] ?? null;
    $logoSrc = $logo ? (\Illuminate\Support\Str::startsWith($logo, 'http://') || \Illuminate\Support\Str::startsWith($logo, 'https://') ? $logo : url($logo)) : null;
@endphp
<div class="app-shell">
    <header class="topbar">
        <a href="{{ route('explore.index') }}" class="brand-block brand-block-link" aria-label="Inicio">
            @if($logoSrc)
                <img class="brand-logo" src="{{ $logoSrc }}" alt="">
            @else
                <div class="brand-mark" aria-hidden="true">E</div>
            @endif
            <div class="brand-text">
                <strong>{{ $branding['site_name'] ?? 'EDA_SOCIAL' }}</strong>
                <p>{{ \Illuminate\Support\Str::limit($branding['site_description'] ?? 'Plataforma de video social', 140) }}</p>
            </div>
        </a>
        <button type="button" class="topbar-menu-toggle" id="blade-topbar-menu-toggle" aria-expanded="false" aria-controls="blade-topbar-menu-sheet">
            <span class="sr-only">Abrir o cerrar menú</span>
            <span class="topbar-menu-toggle-bars" aria-hidden="true"></span>
        </button>
        <div class="topbar-menu-backdrop" id="blade-topbar-menu-backdrop"></div>
        <div class="topbar-menu-sheet" id="blade-topbar-menu-sheet">
            <nav class="menu-actions" aria-label="Principal">
                <a href="{{ route('explore.index') }}" class="btn-primary nav-menu-btn {{ request()->routeIs('explore.index') ? 'blade-nav-active' : '' }}">Inicio</a>
                @auth
                    <a href="{{ route('publish.create') }}" class="btn-primary nav-menu-btn {{ request()->routeIs('publish.*') ? 'blade-nav-active' : '' }}">Publicar</a>
                    <a href="{{ route('account.show') }}" class="btn-primary nav-menu-btn {{ request()->routeIs('account.*') ? 'blade-nav-active' : '' }}">Mi cuenta</a>
                    @if(in_array(optional(auth()->user()->role)->name, ['admin', 'moderator'], true))
                        <a href="{{ route('admin.panel', ['section' => 'seo']) }}" class="btn-primary nav-menu-btn {{ request()->is('admin*') ? 'blade-nav-active' : '' }}">Administración</a>
                    @endif
                    <form action="{{ route('logout') }}" method="post" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn-primary nav-menu-btn">Cerrar sesión</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-primary nav-menu-btn">Iniciar sesión</a>
                @endauth
            </nav>
        </div>
    </header>

    @if (session('status'))
        <p class="blade-flash blade-flash--ok" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="blade-flash blade-flash--err" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</div>
<script>
(function () {
    var toggle = document.getElementById('blade-topbar-menu-toggle');
    var sheet = document.getElementById('blade-topbar-menu-sheet');
    var backdrop = document.getElementById('blade-topbar-menu-backdrop');
    var bars = toggle ? toggle.querySelector('.topbar-menu-toggle-bars') : null;
    if (!toggle || !sheet || !backdrop) return;

    function setOpen(open) {
        sheet.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (bars) bars.classList.toggle('is-open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!sheet.classList.contains('is-open'));
    });

    backdrop.addEventListener('click', function () {
        setOpen(false);
    });

    sheet.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sheet.classList.contains('is-open')) {
            setOpen(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && sheet.classList.contains('is-open')) {
            setOpen(false);
        }
    });
})();
</script>
</body>
</html>
