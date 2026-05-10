<!DOCTYPE html>
<html lang="es" class="h-full" style="--menu-color: {{ $branding['menu_color'] ?? '#d83a7c' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $metaTitle = trim((string) $__env->yieldContent('title', ($branding['site_name'] ?? 'EDA_SOCIAL')));
        $metaDescription = trim((string) ($branding['site_description'] ?? 'Plataforma de video social.'));
        if (isset($seoOgDescription) && is_string($seoOgDescription) && trim($seoOgDescription) !== '') {
            $metaDescription = trim($seoOgDescription);
        }
        $metaKeywords = trim((string) (\App\Support\PlatformConfig::get('site_keywords', 'videos, entretenimiento, tendencias')));
        $canonicalUrl = url()->current();
        $metaLogo = $branding['logo_url'] ?? null;
        $metaImage = $metaLogo ? \App\Support\MediaSrc::web($metaLogo) : asset('images/default-logo.svg');
        if (isset($seoOgImage) && is_string($seoOgImage) && trim($seoOgImage) !== '') {
            $metaImage = trim($seoOgImage);
        }
        if ($metaImage !== '' && ! preg_match('#^https?://#i', $metaImage)) {
            $metaImage = url($metaImage);
        }
        $metaOgType = (isset($seoOgType) && is_string($seoOgType) && trim($seoOgType) !== '') ? trim($seoOgType) : 'website';
    @endphp
    <title>@yield('title', ($branding['site_name'] ?? 'EDA_SOCIAL'))</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="keywords" content="{{ $metaKeywords }}">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="{{ $metaOgType }}">
    <meta property="og:site_name" content="{{ $branding['site_name'] ?? 'EDA_SOCIAL' }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if($metaImage !== '')
        <meta property="og:image" content="{{ $metaImage }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if($metaImage !== '')
        <meta name="twitter:image" content="{{ $metaImage }}">
    @endif
    @php
        $__gaId = '';
        try {
            $__gaId = trim((string) \App\Support\PlatformConfig::get('google_analytics_measurement_id', ''));
        } catch (\Throwable $e) {
            $__gaId = '';
        }
    @endphp
    @if($__gaId !== '')
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ rawurlencode($__gaId) }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($__gaId));
        </script>
    @endif
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
    @stack('styles')
</head>
<body class="min-h-full bg-gradient-to-b from-slate-50 via-white to-slate-100 text-slate-900">
@php
    $logo = $branding['logo_url'] ?? null;
    $logoSrc = $logo ? \App\Support\MediaSrc::web($logo) : '';
    if ($logoSrc === '') {
        $logoSrc = asset('images/default-logo.svg');
    }
    $authUser = auth()->user();
    $authAvatarSrc = $authUser && !empty($authUser->avatar_url) ? \App\Support\MediaSrc::web($authUser->avatar_url) : '';
    $authInitial = $authUser ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $authUser->name, 0, 1)) : '';
