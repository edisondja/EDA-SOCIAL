@php
    $cfg = $bannerSlotConfig ?? [];
@endphp

@if($errors->any())
    <div role="alert" style="margin:0 0 14px;padding:10px 12px;border-radius:10px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;font-size:14px;">
        @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
    </div>
@endif

<h2>Banners en página del vídeo</h2>
<p class="hint-text mb-6" style="max-width:52rem;line-height:1.55;">
    <strong>Plantillas</strong>: bloques HTML reutilizables (código que te da AdSense, otra red, un CTA propio…). Podés crear varias, activarlas y asignarlas a cada zona.<br>
    <strong>Zonas</strong>: lugares fijos en la página del vídeo donde se inserta ese HTML. Así cambiás de proveedor o formato sin tocar código: nueva plantilla → la enlazás a la zona que quieras.
</p>

{{-- Vista esquemática: encaja con post.blade (banner top → player → banner bottom → resto; popup aparte) --}}
<div class="admin-banner-layout-preview mb-8 rounded-2xl border border-slate-200 bg-gradient-to-b from-slate-50 via-white to-slate-50/80 p-5 shadow-soft sm:p-6">
    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Ejemplo visual · ¿Dónde va cada script o HTML?</h3>
    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600">
        No es la página real: sirve para ver en qué orden se colocan el <strong>banner superior</strong>, el <strong>reproductor</strong>, el <strong>banner inferior</strong> y la <strong>ventana emergente</strong>. El contenido lo definís vos en cada plantilla o en HTML personalizado por zona.
    </p>

    <div class="mx-auto mt-5 max-w-md space-y-3">
        {{-- ① Superior --}}
        <div class="rounded-xl border-2 border-dashed border-amber-500/70 bg-amber-50 px-3 py-3 text-center shadow-sm">
            <div class="flex items-center justify-center gap-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[11px] font-black text-white">1</span>
                <span class="text-[11px] font-bold uppercase tracking-wide text-amber-950">Banner superior</span>
            </div>
            <p class="mt-1.5 text-[11px] leading-snug text-amber-950/85">
                Coincide con el campo <strong>Banner superior</strong> más abajo: ahí pegás una plantilla guardada o HTML/script para ads que debe ir <em>encima</em> del video.
            </p>
        </div>

        {{-- Reproductor --}}
        <div class="overflow-hidden rounded-xl border border-slate-300 bg-slate-900 shadow-md ring-2 ring-slate-200">
            <div class="relative flex aspect-video items-center justify-center bg-gradient-to-br from-slate-800 via-slate-900 to-black">
                <div class="absolute inset-x-0 top-2 flex justify-center">
                    <span class="rounded-full bg-black/40 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-white/90 backdrop-blur-sm">Página del video (single)</span>
                </div>
                <div class="text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/15 text-white shadow-lg ring-2 ring-white/20" aria-hidden="true">
                        <svg class="ml-1 h-7 w-7" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7L8 5z"/></svg>
                    </span>
                    <p class="mt-2 text-xs font-semibold text-slate-300">Reproductor</p>
                    <p class="mt-0.5 text-[10px] text-slate-500">El video se reproduce aquí</p>
                </div>
            </div>
        </div>

        {{-- ② Inferior --}}
        <div class="rounded-xl border-2 border-dashed border-amber-500/70 bg-amber-50 px-3 py-3 text-center shadow-sm">
            <div class="flex items-center justify-center gap-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[11px] font-black text-white">2</span>
                <span class="text-[11px] font-bold uppercase tracking-wide text-amber-950">Banner inferior</span>
            </div>
            <p class="mt-1.5 text-[11px] leading-snug text-amber-950/85">
                Mismo concepto que el superior, pero <em>debajo</em> del reproductor (antes de descripción, compartir, etc., según el diseño del sitio).
            </p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-100/80 px-3 py-2 text-center">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Resto de la página</p>
            <p class="text-[11px] text-slate-600">Título, compartir, ranking, descripción, comentarios…</p>
        </div>

        {{-- ③ Popup --}}
        <div class="rounded-xl border-2 border-dashed border-violet-400/80 bg-violet-50 px-3 py-3 text-center shadow-sm">
            <div class="flex items-center justify-center gap-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-600 text-[11px] font-black text-white">3</span>
                <span class="text-[11px] font-bold uppercase tracking-wide text-violet-950">Ventana emergente</span>
            </div>
            <p class="mt-1.5 text-[11px] leading-snug text-violet-950/90">
                No es una “franja”: es un diálogo que aparece encima tras unos segundos. Útil para otro tipo de mensaje o HTML si no querés ocupar espacio al lado del video.
            </p>
        </div>
    </div>

    <p class="mt-5 rounded-lg border border-slate-100 bg-white px-3 py-2 text-xs leading-relaxed text-slate-600">
        <strong class="text-slate-800">Cambiar de red de anuncios:</strong> creá una plantilla nueva con el snippet que te dé el nuevo proveedor, activala y seleccionála en la zona correspondiente (o pegá el HTML en modo integrado). Las plantillas viejas siguen guardadas por si querés volver atrás.
    </p>
