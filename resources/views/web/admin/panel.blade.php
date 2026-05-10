@extends('web.layout')

@section('title', 'Administración')

@section('content')
@php
    $__adminSettings = $settings;
    $s = function ($k, $d = '') use ($__adminSettings) {
        return $__adminSettings[$k] ?? $d;
    };
@endphp
<main class="admin-page-shell mx-auto max-w-6xl px-4 pb-16 pt-2 sm:px-6">
    <nav class="admin-menu mb-6 flex flex-wrap gap-2" aria-label="Secciones">
        @php
            $__adminTabs = [
                'seo' => ['label' => 'SEO', 'icon' => 'magnifying-glass'],
                'aspecto' => ['label' => 'Aspecto', 'icon' => 'swatch'],
                'banners' => ['label' => 'Banners', 'icon' => 'tag'],
                'integraciones' => ['label' => 'Colas', 'icon' => 'queue-list'],
                'verificacion' => ['label' => 'TXT', 'icon' => 'document-text'],
                'monitoreo' => ['label' => 'Monitoreo', 'icon' => 'cpu-chip'],
                'usuarios' => ['label' => 'Usuarios', 'icon' => 'user-group'],
                'videos' => ['label' => 'Videos', 'icon' => 'film'],
                'reportes' => ['label' => 'Reportes', 'icon' => 'no-symbol'],
                'reddit' => ['label' => 'Reddit / tendencias', 'icon' => 'chat-bubble-left'],
            ];
            if (optional(auth()->user()->role)->name === 'admin') {
                $__adminTabs['metricas'] = ['label' => 'Métricas', 'icon' => 'chart-bar'];
            }
        @endphp
        @foreach($__adminTabs as $key => $tab)
            <a href="{{ route('admin.panel', ['section' => $key]) }}"
               class="admin-tab {{ $section === $key ? 'active' : '' }}"
               style="display:inline-flex;align-items:center;gap:6px;">
                @include('web.partials.form-icon', ['name' => $tab['icon'], 'size' => 14])
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </nav>

    @if($section === 'metricas' && $adminTopVideos !== null)
        @php
            $topMaxViews = max((int) $adminTopVideos->max('views_count'), 1);
        @endphp
        <section class="admin-demand-board mb-6 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-soft ring-1 ring-slate-100 sm:p-6" aria-labelledby="admin-demand-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="admin-demand-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Top 10 — videos más demandados</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-snug text-slate-500">Cada fila muestra el <strong class="font-semibold text-slate-700">número de vistas</strong> de ese video. La barra compara cada uno con el más visto del top.</p>
                </div>
            </div>
            @if($adminTopVideos->isEmpty())
                <p class="mt-6 text-sm text-slate-600">Todavía no hay videos en el sistema.</p>
            @else
                <ol class="mt-6 space-y-4">
                    @foreach($adminTopVideos as $rank => $tv)
                        @php
                            $views = (int) $tv->views_count;
                            $barPct = $topMaxViews > 0 ? (int) round(min(100, ($views / $topMaxViews) * 100)) : 0;
                        @endphp
                        <li class="admin-demand-row">
                            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                                <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                                    <span class="inline-flex h-8 min-w-[2rem] shrink-0 items-center justify-center rounded-lg bg-slate-900 text-xs font-bold text-white sm:h-9 sm:min-w-[2.25rem]">{{ $rank + 1 }}</span>
                                    <a href="{{ $tv->playUrl() }}" class="min-w-0 truncate font-semibold text-slate-900 underline-offset-2 hover:text-brand hover:underline" title="{{ $tv->title }} — {{ number_format($views, 0, ',', '.') }} vistas">{{ $tv->title }}</a>
                                </div>
                                <span class="inline-flex shrink-0 flex-col items-end rounded-xl border border-slate-200/90 bg-slate-50 px-3 py-1.5 text-right shadow-sm ring-1 ring-slate-100 sm:flex-row sm:items-baseline sm:gap-2 sm:py-2" title="{{ number_format($views, 0, ',', '.') }} vistas">
                                    <strong class="tabular-nums text-lg font-bold leading-none tracking-tight text-slate-900 sm:text-xl">{{ number_format($views, 0, ',', '.') }}</strong>
                                    <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 sm:mt-0 sm:text-xs">vistas</span>
                                </span>
                            </div>
                            <div class="admin-demand-track mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-200/90" role="presentation">
                                <div class="admin-demand-fill h-full rounded-full transition-[width] duration-300 ease-out" style="width: {{ $barPct }}%; background: linear-gradient(90deg, var(--menu-color, #d83a7c), color-mix(in srgb, var(--menu-color, #d83a7c) 65%, #6366f1));"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif

    <div class="login-card admin-panel">
        <p class="hint-text">Resumen: {{ $dashboard['users_total'] }} usuarios · {{ $dashboard['videos_total'] }} videos · <strong>{{ number_format((int) ($dashboard['views_total'] ?? 0), 0, ',', '.') }}</strong> vistas totales · {{ $dashboard['videos_blocked'] }} bloqueados · {{ $dashboard['users_banned'] }} cuentas bloqueadas@php $pr = (int) ($dashboard['pending_reports'] ?? 0); @endphp@if($pr > 0) · <strong>{{ $pr }} reporte(s) de vídeo pendiente(s)</strong>@endif.</p>

        @if($section === 'seo')
            <h2>SEO y marca</h2>
            <form method="post" action="{{ route('admin.seo') }}" class="admin-seo-form">
                @csrf
                <input type="hidden" name="_section" value="{{ $section }}">
                <label class="field-label label-with-icon" for="admin_site_name">@include('web.partials.form-icon', ['name' => 'building']) Nombre del sitio</label>
                <input id="admin_site_name" type="text" name="site_name" value="{{ old('site_name', $s('site_name')) }}" maxlength="120">
                <label class="field-label label-with-icon" for="admin_site_desc">@include('web.partials.form-icon', ['name' => 'document-text']) Descripción</label>
                <textarea id="admin_site_desc" name="site_description" rows="3">{{ old('site_description', $s('site_description')) }}</textarea>
                <label class="field-label label-with-icon" for="admin_site_kw">@include('web.partials.form-icon', ['name' => 'tag']) Palabras clave</label>
                <input id="admin_site_kw" type="text" name="site_keywords" value="{{ old('site_keywords', $s('site_keywords')) }}" maxlength="500">
                <label class="field-label label-with-icon" for="admin_public_url">@include('web.partials.form-icon', ['name' => 'link']) URL pública del sitio</label>
                <input id="admin_public_url" type="text" name="public_site_url" value="{{ old('public_site_url', $s('public_site_url')) }}" placeholder="https://…" maxlength="255">
                <p class="hint-text" style="margin-top:6px;">Google y los navegadores penalizan <strong>contenido mixto</strong> (página en HTTPS con recursos en HTTP). En <strong>producción</strong> (<code>APP_ENV=production</code>) usá <code>APP_URL=https://…</code> y <strong>https://</strong> en «URL pública»; con <code>TRUSTED_PROXIES=*</code> si hay Nginx/Cloudflare. En local/staging no se fuerza HTTPS salvo <code>FORCE_HTTPS=true</code> en <code>.env</code>.</p>
                <label class="checkbox-with-icon">
                    @include('web.partials.form-icon', ['name' => 'arrow-path', 'size' => 16])
                    <span class="checkbox-with-icon-body checkbox-row" style="margin:0;"><input type="checkbox" name="use_router_links" {{ old('use_router_links', $s('use_router_links','1')) === '1' ? 'checked' : '' }}> Enlaces SPA (React Router) en el feed</span>
                </label>
                <p class="hint-text" style="margin-top:6px;">El sitemap <strong>solo incluye publicaciones</strong> activas y publicadas, en archivos de <strong>20 URLs</strong> (<code>/sitemap-posts-1.xml</code>, etc.) enlazados desde <code>/sitemap.xml</code>. Se <strong>regenera solo</strong> al crear o editar un post, importar, moderar vídeo o al pulsar «Generar» abajo.</p>
                <label class="field-label label-with-icon" for="admin_ga_measurement_id">@include('web.partials.form-icon', ['name' => 'chart-bar']) Google Analytics — ID de medición</label>
                <input id="admin_ga_measurement_id" type="text" name="google_analytics_measurement_id" value="{{ old('google_analytics_measurement_id', $s('google_analytics_measurement_id')) }}" maxlength="40" placeholder="G-XXXXXXXXXX o UA-XXXXXXX-X" autocomplete="off" autocapitalize="characters">
                <p class="hint-text" style="margin-top:6px;">Opcional. Pegá el ID de la propiedad GA4 (<strong>G-…</strong>) o Universal Analytics (<strong>UA-…</strong>). Dejalo vacío para no cargar el script en el sitio público.</p>
                <button type="submit" class="btn-primary label-with-icon">@include('web.partials.form-icon', ['name' => 'sparkles']) Guardar SEO</button>
            </form>
            <div style="margin-top:14px;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">
                <label class="field-label label-with-icon" for="admin_seo_sitemap_url">@include('web.partials.form-icon', ['name' => 'link']) Sitemap para Google</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input id="admin_seo_sitemap_url" type="text" value="{{ $seoSitemapUrl ?? url('/sitemap.xml') }}" readonly style="min-width:280px;flex:1;">
                    <button type="button" class="btn-secondary label-with-icon" id="admin_seo_copy_sitemap_btn">@include('web.partials.form-icon', ['name' => 'link']) Copiar enlace</button>
                    <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer" class="btn-primary label-with-icon">@include('web.partials.form-icon', ['name' => 'link']) Enviar a Google</a>
                </div>
                <p class="hint-text" style="margin-top:8px;">URLs de posts en el sitemap: <strong>{{ number_format((int) ($seoSitemapLinksCount ?? 0), 0, ',', '.') }}</strong> (más el índice <code>sitemap.xml</code> y un archivo por cada 20 posts).</p>
                <form id="admin_seo_generate_sitemap_form" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                    @csrf
                    <button type="submit" class="btn-secondary label-with-icon" id="admin_seo_generate_sitemap_btn">@include('web.partials.form-icon', ['name' => 'arrow-path']) Generar sitemap en /public ahora</button>
                </form>
                <div id="admin_sitemap_progress_wrap" style="display:none;margin-top:10px;">
                    <div style="height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden;">
                        <div id="admin_sitemap_progress_bar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--menu-color,#d83a7c),#6366f1);transition:width .3s;"></div>
                    </div>
                    <p id="admin_sitemap_progress_text" class="hint-text" style="margin-top:6px;">Generando sitemap… 0%</p>
                </div>
            </div>
            <script>
                (function () {
                    var btn = document.getElementById('admin_seo_copy_sitemap_btn');
                    var input = document.getElementById('admin_seo_sitemap_url');
                    if (!btn || !input) return;
                    btn.addEventListener('click', function () {
                        var text = input.value || '';
                        if (!text) return;
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(text).then(function () {
                                alert('Enlace del sitemap copiado.');
                            }).catch(function () {
                                input.select();
                                document.execCommand('copy');
                            });
                            return;
                        }
                        input.select();
                        document.execCommand('copy');
                    });
                })();
            </script>
            <script>
                (function () {
                    var form = document.getElementById('admin_seo_generate_sitemap_form');
                    if (!form) return;
                    var btn = document.getElementById('admin_seo_generate_sitemap_btn');
                    var wrap = document.getElementById('admin_sitemap_progress_wrap');
                    var bar = document.getElementById('admin_sitemap_progress_bar');
                    var text = document.getElementById('admin_sitemap_progress_text');
                    var csrf = document.querySelector('meta[name="csrf-token"]');
                    var statusUrl = @json(route('admin.sitemap_status', [], false));
                    var postUrl = @json(route('admin.sitemap', [], false));
                    var timer = null;
                    var optimistic = 0;

                    function setProgress(pct) {
                        var n = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
                        if (bar) bar.style.width = n + '%';
                        if (text) text.textContent = 'Generando sitemap… ' + n + '%';
                    }

                    function stopPolling() {
                        if (timer) {
                            clearInterval(timer);
                            timer = null;
                        }
                    }

                    function pollStatus() {
                        fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                            .then(function (r) { return r.ok ? r.json() : null; })
                            .then(function (data) {
                                if (!data) return;
                                var serverProgress = parseInt(data.progress || 0, 10);
                                optimistic = Math.min(95, Math.max(optimistic + 2, serverProgress));
                                setProgress(optimistic);
                                if (data.done) {
                                    setProgress(100);
                                    if (text) text.textContent = 'Sitemap generado correctamente (100%).';
                                    stopPolling();
                                    if (btn) btn.disabled = false;
                                }
                            })
                            .catch(function () {});
                    }

                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        if (!csrf || !csrf.getAttribute('content')) return;
                        if (btn) btn.disabled = true;
                        if (wrap) wrap.style.display = 'block';
                        optimistic = 8;
                        setProgress(optimistic);
                        stopPolling();
                        timer = setInterval(pollStatus, 900);

                        var fd = new FormData();
                        fd.append('_token', csrf.getAttribute('content'));
                        fd.append('_section', 'seo');

                        fetch(postUrl, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: fd,
                            credentials: 'same-origin',
                        })
                        .then(function (r) {
                            return r.text().then(function (t) {
                                var data = null;
                                if (t) {
                                    try { data = JSON.parse(t); } catch (e) { data = null; }
                                }
                                return { okHttp: r.ok, status: r.status, data: data };
                            });
                        })
                        .then(function (res) {
                            setProgress(100);
                            var data = res && res.data;
                            var ok = !!(data && data.ok);
                            var msg = ok ? 'Sitemap generado correctamente (100%).' : (data && data.message ? String(data.message) : 'No se pudo generar sitemap.');
                            if (!ok && res && !res.okHttp && res.status) {
                                msg = msg + ' (HTTP ' + res.status + ')';
                            }
                            if (text) text.textContent = msg;
                            stopPolling();
                            if (btn) btn.disabled = false;
                            if (ok) {
                                setTimeout(function () { window.location.reload(); }, 700);
                            }
                        })
                        .catch(function () {
                            stopPolling();
                            if (btn) btn.disabled = false;
                            if (text) text.textContent = 'No se pudo generar sitemap.';
                        });
                    });
                })();
            </script>
        @endif

        @if($section === 'aspecto')
            @php
                $aspectoHex = (string) old('menu_color', $s('menu_color', '#d83a7c'));
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $aspectoHex)) {
                    $aspectoHex = '#d83a7c';
                }
                $aspectoLogo = $branding['logo_url'] ?? null;
                $aspectoLogoSrc = $aspectoLogo && (\Illuminate\Support\Str::startsWith($aspectoLogo, 'http://') || \Illuminate\Support\Str::startsWith($aspectoLogo, 'https://'))
                    ? $aspectoLogo
                    : ($aspectoLogo ? url($aspectoLogo) : null);
            @endphp
            <h2>Aspecto</h2>
            <div class="aspecto-module">
                <header class="aspecto-module-header">
                    <h3 class="aspecto-module-subtitle">Identidad visual y taxonomía</h3>
                    <p class="aspecto-module-lead">Color de acentos en la cabecera, logo mostrado en la barra superior y categorías con las que se clasifican las publicaciones.</p>
                </header>

                <section class="aspecto-card" aria-labelledby="aspecto-card-color">
                    <div class="aspecto-card-title" id="aspecto-card-color">
                        <span class="aspecto-card-title-icon" aria-hidden="true">@include('web.partials.form-icon', ['name' => 'swatch', 'size' => 16])</span>
                        Color del menú
                    </div>
                    <p class="aspecto-card-hint">Formato hexadecimal (#RRGGBB). Debe coincidir con el tono principal de la marca.</p>
                    <form method="post" action="{{ route('admin.menu_color') }}" class="aspecto-form">
                        @csrf
                        <input type="hidden" name="_section" value="{{ $section }}">
                        <label class="sr-only" for="admin_menu_color">Color hexadecimal del menú</label>
                        <div class="aspecto-color-toolbar">
                            <div class="aspecto-color-swatch" style="background-color: {{ $aspectoHex }};" title="Vista previa del color" aria-hidden="true"></div>
                            <input id="admin_menu_color" class="aspecto-text-input" type="text" name="menu_color" pattern="#[0-9A-Fa-f]{6}" value="{{ old('menu_color', $s('menu_color','#d83a7c')) }}" required autocomplete="off">
                            <button type="submit" class="btn-primary label-with-icon aspecto-submit-btn">@include('web.partials.form-icon', ['name' => 'sparkles']) Guardar color</button>
                        </div>
                    </form>
                </section>

                <section class="aspecto-card" aria-labelledby="aspecto-card-logo">
                    <div class="aspecto-card-title" id="aspecto-card-logo">
                        <span class="aspecto-card-title-icon" aria-hidden="true">@include('web.partials.form-icon', ['name' => 'photo', 'size' => 16])</span>
                        Logo del sitio
                    </div>
                    <p class="aspecto-card-hint">Imagen cuadrada o horizontal (PNG o JPG). Recomendado para cabecera: <strong>250×50 px</strong>. Si no subes archivo, se usa el logo por defecto.</p>
                    @if($aspectoLogoSrc)
                        <div class="admin-logo-row aspecto-logo-preview-wrap">
                            <img class="admin-logo-preview aspecto-logo-preview-lg" src="{{ $aspectoLogoSrc }}" alt="Logo actual">
                            <span class="aspecto-logo-caption">Vista previa del logo en uso</span>
                        </div>
                    @endif
                    <form method="post" action="{{ route('admin.logo') }}" enctype="multipart/form-data" class="aspecto-form">
                        @csrf
                        <input type="hidden" name="_section" value="{{ $section }}">
                        <label class="field-label label-with-icon" for="admin_logo_file">@include('web.partials.form-icon', ['name' => 'arrow-down-tray']) Archivo de imagen</label>
                        <input id="admin_logo_file" type="file" name="logo" accept="image/*" required>
                        <button type="submit" class="btn-primary label-with-icon aspecto-submit-btn">@include('web.partials.form-icon', ['name' => 'arrow-down-tray']) Subir logo</button>
                    </form>
                </section>

                <section class="aspecto-card" aria-labelledby="aspecto-card-new-cat">
                    <div class="aspecto-card-title" id="aspecto-card-new-cat">
                        <span class="aspecto-card-title-icon" aria-hidden="true">@include('web.partials.form-icon', ['name' => 'folder-plus', 'size' => 16])</span>
                        Nueva categoría
                    </div>
                    <p class="aspecto-card-hint">El nombre se muestra en filtros y al publicar. El identificador interno (slug) se genera automáticamente.</p>
                    <form method="post" action="{{ route('admin.category') }}" class="aspecto-form">
                        @csrf
                        <input type="hidden" name="_section" value="{{ $section }}">
                        <div class="aspecto-field">
                            <label class="field-label label-with-icon" for="admin_new_cat">@include('web.partials.form-icon', ['name' => 'pencil-square']) Nombre visible</label>
                            <input id="admin_new_cat" type="text" name="name" value="{{ old('name') }}" maxlength="120" required placeholder="Ej. Música en vivo">
                        </div>
                        <button type="submit" class="btn-primary label-with-icon aspecto-submit-btn">@include('web.partials.form-icon', ['name' => 'folder-plus']) Crear categoría</button>
                    </form>
                </section>

                <section class="aspecto-card aspecto-card-foot" aria-labelledby="aspecto-card-cats-list">
                    <div class="aspecto-card-title" id="aspecto-card-cats-list">
                        <span class="aspecto-card-title-icon" aria-hidden="true">@include('web.partials.form-icon', ['name' => 'squares-2x2', 'size' => 16])</span>
                        Categorías actuales
                    </div>
                    <p class="aspecto-card-hint">Listado ordenado alfabéticamente ({{ $categories->count() }} en total).</p>
                    @if($categories->isEmpty())
                        <p class="aspecto-empty-cats">Aún no hay categorías. Crea la primera arriba.</p>
                    @else
                        <ul class="aspecto-category-list">
                            @foreach($categories as $c)
                                <li>{{ $c->name }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif

        @if($section === 'banners')
            @include('web.admin.banners', ['bannerTemplates' => $bannerTemplates, 'bannerSlotConfig' => $bannerSlotConfig])
        @endif

        @if($section === 'integraciones')
            <h2>Colas e integraciones</h2>
            @php
                $__connRedis = $integrationStatus['redis'] ?? [];
                $__connRabbit = $integrationStatus['rabbitmq'] ?? [];
                $__badgeOk = 'display:inline-block;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:700;background:#dcfce7;color:#166534;';
                $__badgeBad = 'display:inline-block;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:700;background:#fee2e2;color:#991b1b;';
                $__badgeNeutral = 'display:inline-block;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:700;background:#e2e8f0;color:#475569;';
            @endphp
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-bottom:16px;">
                <div class="aspecto-card" style="margin:0;">
                    <div class="aspecto-card-title" style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                        <span>Redis — caché</span>
                        @if(($__connRedis['reachable'] ?? null) === true)
                            <span style="{{ $__badgeOk }}">Conectado</span>
                        @elseif(($__connRedis['reachable'] ?? null) === false)
                            <span style="{{ $__badgeBad }}">No conectado</span>
                        @else
                            <span style="{{ $__badgeNeutral }}">{{ $__connRedis['label'] ?? 'N/D' }}</span>
                        @endif
                    </div>
                    <p class="hint-text" style="margin:8px 0 0;font-size:12px;line-height:1.45;">{{ $__connRedis['detail'] ?? '' }}</p>
                    <p class="hint-text" style="margin:6px 0 0;font-size:11px;">Driver de caché actual: <strong>{{ $integrationStatus['cache_driver'] ?? '—' }}</strong>@if(!empty($__connRedis['uses_redis_for_cache'])) · La app usa Redis para caché.@else · La app <strong>no</strong> usa Redis para caché (solo se prueba conectividad si hay REDIS_HOST).@endif Extensión phpredis: <strong>{{ !empty($integrationStatus['redis_extension']) ? 'sí' : 'no' }}</strong></p>
                </div>
                <div class="aspecto-card" style="margin:0;">
                    <div class="aspecto-card-title" style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                        <span>RabbitMQ — broker</span>
                        @if(($__connRabbit['amqp_reachable'] ?? false) && ($__connRabbit['management_ok'] ?? false))
                            <span style="{{ $__badgeOk }}">Conectado</span>
                        @elseif(($__connRabbit['amqp_reachable'] ?? false) || ($__connRabbit['management_ok'] ?? false))
                            <span style="{{ $__badgeNeutral }}">{{ $__connRabbit['label'] ?? 'Parcial' }}</span>
                        @elseif(($__connRabbit['amqp_reachable'] ?? null) === false && ($__connRabbit['management_ok'] ?? null) === false)
                            <span style="{{ $__badgeBad }}">No conectado</span>
                        @else
                            <span style="{{ $__badgeNeutral }}">{{ $__connRabbit['label'] ?? 'N/D' }}</span>
                        @endif
                    </div>
                    <p class="hint-text" style="margin:8px 0 0;font-size:12px;line-height:1.45;">{{ $__connRabbit['detail'] ?? '' }}</p>
                    <p class="hint-text" style="margin:6px 0 0;font-size:11px;">Cola Laravel: <strong>{{ $integrationStatus['queue_connection'] ?? '—' }}</strong> (driver de jobs; puede ser <code>database</code> aunque Rabbit esté instalado).</p>
                </div>
            </div>
            @php
                $__rabbitMgmtUi = rtrim((string) env('RABBITMQ_MANAGEMENT_URL', ''), '/');
                if ($__rabbitMgmtUi === '' && trim((string) env('RABBITMQ_HOST', '')) !== '') {
                    $__rabbitMgmtUi = 'http://' . trim((string) env('RABBITMQ_HOST')) . ':' . (int) env('RABBITMQ_MANAGEMENT_PORT', 15672);
                }
            @endphp

            <section class="admin-queue-live mb-6 rounded-xl border border-slate-200/90 bg-white p-4 shadow-soft ring-1 ring-slate-100" aria-labelledby="admin-queue-live-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 id="admin-queue-live-heading" class="text-base font-bold text-slate-900">Colas en tiempo real</h3>
                    <span id="admin-queue-live-updated" class="text-xs text-slate-500"></span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-secondary label-with-icon" id="admin_worker_media_start_btn">@include('web.partials.form-icon', ['name' => 'sparkles', 'size' => 14]) Encender worker media</button>
                    <span id="admin_worker_media_badge" class="text-xs" style="display:inline-block;padding:2px 10px;border-radius:9999px;font-weight:700;background:#e2e8f0;color:#475569;">Estado: desconocido</span>
                    <span id="admin_worker_media_pid" class="text-xs text-slate-500"></span>
                </div>
                @if($__rabbitMgmtUi !== '')
                    <p class="mt-2 flex flex-wrap items-center gap-2" style="margin-top:10px;">
                        <a href="{{ $__rabbitMgmtUi }}/" target="_blank" rel="noopener noreferrer" class="btn-secondary label-with-icon" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                            @include('web.partials.form-icon', ['name' => 'link', 'size' => 14])
                            Panel web RabbitMQ (management)
                        </a>
                        <span class="text-xs text-slate-500 break-all">{{ $__rabbitMgmtUi }}/</span>
                    </p>
                @else
                    <p class="hint-text mt-2" style="margin-top:8px;">Para mostrar el enlace al plugin web, definí <code>RABBITMQ_MANAGEMENT_URL</code> o <code>RABBITMQ_HOST</code> (+ puerto <code>RABBITMQ_MANAGEMENT_PORT</code>, por defecto 15672) en <code>.env</code>.</p>
                @endif
                <p class="hint-text mt-1" style="margin-top:6px;">En <strong>RabbitMQ</strong>: “en proceso” = mensajes sin acuse (<code>unacked</code>) que el worker está ejecutando. “En espera” = listos para consumir. Con <strong>database</strong>: “en proceso” = filas con <code>reserved_at</code> no nulo.</p>
                <p id="admin-queue-live-meta" class="hint-text mt-1 text-xs" style="margin-top:6px;"></p>
                <p id="admin-queue-live-error" class="mt-2 hidden text-sm text-red-600" role="alert"></p>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[420px] border-collapse text-sm" id="admin-queue-live-table">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="py-2 pr-3">Cola</th>
                                <th class="py-2 pr-3 text-right">En espera</th>
                                <th class="py-2 pr-3 text-right">En proceso</th>
                                <th class="py-2 pr-3 text-right">Consumidores</th>
                                <th class="py-2 text-right">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="admin-queue-live-tbody"></tbody>
                    </table>
                </div>
            </section>
            <script>
            (function () {
                var url = @json(route('admin.queue_status', [], false));
                var workerStatusUrl = @json(route('admin.worker_media_status', [], false));
                var workerStartUrl = @json(route('admin.worker_media_start', [], false));
                var tbody = document.getElementById('admin-queue-live-tbody');
                var errEl = document.getElementById('admin-queue-live-error');
                var metaEl = document.getElementById('admin-queue-live-meta');
                var updatedEl = document.getElementById('admin-queue-live-updated');
                var workerBtn = document.getElementById('admin_worker_media_start_btn');
                var workerBadge = document.getElementById('admin_worker_media_badge');
                var workerPid = document.getElementById('admin_worker_media_pid');
                if (!tbody || !url) return;

                function esc(s) {
                    var d = document.createElement('div');
                    d.textContent = s;
                    return d.innerHTML;
                }

                function render(data) {
                    if (errEl) {
                        if (data.error) {
                            errEl.textContent = data.error;
                            errEl.classList.remove('hidden');
                        } else {
                            errEl.textContent = '';
                            errEl.classList.add('hidden');
                        }
                    }
                    if (metaEl) {
                        metaEl.textContent = 'Driver: ' + (data.driver || '—') + ' · Fuente: ' + (data.source || '—') + ' · Jobs fallidos (tabla): ' + (data.failed_jobs != null ? data.failed_jobs : '—');
                    }
                    if (updatedEl) {
                        updatedEl.textContent = data.updated_at ? ('Actualizado: ' + data.updated_at) : '';
                    }
                    var rows = data.queues || [];
                    if (!rows.length) {
                        tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-slate-500">Sin datos de colas (o lista vacía).</td></tr>';
                        return;
                    }
                    tbody.innerHTML = rows.map(function (q) {
                        var cons = q.consumers != null ? String(q.consumers) : '—';
                        var st = q.state != null ? q.state : '—';
                        return '<tr class="border-t border-slate-100">' +
                            '<td class="py-2 pr-3 font-medium text-slate-800">' + esc(q.name || '') + '</td>' +
                            '<td class="py-2 pr-3 text-right tabular-nums">' + esc(String(q.waiting != null ? q.waiting : 0)) + '</td>' +
                            '<td class="py-2 pr-3 text-right tabular-nums font-semibold text-slate-900">' + esc(String(q.processing != null ? q.processing : 0)) + '</td>' +
                            '<td class="py-2 pr-3 text-right tabular-nums">' + esc(cons) + '</td>' +
                            '<td class="py-2 text-right text-xs text-slate-600">' + esc(st) + '</td>' +
                            '</tr>';
                    }).join('');
                }

                function tick() {
                    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.json(); })
                        .then(render)
                        .catch(function () {
                            if (errEl) {
                                errEl.textContent = 'No se pudo cargar el estado de colas.';
                                errEl.classList.remove('hidden');
                            }
                        });
                }

                function renderWorker(data) {
                    if (!workerBadge) return;
                    var running = !!(data && data.running);
                    workerBadge.textContent = running ? 'Worker media: activo' : 'Worker media: apagado';
                    workerBadge.style.background = running ? '#dcfce7' : '#fee2e2';
                    workerBadge.style.color = running ? '#166534' : '#991b1b';
                    if (workerPid) {
                        workerPid.textContent = data && data.pid ? ('PID: ' + data.pid) : '';
                    }
                    if (workerBtn) {
                        workerBtn.disabled = running;
                    }
                }

                function tickWorker() {
                    fetch(workerStatusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (data) { if (data) renderWorker(data); })
                        .catch(function () {});
                }

                if (workerBtn) {
                    workerBtn.addEventListener('click', function () {
                        workerBtn.disabled = true;
                        fetch(workerStartUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
                            },
                            credentials: 'same-origin'
                        })
                        .then(function (r) { return r.json().catch(function () { return null; }); })
                        .then(function (data) {
                            if (!data || !data.ok) {
                                if (errEl) {
                                    errEl.textContent = (data && data.message) ? data.message : 'No se pudo encender el worker media.';
                                    errEl.classList.remove('hidden');
                                }
                                workerBtn.disabled = false;
                                return;
                            }
                            renderWorker(data);
                        })
                        .catch(function () {
                            if (errEl) {
                                errEl.textContent = 'No se pudo encender el worker media.';
                                errEl.classList.remove('hidden');
                            }
                            workerBtn.disabled = false;
                        });
                    });
                }
                tick();
                tickWorker();
                setInterval(tick, 2800);
                setInterval(tickWorker, 5000);
            })();
            </script>

            <pre class="admin-json-preview" role="region" aria-label="Estado de integraciones">@json($integrationStatus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)</pre>
            <form method="post" action="{{ route('admin.integrations') }}">
                @csrf
                <input type="hidden" name="_section" value="{{ $section }}">
                <label class="checkbox-with-icon">
                    @include('web.partials.form-icon', ['name' => 'cpu-chip', 'size' => 16])
                    <span class="checkbox-with-icon-body checkbox-row" style="margin:0;"><input type="checkbox" name="feature_redis_cache" {{ $s('feature_redis_cache')==='1' ? 'checked' : '' }}> Activar Redis para caché (requiere extensión y .env)</span>
                </label>
                <label class="checkbox-with-icon">
                    @include('web.partials.form-icon', ['name' => 'queue-list', 'size' => 16])
                    <span class="checkbox-with-icon-body checkbox-row" style="margin:0;"><input type="checkbox" name="feature_rabbit_queue" {{ $s('feature_rabbit_queue')==='1' ? 'checked' : '' }}> Activar RabbitMQ (requiere paquete y .env)</span>
                </label>
                <button type="submit" class="btn-primary label-with-icon">@include('web.partials.form-icon', ['name' => 'sparkles']) Guardar</button>
            </form>

            <details class="admin-redis-docs" style="margin-top:18px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:12px 14px;">
                <summary class="cursor-pointer text-sm font-semibold text-slate-800" style="cursor:pointer;">Redis — instalación para caché Laravel</summary>
                <div class="mt-3 space-y-3 text-sm text-slate-700" style="margin-top:12px;">
                    <p class="hint-text" style="margin:0;">Redis acelera la caché de consultas (por ejemplo el explorar) y los deduplicados de colas en hover. Ejecutá en el <strong>servidor</strong> (Ubuntu/Debian) con <code>sudo</code> salvo que indique otro modo.</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">1) Instalar el servidor Redis</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">sudo apt update