@endphp
<div class="mx-auto min-h-full max-w-6xl px-4 pb-16 pt-5 sm:px-6 lg:px-8">
    <header class="sticky top-3 z-30 mb-8 rounded-2xl border border-slate-200/80 bg-white/90 px-4 py-3 shadow-soft backdrop-blur-xl sm:px-6">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('explore.index') }}" class="group flex min-w-0 flex-1 items-center gap-3 rounded-xl outline-none ring-slate-300 transition hover:opacity-95 focus-visible:ring-2" aria-label="Inicio">
                <img class="h-[50px] w-auto max-w-[250px] shrink-0 object-contain" src="{{ $logoSrc }}" alt="Logo del sitio">
                <div class="min-w-0 text-left">
                    <strong class="block truncate text-base font-bold tracking-tight text-slate-900 sm:text-lg">{{ $branding['site_name'] ?? 'EDA_SOCIAL' }}</strong>
                    <p class="truncate text-xs text-slate-500 sm:text-sm">{{ \Illuminate\Support\Str::limit($branding['site_description'] ?? 'Plataforma de video social', 120) }}</p>
                </div>
            </a>
            <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-700 shadow-sm transition hover:bg-white hover:shadow md:hidden" id="blade-topbar-menu-toggle" aria-expanded="false" aria-controls="blade-topbar-menu-sheet" aria-label="Abrir menú">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <nav class="hidden items-center gap-2 md:flex" aria-label="Principal">
                <a href="{{ route('explore.index') }}" class="eda-btn-primary {{ request()->routeIs('explore.index') ? 'blade-nav-active opacity-90' : '' }} !px-4 !py-2 !text-sm">Inicio</a>
                @auth
                    <a href="{{ route('publish.create') }}" class="eda-btn-primary js-open-publish-modal !bg-slate-900 !px-4 !py-2 !text-sm hover:!brightness-110">Publicar</a>
                    <a href="{{ route('account.show') }}" class="eda-btn-secondary !px-4 !py-2 text-sm {{ request()->routeIs('account.*') ? 'border-slate-400 bg-slate-100' : '' }}">Mi cuenta</a>
                    @if(in_array(optional(auth()->user()->role)->name, ['admin', 'moderator'], true))
                        <a href="{{ route('admin.panel', ['section' => 'seo']) }}" class="eda-btn-secondary !px-4 !py-2 text-sm {{ request()->is('admin*') ? 'border-slate-400 bg-slate-100' : '' }}">Admin</a>
                    @endif
                    <form action="{{ route('logout') }}" method="post" class="inline">
                        @csrf
                        <button type="submit" class="eda-btn-secondary !px-4 !py-2 text-sm">Salir</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="eda-btn-primary !px-4 !py-2 !text-sm">Entrar</a>
                    <a href="{{ route('register') }}" class="eda-btn-secondary !px-4 !py-2 text-sm">Registro</a>
                @endauth
            </nav>
            @auth
                <a href="{{ route('account.show') }}" class="hidden md:inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full ring-2 ring-slate-200 overflow-hidden bg-slate-100" title="Cuenta">
                    @if($authAvatarSrc !== '')
                        <img src="{{ $authAvatarSrc }}" alt="Avatar de {{ $authUser->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="text-sm font-bold text-slate-700">{{ $authInitial }}</span>
                    @endif
                </a>
            @endauth
        </div>
    </header>

    <div id="blade-topbar-menu-backdrop" class="fixed inset-0 z-40 bg-slate-900/45 opacity-0 pointer-events-none transition-opacity duration-200 md:hidden" aria-hidden="true"></div>
    <div id="blade-topbar-menu-sheet" class="fixed inset-y-0 right-0 z-50 flex w-[min(20rem,100vw)] translate-x-full flex-col gap-2 border-l border-slate-200 bg-white p-4 pt-6 shadow-2xl transition-transform duration-300 ease-out md:hidden" role="dialog" aria-modal="true" aria-label="Menú">
        <nav class="flex flex-col gap-2" aria-label="Navegación móvil">
            <a href="{{ route('explore.index') }}" class="eda-btn-primary w-full justify-center">Inicio</a>
            @auth
                <div class="mb-2 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center overflow-hidden rounded-full ring-2 ring-white bg-slate-200">
                        @if($authAvatarSrc !== '')
                            <img src="{{ $authAvatarSrc }}" alt="Avatar de {{ $authUser->name }}" class="h-full w-full object-cover">
                        @else
                            <span class="text-sm font-bold text-slate-700">{{ $authInitial }}</span>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $authUser->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ '@' . ($authUser->username ?? 'usuario') }}</p>
                    </div>
                </div>
                <a href="{{ route('publish.create') }}" class="eda-btn-primary js-open-publish-modal w-full justify-center bg-slate-900 hover:!brightness-110">Publicar</a>
                <a href="{{ route('account.show') }}" class="eda-btn-secondary w-full justify-center">Mi cuenta</a>
                @if(in_array(optional(auth()->user()->role)->name, ['admin', 'moderator'], true))
                    <a href="{{ route('admin.panel', ['section' => 'seo']) }}" class="eda-btn-secondary w-full justify-center">Administración</a>
                @endif
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button type="submit" class="eda-btn-secondary w-full justify-center">Cerrar sesión</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="eda-btn-primary w-full justify-center">Iniciar sesión</a>
                <a href="{{ route('register') }}" class="eda-btn-secondary w-full justify-center">Crear cuenta</a>
            @endauth
        </nav>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 shadow-sm" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</div>
@auth
    @include('web.partials.publish-modal')