</div>

<section class="aspecto-module" style="margin-top:18px;">
    <header class="aspecto-module-header">
        <h3 class="aspecto-module-subtitle">Plantillas guardadas</h3>
        <p class="aspecto-module-lead">
            Cada plantilla es un nombre + bloque HTML (scripts de anuncios, iframes, creatividades). Solo las <strong>activas</strong> aparecen en el desplegable de las zonas ① y ② del formulario siguiente. Podés tener una plantilla “AdSense leaderboard”, otra “Media.net”, etc.
        </p>
    </header>

    @forelse($bannerTemplates as $tpl)
        <div class="aspecto-card" style="margin-bottom:12px;">
            <form method="post" action="{{ route('admin.banner_template_update', $tpl['id']) }}" class="aspecto-form">
                @csrf
                <input type="hidden" name="_section" value="banners">
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;align-items:center;margin-bottom:8px;">
                    <strong style="font-family:monospace;font-size:12px;color:#64748b;">{{ $tpl['id'] }}</strong>
                    <label class="checkbox-with-icon" style="margin:0;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="tpl_enabled" value="1" {{ !empty($tpl['enabled']) ? 'checked' : '' }}>
                        <span>Activa</span>
                    </label>
                </div>
                <label class="field-label">Nombre</label>
                <input type="text" name="name" value="{{ old('name', $tpl['name']) }}" maxlength="120" required style="width:100%;max-width:520px;">
                <label class="field-label" style="margin-top:8px;">HTML</label>
                <textarea name="html" rows="5" maxlength="12000" required style="width:100%;font-family:monospace;font-size:12px;">{{ old('html', $tpl['html']) }}</textarea>
                <p class="hint-text">Etiquetas permitidas: enlaces, negritas, listas, imágenes, párrafos, etc. (sanitizado al guardar).</p>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;">
                    <button type="submit" class="btn-primary">Guardar plantilla</button>
                </div>
            </form>
            <form method="post" action="{{ route('admin.banner_template_delete', $tpl['id']) }}" style="margin-top:10px;" onsubmit="return confirm('¿Eliminar esta plantilla?');">
                @csrf
                <input type="hidden" name="_section" value="banners">
                <button type="submit" class="btn-secondary">Eliminar plantilla</button>
            </form>
        </div>
    @empty
        <p class="hint-text">Todavía no hay plantillas. Creá una abajo.</p>
    @endforelse

    <div class="aspecto-card" style="margin-top:14px;">
        <div class="aspecto-card-title">Nueva plantilla</div>
        <form method="post" action="{{ route('admin.banner_template_store') }}" class="aspecto-form">
            @csrf
            <input type="hidden" name="_section" value="banners">
            <label class="field-label" for="bn_new_name">Nombre</label>
            <input id="bn_new_name" type="text" name="name" value="{{ old('name') }}" maxlength="120" required style="width:100%;max-width:520px;">
            <label class="field-label" style="margin-top:8px;" for="bn_new_html">HTML</label>
            <textarea id="bn_new_html" name="html" rows="5" maxlength="12000" required style="width:100%;font-family:monospace;font-size:12px;">{{ old('html') }}</textarea>
            <button type="submit" class="btn-primary" style="margin-top:10px;">Crear plantilla</button>
        </form>
    </div>
</section>