sudo apt install -y redis-server
sudo systemctl enable --now redis-server
redis-cli ping</pre>
                    <p class="hint-text" style="margin:4px 0 0;">La última línea debe responder <code>PONG</code>. El servicio escucha por defecto en <code>127.0.0.1:6379</code>.</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">2) Extensión PHP <code>phpredis</code> (requerida para <code>REDIS_CLIENT=phpredis</code>)</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;"># Sustituí 8.4 por tu versión de PHP (php -v)
sudo apt install -y php8.4-redis
sudo systemctl reload php8.4-fpm   # o apache2, según tu stack
php -m | grep -i redis</pre>
                    <p class="hint-text" style="margin:4px 0 0;">Si no hay paquete para tu versión: <code>sudo pecl install redis</code> y habilitá la extensión en el <code>php.ini</code> del FPM/CLI correspondiente.</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">3) Variables en <code>.env</code> del backend</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">CACHE_DRIVER=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
# Base lógica separada de la default (sesiones/cola pueden usar otra)
REDIS_CACHE_DB=1</pre>
                    <p class="hint-text" style="margin:4px 0 0;">Tras cambiar <code>.env</code>: <code>php artisan config:clear</code> (y si usás <code>config:cache</code> en producción, volvé a generar la caché). En este panel, activá además la casilla <strong>«Activar Redis para caché»</strong> y guardá para que la app use Redis en las rutas que lo comprueban.</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">4) Comprobar desde Laravel</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">cd /ruta/al/backend &amp;&amp; php artisan tinker --execute="Illuminate\Support\Facades\Cache::store('redis')->put('_ping',1,5); echo Cache::store('redis')->get('_ping');"</pre>
                    <p class="hint-text" style="margin:4px 0 0;">La tarjeta <strong>Redis — caché</strong> arriba debe mostrar «Conectado» cuando <code>REDIS_HOST</code> apunta a un Redis accesible.</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">5) Producción y seguridad</p>
                    <p class="hint-text" style="margin:0;">En <code>/etc/redis/redis.conf</code> podés enlazar solo a localhost (<code>bind 127.0.0.1 ::1</code>) y definir <code>requirepass</code> si Redis no es local; entonces poné la misma contraseña en <code>REDIS_PASSWORD</code> del <code>.env</code> (entre comillas si tiene caracteres especiales).</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">Alternativa: Docker (desarrollo)</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">docker run -d --name redis-cache -p 6379:6379 redis:7-alpine redis-server --appendonly yes</pre>
                    <p class="hint-text" style="margin:4px 0 0;">En ese caso <code>REDIS_HOST=127.0.0.1</code> si el contenedor publica el puerto en la máquina host.</p>
                </div>
            </details>

            <details class="admin-rabbitmq-docs" style="margin-top:18px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:12px 14px;">
                <summary class="cursor-pointer text-sm font-semibold text-slate-800" style="cursor:pointer;">RabbitMQ — instalación y usuario para colas (HLS / portadas)</summary>
                <div class="mt-3 space-y-3 text-sm text-slate-700" style="margin-top:12px;">
                    <p class="hint-text" style="margin:0;">Ejecutá estos comandos en el <strong>servidor</strong> (Ubuntu/Debian) como root o con <code>sudo</code>. Cambiá la contraseña de ejemplo por una segura.</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">1) Instalar RabbitMQ</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">sudo apt update
