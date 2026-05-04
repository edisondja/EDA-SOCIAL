@extends('web.layout')

@section('title', 'Administración')

@section('content')
@php
    $__adminSettings = $settings;
    $s = function ($k, $d = '') use ($__adminSettings) {
        return $__adminSettings[$k] ?? $d;
    };
@endphp
<main class="admin-page-shell" style="max-width:1100px;margin:0 auto;padding:16px;">
    <nav class="admin-menu" aria-label="Secciones" style="margin-bottom:20px;">
        @foreach(['seo'=>'SEO','aspecto'=>'Aspecto','integraciones'=>'Colas','verificacion'=>'TXT','usuarios'=>'Usuarios','reddit'=>'Reddit'] as $key => $label)
            <a href="{{ route('admin.panel', ['section' => $key]) }}"
               class="admin-tab {{ $section === $key ? 'active' : '' }}"
               style="display:inline-flex;align-items:center;padding:8px 12px;margin:4px;border-radius:8px;text-decoration:none;color:inherit;border:1px solid #e2e8f0;">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="login-card admin-panel">
        <p class="hint-text">Resumen: {{ $dashboard['users_total'] }} usuarios · {{ $dashboard['videos_total'] }} videos · {{ $dashboard['videos_blocked'] }} bloqueados · {{ $dashboard['users_banned'] }} cuentas bloqueadas.</p>

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
                <button type="submit" class="btn-primary label-with-icon">@include('web.partials.form-icon', ['name' => 'sparkles']) Guardar SEO</button>
            </form>
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
                    <p class="aspecto-card-hint">Imagen cuadrada o horizontal (PNG o JPG). Si no subes archivo, se usa el logo por defecto «EDA-SOCIAL» (230×50 px).</p>
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