@endauth
<script>
(function () {
    var toggle = document.getElementById('blade-topbar-menu-toggle');
    var sheet = document.getElementById('blade-topbar-menu-sheet');
    var backdrop = document.getElementById('blade-topbar-menu-backdrop');
    if (!toggle || !sheet || !backdrop) return;

    function setOpen(open) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        backdrop.classList.toggle('opacity-0', !open);
        backdrop.classList.toggle('pointer-events-none', !open);
        backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
        sheet.classList.toggle('translate-x-full', !open);
        sheet.classList.toggle('translate-x-0', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = sheet.classList.contains('translate-x-full');
        setOpen(open);
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
        if (e.key === 'Escape' && !sheet.classList.contains('translate-x-full')) {
            setOpen(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) {
            setOpen(false);
        }
    });
})();
</script>
@auth
<script>
(function () {
    var root = document.getElementById('blade-publish-modal');
    var backdrop = document.getElementById('blade-publish-modal-backdrop');
    var closeBtn = document.getElementById('blade-publish-modal-close');
    var sheet = document.getElementById('blade-topbar-menu-sheet');
    var toggle = document.getElementById('blade-topbar-menu-toggle');
    var menuBackdrop = document.getElementById('blade-topbar-menu-backdrop');
    var mediaInput = document.getElementById('blade-publish-media');
    var previewBox = document.getElementById('blade-publish-media-preview');
    var previewHint = document.getElementById('blade-publish-media-preview-hint');
    var previewUrls = [];
    if (!root || !backdrop || !closeBtn) return;

    function revokeAllPreviewUrls() {
        previewUrls.forEach(function (u) {
            try {
                URL.revokeObjectURL(u);
            } catch (e) {}
        });
        previewUrls = [];
    }

    function clearPublishPreview() {
        revokeAllPreviewUrls();
        if (previewBox) {
            previewBox.innerHTML = '';
            previewBox.hidden = true;
        }
        if (previewHint) {
            previewHint.hidden = true;
        }
    }

    function inferMediaKind(file) {
        var t = (file.type || '').toLowerCase();
        if (t.indexOf('image/') === 0) return 'image';
        if (t.indexOf('video/') === 0) return 'video';
        var n = (file.name || '').toLowerCase();
        if (/\.(jpe?g|png|gif|webp|bmp|svg)$/.test(n)) return 'image';
        if (/\.(mp4|webm|mov|mkv|m4v|avi|ogv)$/.test(n)) return 'video';
        return 'other';
    }

    function buildPublishPreview(files) {
        clearPublishPreview();
        if (!previewBox || !files || !files.length) return;
        previewBox.hidden = false;
        if (previewHint) previewHint.hidden = false;
        previewBox.className = 'grid gap-3 sm:grid-cols-2';
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var kind = inferMediaKind(file);
            var url = URL.createObjectURL(file);
            previewUrls.push(url);
            var item = document.createElement('div');
            item.className = 'overflow-hidden rounded-xl border border-slate-200 bg-slate-50';
            var mediaWrap = document.createElement('div');
            mediaWrap.className = 'relative aspect-video bg-slate-900';
            var meta = document.createElement('div');
            meta.className = 'flex flex-col gap-1 border-t border-slate-200 bg-white px-3 py-2 text-xs';
            var kindEl = document.createElement('span');
            kindEl.className = 'font-semibold uppercase tracking-wide text-slate-500';
            var nameEl = document.createElement('span');
            nameEl.className = 'truncate text-slate-700';
            nameEl.textContent = file.name || ('Archivo ' + (i + 1));
            if (kind === 'image') {
                kindEl.textContent = 'Imagen';
                var img = document.createElement('img');
                img.src = url;
                img.className = 'h-full w-full object-contain';
                img.alt = 'Vista previa';
                mediaWrap.appendChild(img);
            } else if (kind === 'video') {
                kindEl.textContent = 'Vídeo';
                var vid = document.createElement('video');
                vid.src = url;
                vid.muted = true;
                vid.setAttribute('playsinline', '');
                vid.controls = true;
                vid.className = 'h-full w-full object-contain';
                vid.setAttribute('preload', 'metadata');
                mediaWrap.appendChild(vid);
            } else {
                kindEl.textContent = 'Archivo';
                var ph = document.createElement('p');
                ph.className = 'flex h-full items-center justify-center p-4 text-center text-slate-400';
                ph.textContent = 'Sin vista previa';
                mediaWrap.appendChild(ph);
            }
            meta.appendChild(kindEl);
            meta.appendChild(nameEl);
            item.appendChild(mediaWrap);
            item.appendChild(meta);
            previewBox.appendChild(item);
        }
    }

    if (mediaInput && previewBox) {
        mediaInput.addEventListener('change', function () {
            buildPublishPreview(mediaInput.files || []);
        });
    }

    function closeTopbarMenu() {
        if (!sheet || !menuBackdrop || !toggle) return;
        sheet.classList.add('translate-x-full');
        sheet.classList.remove('translate-x-0');
        menuBackdrop.classList.add('opacity-0', 'pointer-events-none');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function openModal() {
        root.classList.add('publish-modal-open');
        root.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        closeTopbarMenu();
        var first = document.getElementById('blade-publish-title');
        if (first) setTimeout(function () { first.focus(); }, 80);
    }

    function closeModal() {
        root.classList.remove('publish-modal-open');
        root.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('a.js-open-publish-modal').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            openModal();
        });
    });

    backdrop.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!root.classList.contains('publish-modal-open')) return;
        e.preventDefault();
        closeModal();
    });

    @if(session('open_publish_modal'))
    openModal();
    @endif
})();
</script>
@endauth
@stack('scripts')
</body>
</html>