<section class="aspecto-module" style="margin-top:28px;">
    <header class="aspecto-module-header">
        <h3 class="aspecto-module-subtitle">Zonas del single y ventana emergente</h3>
        <p class="aspecto-module-lead">
            Configurá qué se muestra en la página de <strong>un solo vídeo</strong> (no en el listado). Los apartados coinciden con el gráfico: <strong>① superior</strong>, <strong>② inferior</strong> y <strong>③ popup</strong>. Elegí plantilla guardada o HTML integrado por zona.
        </p>
    </header>

    <form method="post" action="{{ route('admin.banner_slots') }}" class="aspecto-form">
        @csrf
        <input type="hidden" name="_section" value="banners">

        <fieldset class="aspecto-card" style="margin-bottom:14px;">
            <legend class="aspecto-card-title">Banner superior</legend>
            <label class="checkbox-with-icon" style="margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="video_ad_banner_top_enabled" value="1" {{ old('video_ad_banner_top_enabled', $cfg['top_enabled'] ?? false) ? 'checked' : '' }}>
                <span>Mostrar banner encima del vídeo</span>
            </label>
            <label class="field-label">Origen</label>
            <select name="video_ad_banner_top_mode">
                <option value="legacy" {{ old('video_ad_banner_top_mode', $cfg['top_mode'] ?? 'legacy') === 'legacy' ? 'selected' : '' }}>Plantillas integradas / HTML por zona</option>
                <option value="library" {{ old('video_ad_banner_top_mode', $cfg['top_mode'] ?? 'legacy') === 'library' ? 'selected' : '' }}>Plantilla guardada</option>
            </select>
            <label class="field-label" style="margin-top:10px;">Plantilla guardada</label>
            <select name="video_ad_banner_top_library_id">
                <option value="">— Elegí una —</option>
                @foreach($bannerTemplates as $bt)
                    @if(!empty($bt['enabled']))
                        <option value="{{ $bt['id'] }}" {{ old('video_ad_banner_top_library_id', $cfg['top_library_id'] ?? '') === $bt['id'] ? 'selected' : '' }}>{{ $bt['name'] }}</option>
                    @endif
                @endforeach
            </select>
            @error('video_ad_banner_top_library_id')<p class="hint-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
            <label class="field-label" style="margin-top:10px;">Integrada</label>
            <select name="video_ad_banner_top_template">
                @foreach(['none'=>'Ninguno','strip'=>'Franja patrocinado','cta'=>'CTA tarjeta','badge'=>'Insignia partner','custom'=>'HTML personalizado (abajo)'] as $val=>$lab)
                    <option value="{{ $val }}" {{ old('video_ad_banner_top_template', $cfg['top_template'] ?? 'none') === $val ? 'selected' : '' }}>{{ $lab }}</option>
                @endforeach
            </select>
            <label class="field-label" style="margin-top:10px;">HTML personalizado (solo si «Integrada» = HTML personalizado)</label>
            <textarea name="video_ad_banner_top_custom_html" rows="4" maxlength="12000" style="width:100%;font-family:monospace;font-size:12px;">{{ old('video_ad_banner_top_custom_html', $cfg['top_custom_html'] ?? '') }}</textarea>
            <label class="field-label" style="margin-top:10px;">Script de ads (JS) para banner superior</label>
            <textarea name="video_ad_banner_top_custom_script" rows="4" maxlength="12000" style="width:100%;font-family:monospace;font-size:12px;" placeholder="<script>... código del proveedor ...</script>">{{ old('video_ad_banner_top_custom_script', $cfg['top_custom_script'] ?? '') }}</textarea>
            <p class="hint-text">Se inserta tal cual en la zona superior. Úsalo para snippets JS del proveedor de anuncios.</p>
        </fieldset>

        <fieldset class="aspecto-card" style="margin-bottom:14px;">
            <legend class="aspecto-card-title">Banner inferior</legend>
            <label class="checkbox-with-icon" style="margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="video_ad_banner_bottom_enabled" value="1" {{ old('video_ad_banner_bottom_enabled', $cfg['bottom_enabled'] ?? false) ? 'checked' : '' }}>
                <span>Mostrar banner debajo del vídeo</span>
            </label>
            <label class="field-label">Origen</label>
            <select name="video_ad_banner_bottom_mode">
                <option value="legacy" {{ old('video_ad_banner_bottom_mode', $cfg['bottom_mode'] ?? 'legacy') === 'legacy' ? 'selected' : '' }}>Plantillas integradas / HTML por zona</option>
                <option value="library" {{ old('video_ad_banner_bottom_mode', $cfg['bottom_mode'] ?? 'legacy') === 'library' ? 'selected' : '' }}>Plantilla guardada</option>
            </select>
            <label class="field-label" style="margin-top:10px;">Plantilla guardada</label>
            <select name="video_ad_banner_bottom_library_id">
                <option value="">— Elegí una —</option>
                @foreach($bannerTemplates as $bt)
                    @if(!empty($bt['enabled']))
                        <option value="{{ $bt['id'] }}" {{ old('video_ad_banner_bottom_library_id', $cfg['bottom_library_id'] ?? '') === $bt['id'] ? 'selected' : '' }}>{{ $bt['name'] }}</option>
                    @endif
                @endforeach
            </select>
            @error('video_ad_banner_bottom_library_id')<p class="hint-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
            <label class="field-label" style="margin-top:10px;">Integrada</label>
            <select name="video_ad_banner_bottom_template">
                @foreach(['none'=>'Ninguno','strip'=>'Franja patrocinado','cta'=>'CTA tarjeta','badge'=>'Insignia partner','custom'=>'HTML personalizado (abajo)'] as $val=>$lab)
                    <option value="{{ $val }}" {{ old('video_ad_banner_bottom_template', $cfg['bottom_template'] ?? 'none') === $val ? 'selected' : '' }}>{{ $lab }}</option>
                @endforeach
            </select>
            <label class="field-label" style="margin-top:10px;">HTML personalizado</label>
            <textarea name="video_ad_banner_bottom_custom_html" rows="4" maxlength="12000" style="width:100%;font-family:monospace;font-size:12px;">{{ old('video_ad_banner_bottom_custom_html', $cfg['bottom_custom_html'] ?? '') }}</textarea>
            <label class="field-label" style="margin-top:10px;">Script de ads (JS) para banner inferior</label>
            <textarea name="video_ad_banner_bottom_custom_script" rows="4" maxlength="12000" style="width:100%;font-family:monospace;font-size:12px;" placeholder="<script>... código del proveedor ...</script>">{{ old('video_ad_banner_bottom_custom_script', $cfg['bottom_custom_script'] ?? '') }}</textarea>
            <p class="hint-text">Se inserta tal cual en la zona inferior. Úsalo para snippets JS del proveedor de anuncios.</p>
        </fieldset>

        <fieldset class="aspecto-card">
            <legend class="aspecto-card-title">Ventana emergente tras cargar el vídeo</legend>
            <label class="checkbox-with-icon" style="margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="video_ad_pop_enabled" value="1" {{ old('video_ad_pop_enabled', $cfg['pop_enabled'] ?? false) ? 'checked' : '' }}>
                <span>Activar popup</span>
            </label>
            <label class="field-label">Plantilla</label>
            <select name="video_ad_pop_template">
                <option value="none" {{ old('video_ad_pop_template', $cfg['pop_template'] ?? 'none') === 'none' ? 'selected' : '' }}>Ninguno</option>
                <option value="simple" {{ old('video_ad_pop_template', $cfg['pop_template'] ?? 'none') === 'simple' ? 'selected' : '' }}>Mensaje simple</option>
                <option value="custom" {{ old('video_ad_pop_template', $cfg['pop_template'] ?? 'none') === 'custom' ? 'selected' : '' }}>HTML personalizado</option>
            </select>
            <label class="field-label" style="margin-top:10px;">Título del diálogo</label>
            <input type="text" name="video_ad_pop_title" value="{{ old('video_ad_pop_title', $cfg['pop_title'] ?? 'Información') }}" maxlength="120" style="width:100%;max-width:420px;">
            <label class="field-label" style="margin-top:10px;">Retraso (ms)</label>
            <input type="number" name="video_ad_pop_delay_ms" value="{{ old('video_ad_pop_delay_ms', $cfg['pop_delay_ms'] ?? 3500) }}" min="0" max="120000" style="width:140px;">
            <label class="field-label" style="margin-top:10px;">HTML personalizado del cuerpo</label>
            <textarea name="video_ad_pop_custom_html" rows="4" maxlength="12000" style="width:100%;font-family:monospace;font-size:12px;">{{ old('video_ad_pop_custom_html', $cfg['pop_custom_html'] ?? '') }}</textarea>
        </fieldset>

        <button type="submit" class="btn-primary label-with-icon" style="margin-top:16px;">@include('web.partials.form-icon', ['name' => 'sparkles']) Guardar zonas y popup</button>
    </form>
</section>