sudo apt install -y rabbitmq-server
sudo systemctl enable --now rabbitmq-server</pre>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">2) Panel web de administración (API de métricas / colas)</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">sudo rabbitmq-plugins enable rabbitmq_management</pre>
                    <p class="hint-text" style="margin:4px 0 0;">Por defecto: <code>http://IP-DEL-SERVIDOR:15672</code> — usuario <code>guest</code> solo funciona desde localhost.</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">Si falla con <code>Error: :plugins_dir_does_not_exist</code> (Ubuntu 24.04+ / 25)</p>
                    <p class="hint-text" style="margin:0 0 6px;">El CLI no encuentra la carpeta de plugins. Averiguá la ruta real (cambiá el glob por lo que exista en tu servidor):</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">ls /usr/lib/rabbitmq/lib/
ls /usr/lib/rabbitmq/lib/rabbitmq_server-*/plugins | head</pre>
                    <p class="hint-text" style="margin:6px 0 6px;">Creá el directorio fijo para plugins extra (a veces falta en paquetes recientes) y en <code>/etc/rabbitmq/rabbitmq-env.conf</code> definí <code>PLUGINS_DIR</code> (sin prefijo <code>RABBITMQ_</code>) con <strong>dos rutas</strong> separadas por <code>:</code>:</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">sudo mkdir -p /usr/lib/rabbitmq/plugins
