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
                'seo' => 'SEO',
                'aspecto' => 'Aspecto',
                'banners' => 'Banners',
                'integraciones' => 'Colas',
                'verificacion' => 'TXT',
                'usuarios' => 'Usuarios',
                'videos' => 'Videos',
                'reportes' => 'Reportes',
                'reddit' => 'Reddit',
            ];
            if (optional(auth()->user()->role)->name === 'admin') {
                $__adminTabs['metricas'] = 'Métricas';
            }
        @endphp
        @foreach($__adminTabs as $key => $label)
            <a href="{{ route('admin.panel', ['section' => $key]) }}"
               class="admin-tab {{ $section === $key ? 'active' : '' }}">
                {{ $label }}
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
                <label class="checkbox-with-icon">
                    @include('web.partials.form-icon', ['name' => 'arrow-path', 'size' => 16])
                    <span class="checkbox-with-icon-body checkbox-row" style="margin:0;"><input type="checkbox" name="use_router_links" {{ old('use_router_links', $s('use_router_links','1')) === '1' ? 'checked' : '' }}> Enlaces SPA (React Router) en el feed</span>
                </label>
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
                <p class="hint-text" style="margin-top:8px;">URLs actualmente incluidas en sitemap: <strong>{{ number_format((int) ($seoSitemapLinksCount ?? 0), 0, ',', '.') }}</strong></p>
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
                <p class="hint-text" style="margin:10px 0 0;">CLI equivalente: <code style="font-size:11px;">php artisan videos:generate-previews --limit=30</code></p>
            </section>

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
            <h2>Importar desde Reddit</h2>
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
        @endif
    </div>
</main>
@endsection