sudo chown rabbitmq:rabbitmq /usr/lib/rabbitmq/plugins
# sudo nano /etc/rabbitmq/rabbitmq-env.conf  —  una línea (ajustá la carpeta rabbitmq_server-* real):
# PLUGINS_DIR=/usr/lib/rabbitmq/plugins:/usr/lib/rabbitmq/lib/rabbitmq_server-4.0.5/plugins
sudo systemctl restart rabbitmq-server
sudo rabbitmq-plugins enable rabbitmq_management</pre>
                    <p class="hint-text" style="margin:6px 0 0;">Si <code>rabbitmq_server-*</code> no existe, el paquete está incompleto: probá <code>sudo apt install --reinstall rabbitmq-server</code> o los paquetes oficiales de <a href="https://www.rabbitmq.com/docs/install-debian" target="_blank" rel="noopener noreferrer">rabbitmq.com/docs/install-debian</a>, o Docker <code>rabbitmq:3-management</code>.</p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">3) Virtual host dedicado (opcional, recomendado)</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">sudo rabbitmqctl add_vhost eda_social</pre>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">4) Usuario y contraseña para Laravel / workers</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">sudo rabbitmqctl add_user eda_worker 'CAMBIA_ESTA_CONTRASENA_SEGURA'
sudo rabbitmqctl set_permissions -p eda_social eda_worker ".*" ".*" ".*"
sudo rabbitmqctl set_user_tags eda_worker management</pre>
                    <p class="hint-text" style="margin:4px 0 0;">Si no usás vhost propio, usá <code>/</code> en lugar de <code>eda_social</code>:<br><code>sudo rabbitmqctl set_permissions -p / eda_worker ".*" ".*" ".*"</code></p>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">5) Comprobar usuarios y colas</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">sudo rabbitmqctl list_users
sudo rabbitmqctl list_vhosts
sudo rabbitmqctl list_queues -p eda_social</pre>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">6) Variables en <code>.env</code> del backend (ejemplo)</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=eda_worker
RABBITMQ_PASSWORD=CAMBIA_ESTA_CONTRASENA_SEGURA
RABBITMQ_VHOST=eda_social
# API del panel (métricas en vivo; mismo usuario o uno solo-lectura)
RABBITMQ_MANAGEMENT_URL=http://127.0.0.1:15672
RABBITMQ_MANAGEMENT_USER=eda_worker
RABBITMQ_MANAGEMENT_PASSWORD=CAMBIA_ESTA_CONTRASENA_SEGURA
RABBITMQ_ADMIN_QUEUE_NAMES=media,default</pre>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">7) Worker de Laravel (cola <code>media</code> — HLS y portadas)</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">cd /var/www/ruta-a-tu/backend &amp;&amp; php artisan queue:work rabbitmq --queue=media,default --tries=3 --timeout=1200</pre>

                    <p class="font-semibold text-slate-900" style="margin:10px 0 4px;">Alternativa: Docker (desarrollo)</p>
                    <pre class="admin-json-preview" style="margin:0;font-size:11px;white-space:pre-wrap;">docker run -d --name rabbitmq -p 5672:5672 -p 15672:15672 \
  -e RABBITMQ_DEFAULT_USER=eda_worker \
  -e RABBITMQ_DEFAULT_PASS=CAMBIA_ESTA_CONTRASENA_SEGURA \
  rabbitmq:3-management</pre>
                    <p class="hint-text" style="margin:4px 0 0;">En Docker el vhost por defecto es <code>/</code> (<code>RABBITMQ_VHOST=/</code>).</p>
                </div>
            </details>
        @endif

        @if($section === 'monitoreo')
            @php
                $mon = $serverMonitor ?? [];
                $mem = $mon['memory'] ?? [];
                $cpu = $mon['cpu'] ?? [];
                $disk = $mon['disk'] ?? [];
                $topCpu = $mon['top_cpu'] ?? [];
                $topMem = $mon['top_mem'] ?? [];
            @endphp
            <h2>Monitoreo del servidor</h2>
            <p class="hint-text">Capturado: {{ $mon['captured_at'] ?? now()->toDateTimeString() }} · {{ $mon['note'] ?? '' }}</p>
            <form method="get" action="{{ route('admin.panel', ['section' => 'monitoreo']) }}" style="margin-bottom:12px;">
                <button type="submit" class="btn-secondary label-with-icon">@include('web.partials.form-icon', ['name' => 'arrow-path']) Refrescar</button>
            </form>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-bottom:14px;">
                <div class="aspecto-card" style="margin:0;">
                    <div class="aspecto-card-title">Memoria RAM</div>
                    <p class="hint-text">Uso: <strong>{{ $mem['used_pct'] !== null ? $mem['used_pct'].'%' : 'N/D' }}</strong></p>
                    <p class="hint-text">Usada: {{ number_format((float) ($mem['used_mb'] ?? 0), 0, ',', '.') }} MB</p>
                    <p class="hint-text">Libre: {{ number_format((float) ($mem['free_mb'] ?? 0), 0, ',', '.') }} MB</p>
                    <p class="hint-text">Total: {{ number_format((float) ($mem['total_mb'] ?? 0), 0, ',', '.') }} MB</p>
                </div>
                <div class="aspecto-card" style="margin:0;">
                    <div class="aspecto-card-title">CPU</div>
                    <p class="hint-text">Uso estimado: <strong>{{ $cpu['usage_pct'] !== null ? $cpu['usage_pct'].'%' : 'N/D' }}</strong></p>
                    <p class="hint-text">Load 1m: {{ $cpu['load_1m'] !== null ? $cpu['load_1m'] : 'N/D' }}</p>
                    <p class="hint-text">Load 5m: {{ $cpu['load_5m'] !== null ? $cpu['load_5m'] : 'N/D' }}</p>
                    <p class="hint-text">Cores: {{ (int) ($cpu['cores'] ?? 0) }}</p>
                </div>
                <div class="aspecto-card" style="margin:0;">
                    <div class="aspecto-card-title">Disco</div>
                    <p class="hint-text">Ruta: <code>{{ $disk['path'] ?? base_path() }}</code></p>
                    <p class="hint-text">Uso: <strong>{{ $disk['used_pct'] !== null ? $disk['used_pct'].'%' : 'N/D' }}</strong></p>
                    <p class="hint-text">Usado: {{ $disk['used_gb'] !== null ? number_format((float) $disk['used_gb'], 2, ',', '.') : 'N/D' }} GB</p>
                    <p class="hint-text">Libre: {{ $disk['free_gb'] !== null ? number_format((float) $disk['free_gb'], 2, ',', '.') : 'N/D' }} GB</p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px;">
                <div class="aspecto-card" style="margin:0;">
                    <div class="aspecto-card-title">Procesos con más CPU</div>
                    <table style="width:100%;font-size:12px;">
                        <thead><tr><th align="left">PID</th><th align="left">Proceso</th><th align="right">%CPU</th><th align="right">%MEM</th></tr></thead>
                        <tbody>
                        @forelse($topCpu as $p)
                            <tr style="border-top:1px solid #e2e8f0;">
                                <td>{{ $p['pid'] }}</td>
                                <td>{{ $p['name'] }}</td>
                                <td align="right">{{ number_format((float) $p['cpu'], 1, ',', '.') }}</td>
                                <td align="right">{{ number_format((float) $p['mem'], 1, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="hint-text">No disponible.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="aspecto-card" style="margin:0;">
                    <div class="aspecto-card-title">Procesos con más memoria</div>
                    <table style="width:100%;font-size:12px;">
                        <thead><tr><th align="left">PID</th><th align="left">Proceso</th><th align="right">%CPU</th><th align="right">%MEM</th></tr></thead>
                        <tbody>
                        @forelse($topMem as $p)
                            <tr style="border-top:1px solid #e2e8f0;">
                                <td>{{ $p['pid'] }}</td>
                                <td>{{ $p['name'] }}</td>
                                <td align="right">{{ number_format((float) $p['cpu'], 1, ',', '.') }}</td>
                                <td align="right">{{ number_format((float) $p['mem'], 1, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="hint-text">No disponible.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($section === 'verificacion')
            <h2>Archivos .txt en la raíz</h2>
            <ul>@foreach($verificationFiles as $f)<li>{{ $f }}</li>@endforeach</ul>
            @php
                $sitemapUrl = url('/sitemap.xml');
            @endphp
            <div style="margin:12px 0;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">
                <label class="field-label label-with-icon" for="admin_sitemap_url">@include('web.partials.form-icon', ['name' => 'link']) Enlace del sitemap (Google Search Console)</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input id="admin_sitemap_url" type="text" value="{{ $sitemapUrl }}" readonly style="min-width:280px;flex:1;">
                    <button type="button" class="btn-secondary label-with-icon" id="admin_copy_sitemap_btn">@include('web.partials.form-icon', ['name' => 'link']) Copiar enlace</button>
                    <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer" class="btn-primary label-with-icon">@include('web.partials.form-icon', ['name' => 'link']) Ir a Google</a>
                </div>
            </div>
            <form method="post" action="{{ route('admin.verification') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_section" value="{{ $section }}">
                <label class="field-label label-with-icon" for="admin_verification_file">@include('web.partials.form-icon', ['name' => 'document-text']) Archivo de verificación (.txt)</label>
                <input id="admin_verification_file" type="file" name="file" accept=".txt,text/plain" required>
                <button type="submit" class="btn-primary label-with-icon">@include('web.partials.form-icon', ['name' => 'arrow-down-tray']) Subir .txt</button>
            </form>
            <form method="post" action="{{ route('admin.sitemap') }}" style="margin-top:16px;">
                @csrf
                <input type="hidden" name="_section" value="{{ $section }}">
                <button type="submit" class="btn-secondary label-with-icon">@include('web.partials.form-icon', ['name' => 'link']) Generar sitemap.xml en /public</button>
            </form>
            <script>
                (function () {
                    var btn = document.getElementById('admin_copy_sitemap_btn');
                    var input = document.getElementById('admin_sitemap_url');
                    if (!btn || !input) return;
                    btn.addEventListener('click', function () {
                        var text = input.value || '';
                        if (!text) return;
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(text).then(function () {
                                alert('Enlace del sitemap copiado.');
                            }).catch(function () {
                                input.select();
                                document.execCommand('copy');
                            });
                            return;
                        }
                        input.select();
                        document.execCommand('copy');
                    });
                })();
            </script>
        @endif

        @if($section === 'usuarios' && $users)
            <h2>Usuarios</h2>
            <table class="admin-users-table" style="width:100%;">
                <thead><tr><th align="left">ID</th><th align="left">Nombre</th><th align="left">Email</th><th align="left">Rol</th><th align="left">Acciones</th></tr></thead>
                <tbody>
                @foreach($users as $u)
                    <tr style="border-top:1px solid #e2e8f0;">
                        <td>{{ $u->id }}</td>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ optional($u->role)->name }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.user_role', $u) }}" style="display:inline-block;margin-bottom:6px;">
                                @csrf
                                <input type="hidden" name="_section" value="{{ $section }}">
                                <label class="sr-only" for="admin_role_{{ $u->id }}">Rol de {{ $u->name }}</label>
                                <select id="admin_role_{{ $u->id }}" name="role_id" aria-label="Rol de {{ $u->name }}">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ $u->role_id === $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-secondary label-with-icon">@include('web.partials.form-icon', ['name' => 'user-group', 'size' => 16]) Rol</button>
                            </form>
                            <form method="post" action="{{ route('admin.user_ban', $u) }}" style="display:block;">
                                @csrf
                                <input type="hidden" name="_section" value="{{ $section }}">
                                <label class="field-label label-with-icon" for="admin_ban_{{ $u->id }}" style="margin-top:6px;">@include('web.partials.form-icon', ['name' => 'no-symbol']) Motivo del ban</label>
                                <input id="admin_ban_{{ $u->id }}" type="text" name="reason" placeholder="Motivo ban" required class="admin-ban-reason">
                                <button type="submit" class="btn-secondary label-with-icon">@include('web.partials.form-icon', ['name' => 'no-symbol', 'size' => 16]) Banear</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="margin-top:12px;">{{ $users->links() }}</div>
        @endif

        @if($section === 'videos' && $adminVideos)
            <h2>Gestión de videos</h2>
            <p class="hint-text" style="max-width:48rem;">Busca por título, slug, descripción o ID. Filtra por moderación y estado de publicación. Puedes <strong>activar</strong> (quita bloqueo en catálogo), <strong>bloquear</strong> (requiere motivo, igual que la API) o <strong>editar</strong> metadatos y URLs.</p>

            @if($errors->any())
                <div role="alert" style="margin:12px 0;padding:10px 12px;border-radius:10px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;font-size:14px;">
                    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                </div>
            @endif

            <form method="get" action="{{ route('admin.panel', ['section' => 'videos']) }}" class="admin-video-filters" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin:16px 0;">
                <div>
                    <label class="field-label" for="vf_q">Buscar</label>
                    <input id="vf_q" type="search" name="q" value="{{ $videoFilters['q'] ?? '' }}" placeholder="Título, slug, texto… o ID" maxlength="200" style="min-width:220px;">
                </div>
                <div>
                    <label class="field-label" for="vf_mod">Moderación</label>
                    <select id="vf_mod" name="moderation">
                        <option value="" {{ ($videoFilters['mod'] ?? '') === '' ? 'selected' : '' }}>Todas</option>
                        <option value="active" {{ ($videoFilters['mod'] ?? '') === 'active' ? 'selected' : '' }}>Activos</option>
                        <option value="blocked" {{ ($videoFilters['mod'] ?? '') === 'blocked' ? 'selected' : '' }}>Bloqueados</option>
                        <option value="review" {{ ($videoFilters['mod'] ?? '') === 'review' ? 'selected' : '' }}>En revisión</option>
                    </select>
                </div>
                <div>
                    <label class="field-label" for="vf_pub">Publicado</label>
                    <select id="vf_pub" name="published">
                        <option value="" {{ ($videoFilters['pub'] ?? '') === '' ? 'selected' : '' }}>Todos</option>
                        <option value="1" {{ ($videoFilters['pub'] ?? '') === '1' ? 'selected' : '' }}>Sí</option>
                        <option value="0" {{ ($videoFilters['pub'] ?? '') === '0' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Filtrar</button>
                @if(($videoFilters['q'] ?? '') !== '' || ($videoFilters['mod'] ?? '') !== '' || ($videoFilters['pub'] ?? '') !== '')
                    <a href="{{ route('admin.panel', ['section' => 'videos']) }}" class="btn-secondary">Limpiar</a>
                @endif
            </form>

            <section class="aspecto-card" style="margin:18px 0;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#fafbff;">
                <div class="aspecto-card-title" style="margin-bottom:8px;">Miniatura y vista previa (ffmpeg)</div>
                <p class="hint-text" style="margin:0 0 10px;">Para videos <strong>sin poster</strong> y/o <strong>sin clip de hover</strong> en el feed: genera una captura JPG y un MP4 corto (silenciado) desde el archivo de vídeo local o una URL remota. Requiere <code>ffmpeg</code> en el servidor y codec <code>libx264</code> para el clip. Los archivos se guardan en <code>storage/app/public/generated-previews/</code>.</p>
                <form method="post" action="{{ route('admin.video_previews_generate') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                    @csrf
                    <input type="hidden" name="_section" value="videos">
                    <div>
                        <label class="field-label" for="ffmpeg_batch_limit">Cantidad máxima por ejecución</label>
                        <select id="ffmpeg_batch_limit" name="limit">
                            @foreach([5 => '5', 10 => '10', 15 => '15', 25 => '25', 40 => '40'] as $val => $lab)
                                <option value="{{ $val }}" {{ (int) old('limit', 15) === (int) $val ? 'selected' : '' }}>{{ $lab }} videos</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Generar con ffmpeg</button>
                </form>
                <p class="hint-text" style="margin:10px 0 0;">CLI: solo portadas JPG — <code style="font-size:11px;">php artisan videos:generate-posters --limit=40</code> · poster + clip hover — <code style="font-size:11px;">php artisan videos:generate-previews --limit=30</code></p>
            </section>

            <section class="aspecto-card" style="margin:18px 0;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;">
                <div class="aspecto-card-title" style="margin-bottom:8px;">Portadas en cola (RabbitMQ) + progreso</div>
                <p class="hint-text" style="margin:0 0 12px;">Encola <strong>un job por vídeo</strong> para generar portada JPG si falta. Al terminar cada uno se marca <strong>OK</strong> y se actualiza esta barra de progreso.</p>
                <form id="admin_poster_enqueue_form" style="display:flex;flex-direction:column;gap:12px;max-width:42rem;">
                    @csrf
                    <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;">
                        <div>
                            <label class="field-label" for="poster_queue_limit">Máximo de vídeos por ejecución</label>
                            <select id="poster_queue_limit" name="limit">
                                @foreach([20 => '20', 40 => '40', 60 => '60', 100 => '100', 150 => '150', 200 => '200', 300 => '300'] as $val => $lab)
                                    <option value="{{ $val }}" {{ (int) old('poster_limit', 120) === (int) $val ? 'selected' : '' }}>{{ $lab }}</option>
                                @endforeach
                            </select>
                            <label class="checkbox-with-icon" style="margin-top:8px;display:block;">
                                <span class="checkbox-with-icon-body checkbox-row" style="margin:0;">
                                    <input type="checkbox" name="scan_all_posts" value="1" id="scan_all_posts_check">
                                    Revisar TODOS los posts (sin límite) y encolar solo los que no tengan portada
                                </span>
                            </label>
                        </div>
                        <div>
                            <span class="field-label" style="display:block;margin-bottom:4px;">Ámbito</span>
                            <label class="checkbox-with-icon" style="margin:0;display:block;"><span class="checkbox-with-icon-body checkbox-row" style="margin:0;"><input type="radio" name="scope" value="missing" checked> Solo sin portada válida</span></label>
                            <label class="checkbox-with-icon" style="margin:0;display:block;margin-top:6px;"><span class="checkbox-with-icon-body checkbox-row" style="margin:0;"><input type="radio" name="scope" value="all"> Todas (sobrescribe)</span></label>
                        </div>
                        <div>
                            <span class="field-label" style="display:block;margin-bottom:4px;">Instante de captura</span>
                            <label class="checkbox-with-icon" style="margin:0;display:block;"><span class="checkbox-with-icon-body checkbox-row" style="margin:0;"><input type="radio" name="duration_aware" value="1" checked> Según duración + ID</span></label>
                            <label class="checkbox-with-icon" style="margin:0;display:block;margin-top:6px;"><span class="checkbox-with-icon-body checkbox-row" style="margin:0;"><input type="radio" name="duration_aware" value="0"> Fijo (<code>FFMPEG_POSTER_SEEK</code>)</span></label>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary label-with-icon" id="admin_poster_enqueue_btn">@include('web.partials.form-icon', ['name' => 'photo', 'size' => 16]) Encolar portadas faltantes</button>
                </form>
                <div id="admin_poster_progress_wrap" style="display:none;margin-top:12px;">
                    <div style="height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden;">
                        <div id="admin_poster_progress_bar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--menu-color,#d83a7c),#16a34a);transition:width .3s;"></div>
                    </div>
                    <p id="admin_poster_progress_text" class="hint-text" style="margin-top:6px;">Procesando… 0%</p>
                    <p id="admin_poster_progress_counts" class="hint-text" style="margin-top:4px;"></p>
                    <div id="admin_poster_recent_ok" class="hint-text" style="margin-top:6px;max-height:130px;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:8px;"></div>
                </div>
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <p class="hint-text" style="margin:0 0 8px;">Para procesar jobs de <strong>miniatura / vista previa</strong> necesitás el worker de la cola <code>media</code>. Podés encenderlo aquí o en <a href="{{ route('admin.panel', ['section' => 'integraciones']) }}" class="text-link">Colas e integraciones</a>.</p>
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                        <button type="button" class="btn-secondary label-with-icon" id="admin_worker_media_start_btn_videos">@include('web.partials.form-icon', ['name' => 'sparkles', 'size' => 14]) Encender worker media</button>
                        <span id="admin_worker_media_badge_videos" class="text-xs" style="display:inline-block;padding:2px 10px;border-radius:9999px;font-weight:700;background:#e2e8f0;color:#475569;">Estado: …</span>
                        <span id="admin_worker_media_pid_videos" class="text-xs" style="color:#64748b;"></span>
                    </div>
                    <p id="admin_worker_media_err_videos" class="hint-text hidden" style="margin-top:8px;color:#b91c1c;" role="alert"></p>
                    <p class="hint-text" style="margin:10px 0 0;font-size:11px;">CLI: <code style="font-size:11px;">php artisan queue:work rabbitmq --queue=media,default --tries=3 --timeout=1200</code></p>
                </div>
            </section>
            <script>
                (function () {
                    var form = document.getElementById('admin_poster_enqueue_form');
                    if (!form) return;
                    var btn = document.getElementById('admin_poster_enqueue_btn');
                    var wrap = document.getElementById('admin_poster_progress_wrap');
                    var bar = document.getElementById('admin_poster_progress_bar');
                    var text = document.getElementById('admin_poster_progress_text');
                    var counts = document.getElementById('admin_poster_progress_counts');
                    var recent = document.getElementById('admin_poster_recent_ok');
                    var csrf = document.querySelector('meta[name="csrf-token"]');
                    var postUrl = @json(route('admin.video_posters_enqueue', [], false));
                    var statusUrl = @json(route('admin.video_posters_status', [], false));
                    var timer = null;
                    var batchId = '';

                    function esc(s) {
                        var d = document.createElement('div');
                        d.textContent = s == null ? '' : String(s);
                        return d.innerHTML;
                    }

                    function stopPolling() {
                        if (timer) {
                            clearInterval(timer);
                            timer = null;
                        }
                    }

                    function setProgress(n) {
                        var pct = Math.max(0, Math.min(100, parseInt(n, 10) || 0));
                        if (bar) bar.style.width = pct + '%';
                        if (text) text.textContent = 'Procesando portadas… ' + pct + '%';
                    }

                    function poll() {
                        if (!batchId) return;
                        fetch(statusUrl + '?batch_id=' + encodeURIComponent(batchId), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (data) {
                            if (!data || !data.ok) return;
                            setProgress(data.progress || 0);
                            if (data.scanning && text) {
                                text.textContent = 'Escaneando todos los posts para detectar faltantes…';
                            }
                            var c = data.counts || {};
                            if (counts) {
                                counts.textContent = 'Total: ' + (c.total || 0) + ' · Hechos: ' + (c.done || 0) + ' · OK: ' + (c.ok || 0) + ' · Error: ' + (c.failed || 0);
                            }
                            if (recent) {
                                var lines = data.recent || [];
                                recent.innerHTML = lines.length
                                    ? lines.map(function (line) { return '<div>• ' + esc(line) + '</div>'; }).join('')
                                    : '<div>Esperando resultados…</div>';
                            }
                            if (data.done) {
                                setProgress(100);
                                if (text) text.textContent = 'Proceso completado.';
                                if (btn) btn.disabled = false;
                                stopPolling();
                            }
                        })
                        .catch(function () {});
                    }

                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        if (!csrf || !csrf.getAttribute('content')) return;
                        if (btn) btn.disabled = true;
                        if (wrap) wrap.style.display = 'block';
                        setProgress(3);
                        if (recent) recent.innerHTML = '<div>Encolando trabajos…</div>';
                        if (counts) counts.textContent = '';

                        var fd = new FormData(form);
                        fd.append('_section', 'videos');
                        fd.append('_token', csrf.getAttribute('content'));

                        fetch(postUrl, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: fd,
                            credentials: 'same-origin'
                        })
                        .then(function (r) {
                            return r.json().catch(function () { return null; }).then(function (data) {
                                return { okHttp: r.ok, status: r.status, data: data };
                            });
                        })
                        .then(function (data) {
                            var payload = data && data.data ? data.data : null;
                            if (!payload || !payload.ok) {
                                var msg = payload && payload.message ? String(payload.message) : 'No se pudo iniciar la cola de portadas.';
                                if (data && !data.okHttp && data.status) {
                                    msg += ' (HTTP ' + data.status + ')';
                                }
                                throw new Error(msg);
                            }
                            if (!payload.batch_id || !payload.total) {
                                setProgress(100);
                                if (text) text.textContent = (payload.message || 'No hay vídeos pendientes.');
                                if (btn) btn.disabled = false;
                                return;
                            }
                            batchId = String(payload.batch_id);
                            setProgress(7);
                            if (text) text.textContent = 'Cola iniciada. Procesando…';
                            stopPolling();
                            timer = setInterval(poll, 1200);
                            poll();
                        })
                        .catch(function (err) {
                            if (text) text.textContent = err && err.message ? err.message : 'No se pudo iniciar la cola de portadas.';
                            if (btn) btn.disabled = false;
                            stopPolling();
                        });
                    });

                    var workerStatusUrlV = @json(route('admin.worker_media_status', [], false));
                    var workerStartUrlV = @json(route('admin.worker_media_start', [], false));
                    var workerBtnV = document.getElementById('admin_worker_media_start_btn_videos');
                    var workerBadgeV = document.getElementById('admin_worker_media_badge_videos');
                    var workerPidV = document.getElementById('admin_worker_media_pid_videos');
                    var workerErrV = document.getElementById('admin_worker_media_err_videos');

                    function renderWorkerV(data) {
                        if (!workerBadgeV) return;
                        var running = !!(data && data.running);
                        workerBadgeV.textContent = running ? 'Worker media: activo' : 'Worker media: apagado';
                        workerBadgeV.style.background = running ? '#dcfce7' : '#fee2e2';
                        workerBadgeV.style.color = running ? '#166534' : '#991b1b';
                        if (workerPidV) {
                            workerPidV.textContent = data && data.pid ? ('PID: ' + data.pid) : '';
                        }
                        if (workerBtnV) {
                            workerBtnV.disabled = running;
                        }
                    }

                    function tickWorkerV() {
                        if (!workerBadgeV) return;
                        fetch(workerStatusUrlV, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                            .then(function (r) { return r.ok ? r.json() : null; })
                            .then(function (data) { if (data) renderWorkerV(data); })
                            .catch(function () {});
                    }

                    if (workerBtnV) {
                        workerBtnV.addEventListener('click', function () {
                            if (workerErrV) {
                                workerErrV.textContent = '';
                                workerErrV.classList.add('hidden');
                            }
                            workerBtnV.disabled = true;
                            fetch(workerStartUrlV, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrf ? (csrf.getAttribute('content') || '') : ''
                                },
                                credentials: 'same-origin'
                            })
                            .then(function (r) { return r.json().catch(function () { return null; }); })
                            .then(function (data) {
                                if (!data || !data.ok) {
                                    if (workerErrV) {
                                        workerErrV.textContent = (data && data.message) ? data.message : 'No se pudo encender el worker media.';
                                        workerErrV.classList.remove('hidden');
                                    }
                                    workerBtnV.disabled = false;
                                    return;
                                }
                                renderWorkerV(data);
                            })
                            .catch(function () {
                                if (workerErrV) {
                                    workerErrV.textContent = 'No se pudo encender el worker media.';
                                    workerErrV.classList.remove('hidden');
                                }
                                workerBtnV.disabled = false;
                            });
                        });
                    }
                    tickWorkerV();
                    setInterval(tickWorkerV, 5000);
                })();
            </script>

            <table class="admin-users-table admin-videos-table" style="width:100%;table-layout:fixed;">
                <thead>
                    <tr>
                        <th align="left" style="width:52px;">ID</th>
                        <th align="left">Título</th>
                        <th align="left" style="width:120px;">Estado</th>
                        <th align="right" style="width:72px;">Vistas</th>
                        <th align="left" style="width:38%;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($adminVideos as $v)
                    @php
                        $editFocus = (string) old('_edit_video_id', '') === (string) $v->id;
                        $modSel = $editFocus ? old('moderation_status', $v->moderation_status) : $v->moderation_status;
                        $pubChecked = $editFocus ? (bool) old('is_published', false) : $v->is_published;
                        $pubAtDefault = $v->published_at ? $v->published_at->format('Y-m-d\TH:i') : '';
                        $pubAt = $editFocus ? old('published_at', $pubAtDefault) : $pubAtDefault;
                    @endphp
                    <tr style="border-top:1px solid #e2e8f0;vertical-align:top;">
                        <td>{{ $v->id }}</td>
                        <td style="word-break:break-word;">
                            <strong>{{ \Illuminate\Support\Str::limit($v->title, 80) }}</strong>
                            <div class="hint-text" style="margin-top:4px;">
                                {{ optional($v->channel)->display_name ?? 'Canal' }}
                                · <a href="{{ route('posts.show', ['video' => $v->id, 'slug' => $v->playSlug()]) }}" target="_blank" rel="noopener noreferrer" class="text-link">Ver público</a>
                            </div>
                        </td>
                        <td>
                            @if($v->moderation_status === 'blocked')
                                <span style="color:#b91c1c;font-weight:600;">Bloqueado</span>
                            @elseif($v->moderation_status === 'review')
                                <span style="color:#b45309;">Revisión</span>
                            @else
                                <span style="color:#15803d;">Activo</span>
                            @endif
                            <div class="hint-text">{{ $v->is_published ? 'Publicado' : 'No publicado' }}</div>
                        </td>
                        <td align="right">{{ number_format((int) $v->views_count, 0, ',', '.') }}</td>
                        <td>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
                                @if($v->moderation_status !== 'active')
                                    <form method="post" action="{{ route('admin.video_activate', $v) }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="_section" value="videos">
                                        @include('web.admin.partials.video-retain-filters')
                                        <button type="submit" class="btn-secondary" style="font-size:12px;padding:6px 10px;">Activar</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('admin.video_hls_generate', $v) }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="_section" value="videos">
                                    @include('web.admin.partials.video-retain-filters')
                                    <button type="submit" class="btn-secondary" style="font-size:12px;padding:6px 10px;">Generar HLS</button>
                                </form>
                                <form method="post" action="{{ route('admin.video_block', $v) }}" style="display:inline-flex;flex-wrap:wrap;gap:6px;align-items:center;">
                                    @csrf
                                    <input type="hidden" name="_section" value="videos">
                                    @include('web.admin.partials.video-retain-filters')
                                    <input type="text" name="reason" placeholder="Motivo bloqueo *" value="{{ old('reason') }}" required maxlength="255" style="max-width:140px;font-size:12px;">
                                    <input type="text" name="notes" placeholder="Notas" value="{{ old('notes') }}" maxlength="500" style="max-width:100px;font-size:12px;">
                                    <button type="submit" class="btn-secondary" style="font-size:12px;padding:6px 10px;">Bloquear</button>
                                </form>
                            </div>
                            <details style="margin-top:10px;" class="admin-video-edit-details" {{ $errors->any() && $editFocus ? 'open' : '' }}>
                                <summary class="text-link" style="cursor:pointer;font-weight:600;">Editar datos…</summary>
                                <form method="post" action="{{ route('admin.video_update', $v) }}" enctype="multipart/form-data" style="margin-top:10px;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#fafafa;">
                                    @csrf
                                    <input type="hidden" name="_section" value="videos">
                                    <input type="hidden" name="_edit_video_id" value="{{ $v->id }}">
                                    @include('web.admin.partials.video-retain-filters')
                                    <label class="field-label">Título</label>
                                    <input type="text" name="title" value="{{ $editFocus ? old('title', $v->title) : $v->title }}" maxlength="180" required style="width:100%;max-width:100%;box-sizing:border-box;">
                                    <label class="field-label" style="margin-top:8px;">Slug (URL)</label>
                                    <input type="text" name="slug" value="{{ $editFocus ? old('slug', $v->slug) : $v->slug }}" maxlength="220" required style="width:100%;max-width:100%;box-sizing:border-box;">
                                    <label class="field-label" style="margin-top:8px;">Descripción</label>
                                    <textarea name="description" rows="3" maxlength="65535" style="width:100%;max-width:100%;box-sizing:border-box;">{{ $editFocus ? old('description', $v->description) : $v->description }}</textarea>
                                    <label class="field-label" style="margin-top:8px;">URL del video</label>
                                    <input type="text" name="video_url" value="{{ $editFocus ? old('video_url', $v->video_url) : $v->video_url }}" maxlength="255" required style="width:100%;max-width:100%;box-sizing:border-box;">
                                    <label class="field-label" style="margin-top:8px;">URL vista previa</label>
                                    <input type="text" name="preview_url" value="{{ $editFocus ? old('preview_url', $v->preview_url ?? '') : ($v->preview_url ?? '') }}" maxlength="255" style="width:100%;max-width:100%;box-sizing:border-box;">
                                    <label class="field-label" style="margin-top:8px;">URL miniatura</label>
                                    <input type="text" name="thumbnail_url" value="{{ $editFocus ? old('thumbnail_url', $v->thumbnail_url ?? '') : ($v->thumbnail_url ?? '') }}" maxlength="255" style="width:100%;max-width:100%;box-sizing:border-box;">
                                    <label class="field-label" style="margin-top:8px;">Adjuntar miniatura (archivo)</label>
                                    <input type="file" name="thumbnail_file" accept="image/*" style="width:100%;max-width:100%;box-sizing:border-box;">
                                    <p class="hint-text" style="margin-top:4px;">Si subes un archivo, reemplaza la URL de miniatura actual.</p>
                                    <label class="field-label" style="margin-top:8px;">Moderación</label>
                                    <select name="moderation_status">
                                        <option value="active" {{ $modSel === 'active' ? 'selected' : '' }}>Activo</option>
                                        <option value="review" {{ $modSel === 'review' ? 'selected' : '' }}>En revisión</option>
                                        <option value="blocked" {{ $modSel === 'blocked' ? 'selected' : '' }}>Bloqueado</option>
                                    </select>
                                    <label class="checkbox-with-icon" style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                                        <input type="checkbox" name="is_published" value="1" {{ $pubChecked ? 'checked' : '' }}>
                                        <span>Publicado en el feed</span>
                                    </label>
                                    <label class="field-label" style="margin-top:8px;">Fecha publicación</label>
                                    <input type="datetime-local" name="published_at" value="{{ $pubAt }}" style="width:100%;max-width:280px;">
                                    <button type="submit" class="btn-primary" style="margin-top:12px;">Guardar cambios</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="margin-top:12px;">{{ $adminVideos->links() }}</div>
        @endif

        @if($section === 'metricas' && $metricsVideos && $metricsSummary)
            <h2>Métricas de vídeos</h2>
            <p class="hint-text" style="max-width:52rem;line-height:1.5;">
                Cada visita a la página de reproducción ejecuta el registro en base de datos: se incrementa <code>videos.views_count</code> y se suma una vista al día en <code>video_daily_views</code> (<code>VideoViewTracker</code>).
                El contador y el histórico diario no se muestran a los visitantes en el single; solo aparecen aquí para administración.
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:14px;margin:16px 0;">
                <div class="login-card" style="margin:0;padding:12px 16px;min-width:140px;">
                    <strong style="display:block;font-size:22px;">{{ number_format($metricsSummary['views_total'], 0, ',', '.') }}</strong>
                    <span class="hint-text">Vistas acumuladas (todas las publicaciones)</span>
                </div>
                <div class="login-card" style="margin:0;padding:12px 16px;min-width:140px;">
                    <strong style="display:block;font-size:22px;">{{ number_format($metricsSummary['videos_total'], 0, ',', '.') }}</strong>
                    <span class="hint-text">Vídeos en catálogo</span>
                </div>
                <div class="login-card" style="margin:0;padding:12px 16px;min-width:140px;">
                    <strong style="display:block;font-size:22px;">
                        @if($metricsSummary['views_today'] !== null)
                            {{ number_format($metricsSummary['views_today'], 0, ',', '.') }}
                        @else
                            —
                        @endif
                    </strong>
                    <span class="hint-text">Reproducciones registradas hoy (tabla diaria)</span>
                </div>
            </div>
            <h3 style="margin-top:20px;font-size:1rem;">Ranking por vistas</h3>
            <table class="admin-users-table" style="width:100%;">
                <thead>
                    <tr>
                        <th align="left">ID</th>
                        <th align="right">Vistas</th>
                        <th align="left">Título</th>
                        <th align="left">Estado</th>
                        <th align="left">Enlace público</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($metricsVideos as $v)
                    <tr style="border-top:1px solid #e2e8f0;">
                        <td>{{ $v->id }}</td>
                        <td align="right">{{ number_format((int) $v->views_count, 0, ',', '.') }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($v->title, 72) }}</td>
                        <td>
                            @if($v->moderation_status === 'blocked')
                                Bloqueado
                            @elseif($v->is_published)
                                Publicado
                            @else
                                Borrador
                            @endif
                        </td>
                        <td><a href="{{ route('posts.show', ['video' => $v->id, 'slug' => $v->playSlug()]) }}" target="_blank" rel="noopener noreferrer" class="text-link">Abrir</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="margin-top:12px;">{{ $metricsVideos->links() }}</div>
        @endif

        @if($section === 'reddit')
            <h2>Reddit e ideas de tendencias</h2>
            <p class="hint-text" style="max-width:48rem;margin-bottom:16px;">Importá un post con vídeo desde Reddit. Más abajo tenés enlaces a otras herramientas públicas para <strong>detectar qué está pegando</strong> antes de curar contenido para la plataforma (siempre respetá derechos de autor y los términos de cada sitio).</p>
            <form method="post" action="{{ route('admin.reddit') }}">
                @csrf
                <input type="hidden" name="_section" value="{{ $section }}">
                <label class="field-label label-with-icon" for="admin_reddit_url">@include('web.partials.form-icon', ['name' => 'link']) URL del post</label>
                <input id="admin_reddit_url" type="url" name="reddit_url" value="{{ old('reddit_url') }}" required>
                <label class="field-label label-with-icon" for="admin_reddit_title">@include('web.partials.form-icon', ['name' => 'pencil-square']) Título</label>
                <input id="admin_reddit_title" type="text" name="title" value="{{ old('title') }}" required maxlength="180">
                <label class="field-label label-with-icon" for="admin_reddit_desc">@include('web.partials.form-icon', ['name' => 'document-text']) Descripción</label>
                <textarea id="admin_reddit_desc" name="description" rows="2">{{ old('description') }}</textarea>
                <label class="field-label label-with-icon" for="admin_reddit_cats">@include('web.partials.form-icon', ['name' => 'squares-2x2']) Categorías</label>
                <select id="admin_reddit_cats" name="category_ids[]" multiple class="create-category-select" size="6">
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-primary label-with-icon" style="margin-top:12px;">@include('web.partials.form-icon', ['name' => 'arrow-down-tray']) Importar</button>
            </form>

            <section class="aspecto-card" style="margin-top:28px;padding:16px 18px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;max-width:52rem;">
                <h3 class="aspecto-card-title" style="margin-bottom:10px;">Plataformas para explorar tendencias</h3>
                <p class="hint-text" style="margin:0 0 14px;line-height:1.5;">Usalas como <strong>brújula editorial</strong>: buscá nichos, compará regiones y volvé acá para importar o publicar contenido con licencia o propio.</p>
                <ul class="hint-text" style="margin:0;padding-left:1.15rem;line-height:1.65;list-style:disc;">
                    <li><a href="https://trends.google.com/trending" class="text-link" target="_blank" rel="noopener noreferrer">Google Trends</a> — búsquedas y temas calientes por país e idioma.</li>
                    <li><a href="https://www.youtube.com/feed/trending" class="text-link" target="_blank" rel="noopener noreferrer">YouTube — Tendencias</a> — qué formato de vídeo arrasa (no reutilices clips sin permiso).</li>
                    <li><a href="https://www.reddit.com/r/popular/" class="text-link" target="_blank" rel="noopener noreferrer">Reddit Popular</a> y subreddits de tu nicho — ideal combinado con el importador de arriba.</li>
                    <li><a href="https://x.com/explore" class="text-link" target="_blank" rel="noopener noreferrer">X (Twitter) Explorar</a> — tendencias en tiempo real (API de pago si querés automatizar).</li>
                    <li><a href="https://ads.tiktok.com/business/creativecenter/" class="text-link" target="_blank" rel="noopener noreferrer">TikTok Creative Center</a> — hashtags, audios y referencias comerciales (orientado a creadores y marcas).</li>
                    <li><a href="https://trends.pinterest.com/" class="text-link" target="_blank" rel="noopener noreferrer">Pinterest Trends</a> — tendencias visuales por mercado.</li>
                    <li><a href="https://explodingtopics.com/" class="text-link" target="_blank" rel="noopener noreferrer">Exploding Topics</a> — temas emergentes (capa gratuita limitada).</li>
                    <li><a href="https://buzzsumo.com/" class="text-link" target="_blank" rel="noopener noreferrer">BuzzSumo</a> — contenido muy compartido en la web (prueba de pago).</li>
                    <li><a href="https://www.pexels.com/" class="text-link" target="_blank" rel="noopener noreferrer">Pexels</a> / <a href="https://pixabay.com/videos/" class="text-link" target="_blank" rel="noopener noreferrer">Pixabay</a> — vídeo e imagen con licencia clara para rellenar categorías sin riesgo legal.</li>
                </ul>
                <p class="hint-text" style="margin:14px 0 0;line-height:1.5;color:#64748b;">Recordatorio: tendencia ≠ derecho a copiar. Preferí acuerdos, material con licencia, embeds o contenido original; YouTube, TikTok y Reddit tienen reglas estrictas sobre descarga y republicación.</p>
            </section>
        @endif
    </div>
</main>
@endsection
