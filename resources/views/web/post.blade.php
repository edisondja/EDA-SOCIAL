@extends('web.layout')

@section('title', $video->title . ' · ' . ($branding['site_name'] ?? 'EDA_SOCIAL'))

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.min.css" crossorigin="anonymous">
@endpush

@section('content')
@php
    $ads = $videoAds ?? [];
    $posterUrl = $video->thumbnail_url ? \App\Support\MediaSrc::web($video->thumbnail_url) : '';
    $vastEnabled = !empty($ads['vast_enabled']);
    $vastTagUrl = (string) ($ads['vast_tag_url'] ?? '');
    $vastSkipSeconds = (int) ($ads['vast_skip_seconds'] ?? 5);
@endphp
<main class="mx-auto max-w-[1100px] px-1 pb-16 sm:px-4">
    <p class="text-sm"><a href="{{ route('explore.index') }}" class="text-link">← Volver al feed</a></p>
    <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $video->title }}</h1>
    <p class="mt-2 text-sm text-slate-500">Por {{ optional($video->author)->name ?? 'Autor' }} · {{ optional($video->channel)->display_name ?? 'Canal' }}</p>

    @if(!empty($ads['banner_top_enabled']) && !empty($ads['banner_top_html']))
        <div class="video-ad-slot video-ad-slot--top mt-6 rounded-xl border border-slate-100 bg-slate-50/80 p-3">{!! $ads['banner_top_html'] !!}</div>
    @endif

    <div class="mt-6 flex flex-col gap-4">
        @php $mediaSorted = $video->media->sortBy('position'); @endphp
        @forelse($mediaSorted as $m)
            @php
                $u = \App\Support\MediaSrc::web($m->url);
                $path = parse_url($u, PHP_URL_PATH) ?: '';
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($ext === 'webm') { $mime = 'video/webm'; }
                elseif (in_array($ext, ['ogv', 'ogg'], true)) { $mime = 'video/ogg'; }
                elseif ($ext === 'mov') { $mime = 'video/quicktime'; }
                else { $mime = 'video/mp4'; }
            @endphp
            <div>
                @if($m->type === 'video')
                    <div class="eda-player-shell">
                        <video class="eda-plyr-video" playsinline controls preload="metadata" @if($posterUrl !== '') poster="{{ $posterUrl }}" @endif>
                            <source src="{{ $u }}" type="{{ $mime }}">
                        </video>
                    </div>
                @else
                    <img src="{{ $u }}" alt="" class="max-h-[520px] w-full rounded-2xl border border-slate-100 bg-slate-50 object-contain shadow-soft">
                @endif
            </div>
        @empty
            @if(trim((string) $video->video_url) !== '')
                @php
                    $u = \App\Support\MediaSrc::web($video->video_url);
                    $path = parse_url($u, PHP_URL_PATH) ?: '';
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if ($ext === 'webm') { $mime = 'video/webm'; }
                    elseif (in_array($ext, ['ogv', 'ogg'], true)) { $mime = 'video/ogg'; }
                    elseif ($ext === 'mov') { $mime = 'video/quicktime'; }
                    else { $mime = 'video/mp4'; }
                @endphp
                <div class="eda-player-shell">
                    <video class="eda-plyr-video" playsinline controls preload="metadata" @if($posterUrl !== '') poster="{{ $posterUrl }}" @endif>
                        <source src="{{ $u }}" type="{{ $mime }}">
                    </video>
                </div>
            @endif
        @endforelse
    </div>

    @php
        $shareUrl = $video->playUrl();
        $shareTitle = $video->title;
    @endphp
    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start">
        <div class="min-w-0 flex-1">
            @include('web.partials.share-bar', ['shareUrl' => $shareUrl, 'shareTitle' => $shareTitle])
        </div>
        @auth
            <button type="button" class="eda-btn-secondary shrink-0 border-red-100 bg-red-50/80 text-red-800 hover:bg-red-50 video-report-open-btn">Reportar vídeo</button>
        @else
            <span class="hint-text shrink-0"><a href="{{ route('login') }}" class="text-link">Inicia sesión para reportar</a></span>
        @endauth
    </div>

    @include('web.partials.video-rating', [
        'video' => $video,
        'ratingAvg' => $ratingAvg,
        'ratingCount' => $ratingCount,
        'userRating' => $userRating,
    ])

    @auth
        <dialog id="video-report-dialog" class="video-report-dialog">
            <form method="post" action="{{ route('posts.report', ['video' => $video->id, 'slug' => $video->playSlug()]) }}" class="video-report-dialog__form">
                @csrf
                <h3 class="text-lg font-bold text-slate-900">Reportar contenido</h3>
                <p class="hint-text text-sm">El equipo de moderación revisará tu reporte. Abusar de esta función puede conllevar acciones sobre la cuenta.</p>
                <div>
                    <label class="eda-label" for="video-report-reason">Motivo</label>
                    <select id="video-report-reason" name="reason" required class="eda-input mt-1">
                        @foreach(\App\VideoReport::reasonLabels() as $val => $lab)
                            <option value="{{ $val }}">{{ $lab }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="eda-label" for="video-report-details">Detalles (opcional)</label>
                    <textarea id="video-report-details" name="details" rows="4" maxlength="2000" class="eda-input mt-1" placeholder="Describí el problema si querés aportar más contexto…"></textarea>
                </div>
                <div class="mt-2 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                    <button type="submit" class="eda-btn-primary">Enviar reporte</button>
                    <button type="button" class="eda-btn-secondary video-report-close-btn">Cancelar</button>
                </div>
            </form>
        </dialog>
        <script>
        (function () {
          var dlg = document.getElementById('video-report-dialog');
          var openBtn = document.querySelector('.video-report-open-btn');
          var closeBtns = document.querySelectorAll('.video-report-close-btn');
          if (!dlg || !openBtn) return;
          openBtn.addEventListener('click', function () {
            if (typeof dlg.showModal === 'function') {
              dlg.showModal();
            } else {
              window.alert('Tu navegador no permite el cuadro de reporte. Probá actualizar el navegador.');
            }
          });
          closeBtns.forEach(function (b) {
            b.addEventListener('click', function () { dlg.close(); });
          });
          dlg.addEventListener('click', function (e) {
            if (e.target === dlg) dlg.close();
          });
        })();
        </script>
    @endauth

    @if(trim((string) $video->description) !== '')
        <section class="eda-card mt-10 border-slate-100" aria-labelledby="single-video-desc-heading">
            <h2 id="single-video-desc-heading" class="text-lg font-bold text-slate-900">Descripción</h2>
            <div class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{!! nl2br(e($video->description)) !!}</div>
        </section>
    @endif

    @if(!empty($ads['banner_bottom_enabled']) && !empty($ads['banner_bottom_html']))
        <div class="video-ad-slot video-ad-slot--bottom mt-10 rounded-xl border border-slate-100 bg-slate-50/80 p-3">{!! $ads['banner_bottom_html'] !!}</div>
    @endif

    @if($video->hashtags->count())
        <div class="mt-8 flex flex-wrap gap-2">
            @foreach($video->hashtags as $tag)
                <a href="{{ route('explore.index', ['hashtag' => $tag->name]) }}" class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200/80 transition hover:bg-white hover:text-brand">#{{ $tag->name }}</a>
            @endforeach
        </div>
    @endif

    @if($related->count())
        <section class="mt-12 pb-8 sm:pb-10">
            <h2 class="text-lg font-bold text-slate-900">Relacionados</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($related as $r)
                    @php
                        $thumb = $r->thumbnail_url;
                        if (!$thumb && $r->relationLoaded('media') && $r->media->count()) {
                            $first = $r->media->sortBy('position')->first();
                            $thumb = $first->url ?? null;
                        }
                        $thumbUrl = $thumb ? \App\Support\MediaSrc::web($thumb) : '';
                    @endphp
                    <li>
                        <a href="{{ $r->playUrl() }}" class="group flex gap-3 rounded-xl border border-slate-200/80 bg-white p-3 shadow-sm transition hover:border-slate-300 hover:shadow-soft">
                            @if($thumbUrl)
                                <img src="{{ $thumbUrl }}" alt="" loading="lazy" decoding="async" class="h-16 w-28 shrink-0 rounded-lg object-cover ring-1 ring-black/5">
                            @else
                                <span class="flex h-16 w-28 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 text-xs font-semibold text-slate-500" aria-hidden="true">Sin miniatura</span>
                            @endif
                            <span class="line-clamp-2 min-w-0 flex-1 text-sm font-semibold leading-snug text-slate-900 group-hover:text-brand">{{ $r->title }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section id="comments" class="mt-16 border-t border-slate-200/70 pt-12 sm:mt-20 sm:pt-14">
        <div class="flex items-start gap-4 sm:gap-5">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-md sm:h-12 sm:w-12">
                @include('web.partials.form-icon', ['name' => 'chat-bubble-left', 'size' => 22, 'class' => 'text-white'])
            </span>
            <div class="min-w-0 pt-0.5">
                <h2 class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Comentarios</h2>
                <p class="mt-1 text-sm leading-snug text-slate-500">Conversación abierta y respetuosa sobre este video.</p>
            </div>
        </div>

        @auth
            @php
                $__cu = auth()->user();
                $__cuAvatar = $__cu && $__cu->avatar_url ? \App\Support\MediaSrc::web($__cu->avatar_url) : '';
            @endphp
            <div class="mt-8">
                <p
                    id="blade-reply-hint"
                    class="mb-5 hidden rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3.5 text-sm text-slate-800 shadow-sm ring-1 ring-slate-100"
                    style="border-left-width: 4px; border-left-color: var(--menu-color, #d83a7c);"
                >
                    <span class="text-slate-500">Respondiendo a</span>
                    <strong id="blade-reply-name" class="font-semibold text-slate-900"></strong>
                    <button type="button" class="ml-3 rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 transition hover:bg-white hover:text-slate-800" id="blade-reply-cancel">Cancelar</button>
                </p>
                <form method="post" action="{{ $video->commentsStoreUrl() }}" class="flex flex-col gap-4 sm:flex-row sm:items-start" id="blade-main-comment-form">
                    @csrf
                    <input type="hidden" name="parent_id" value="" id="blade-comment-parent-id">
                    <div class="flex shrink-0 justify-center sm:pt-1">
                        @if($__cuAvatar !== '')
                            <img src="{{ $__cuAvatar }}" alt="" class="h-11 w-11 rounded-full object-cover shadow-md ring-2 ring-white sm:h-12 sm:w-12" width="48" height="48">
                        @else
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-slate-800 to-slate-900 text-base font-semibold text-white shadow-md ring-2 ring-white sm:h-12 sm:w-12 sm:text-lg" aria-hidden="true">{{ \Illuminate\Support\Str::substr($__cu->name, 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 flex flex-col">
                        <label class="sr-only" for="comment-body">Tu comentario</label>
                        <textarea
                            id="comment-body"
                            name="body"
                            rows="3"
                            placeholder="Escribí tu comentario…"
                            required
                            maxlength="2000"
                            class="eda-input min-h-[5.5rem] w-full shrink-0 resize-y rounded-2xl border-slate-200 bg-white py-3.5 text-[15px] leading-relaxed shadow-inner placeholder:text-slate-400 focus:border-slate-300"
                        ></textarea>
                        <div class="mt-2 flex shrink-0 flex-col gap-2">
                            <button type="submit" class="eda-btn-primary w-full justify-center rounded-xl px-8 py-2.5 text-sm font-semibold shadow-md sm:w-auto sm:self-end">
                                Publicar comentario
                            </button>
                            <p class="text-[11px] leading-snug text-slate-400 sm:text-xs sm:text-right">Máximo 2.000 caracteres · Sé cordial.</p>
                        </div>
                    </div>
                </form>
            </div>
        @else
            <div class="mt-8 text-center sm:text-left">
                <p class="text-sm text-slate-600">
                    <a href="{{ route('login') }}" class="text-link font-semibold">Iniciá sesión</a>
                    para sumarte a la conversación.
                </p>
            </div>
        @endauth

        <div class="mt-10">
            <div class="mx-auto max-w-3xl space-y-4">
                @forelse($commentsTree as $comment)
                    @include('web.partials.comment-thread', ['comment' => $comment, 'video' => $video, 'depth' => 0])
                @empty
                    <div class="py-12 text-center">
                        <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                            @include('web.partials.form-icon', ['name' => 'chat-bubble-left', 'size' => 24])
                        </span>
                        <p class="text-base font-semibold text-slate-700">Todavía no hay comentarios</p>
                        <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-500">Cuando alguien publique el primero, aparecerá aquí.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
</main>

@auth
<script>
(function () {
  var parentField = document.getElementById('blade-comment-parent-id');
  var hint = document.getElementById('blade-reply-hint');
  var nameEl = document.getElementById('blade-reply-name');
  var cancel = document.getElementById('blade-reply-cancel');
  var ta = document.getElementById('comment-body');
  if (!parentField || !hint || !nameEl) return;
  function clearReply() {
    parentField.value = '';
    hint.classList.add('hidden');
    if (ta) { ta.placeholder = 'Escribí tu comentario…'; }
  }
  document.querySelectorAll('.blade-comment-reply').forEach(function (btn) {
    btn.addEventListener('click', function () {
      parentField.value = btn.getAttribute('data-parent-id') || '';
      nameEl.textContent = btn.getAttribute('data-author-name') || '';
      hint.classList.remove('hidden');
      if (ta) { ta.placeholder = 'Escribí tu respuesta…'; ta.focus(); }
    });
  });
  if (cancel) cancel.addEventListener('click', clearReply);
  var mainForm = document.getElementById('blade-main-comment-form');
  if (mainForm) mainForm.addEventListener('submit', function () {
    setTimeout(clearReply, 0);
  });
})();
</script>
@endauth

@if(!empty($ads['pop_enabled']) && !empty($ads['pop_body_html']))
    <div id="blade-video-pop-root" hidden data-delay="{{ (int)($ads['pop_delay_ms'] ?? 3500) }}">
        <div class="video-pop-ad-backdrop hidden" id="blade-video-pop-backdrop">
            <div class="video-pop-ad-dialog" role="dialog" aria-modal="true" aria-labelledby="blade-pop-title">
                <button type="button" class="video-pop-ad-close" id="blade-video-pop-close" aria-label="Cerrar">×</button>
                <h3 id="blade-pop-title" class="video-pop-ad-title">{{ $ads['pop_title'] ?? 'Información' }}</h3>
                <div class="pop-ad-body mt-4 text-sm leading-relaxed text-slate-700">{!! $ads['pop_body_html'] !!}</div>
                <button type="button" class="eda-btn-primary mt-8 w-full justify-center sm:w-auto" id="blade-video-pop-cta">Cerrar</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var root = document.getElementById('blade-video-pop-root');
        if (!root) return;
        var backdrop = document.getElementById('blade-video-pop-backdrop');
        var delay = parseInt(root.getAttribute('data-delay') || '3500', 10) || 0;
        function hide() {
            if (backdrop) backdrop.classList.add('hidden');
        }
        function show() {
            if (backdrop) backdrop.classList.remove('hidden');
        }
        setTimeout(show, Math.max(0, delay));
        var c = document.getElementById('blade-video-pop-close');
        var b = document.getElementById('blade-video-pop-cta');
        if (c) c.addEventListener('click', hide);
        if (b) b.addEventListener('click', hide);
    })();
    </script>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.min.js" crossorigin="anonymous"></script>
<script>
(function () {
  if (typeof Plyr === 'undefined') return;
  var title = @json($video->title);
  var vastEnabled = @json($vastEnabled);
  var vastTagUrl = @json($vastTagUrl);
  var vastSkipSeconds = @json($vastSkipSeconds);
  var vastMediaPromise = null;
  var i18n = {
    restart: 'Reiniciar',
    rewind: 'Retroceder {seektime} s',
    play: 'Reproducir',
    pause: 'Pausar',
    fastForward: 'Avanzar {seektime} s',
    seek: 'Ir a',
    seekLabel: '{currentTime} de {duration}',
    played: 'Reproducido',
    buffered: 'En búfer',
    currentTime: 'Tiempo',
    duration: 'Duración',
    volume: 'Volumen',
    mute: 'Silenciar',
    unmute: 'Activar sonido',
    enterFullscreen: 'Pantalla completa',
    exitFullscreen: 'Salir de pantalla completa',
    frameTitle: 'Reproductor: ' + title,
    settings: 'Ajustes',
    pip: 'Imagen en imagen',
    menuBack: 'Atrás',
    speed: 'Velocidad',
    normal: 'Normal',
    quality: 'Calidad',
    loop: 'Repetir'
  };
  function detectType(url) {
    if (/\.webm(\?.*)?$/i.test(url)) return 'video/webm';
    if (/\.(ogv|ogg)(\?.*)?$/i.test(url)) return 'video/ogg';
    if (/\.m3u8(\?.*)?$/i.test(url)) return 'application/vnd.apple.mpegurl';
    return 'video/mp4';
  }

  function extractVastMediaUrl(vastXml) {
    try {
      var doc = new DOMParser().parseFromString(vastXml, 'application/xml');
      var mediaNodes = doc.querySelectorAll('MediaFile');
      for (var i = 0; i < mediaNodes.length; i++) {
        var node = mediaNodes[i];
        var src = (node.textContent || '').trim();
        if (!src) continue;
        if (/^https?:\/\//i.test(src)) return src;
      }
    } catch (e) {}
    return '';
  }

  function resolveVastMediaUrl() {
    if (!vastEnabled || !vastTagUrl) return Promise.resolve('');
    if (!vastMediaPromise) {
      vastMediaPromise = fetch(vastTagUrl, { credentials: 'omit' })
        .then(function (r) { return r.ok ? r.text() : ''; })
        .then(function (xml) { return xml ? extractVastMediaUrl(xml) : ''; })
        .catch(function () { return ''; });
    }
    return vastMediaPromise;
  }

  function attachHlsIfNeeded(el, sourceUrl) {
    if (!/\.m3u8(\?.*)?$/i.test(sourceUrl)) return null;
    if (typeof Hls === 'undefined' || !Hls.isSupported()) return null;
    var hls = new Hls();
    hls.loadSource(sourceUrl);
    hls.attachMedia(el);
    return hls;
  }

  document.querySelectorAll('.eda-plyr-video').forEach(function (el) {
    var sourceEl = el.querySelector('source');
    var src = sourceEl ? (sourceEl.getAttribute('src') || '') : '';
    var sourceType = sourceEl ? (sourceEl.getAttribute('type') || detectType(src)) : detectType(src);
    var hlsInstance = attachHlsIfNeeded(el, src);
    var player = new Plyr(el, {
      controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'pip', 'fullscreen', 'settings'],
      settings: ['speed'],
      speed: { selected: 1, options: [0.75, 1, 1.25, 1.5, 2] },
      keyboard: { focused: true, global: false },
      tooltips: { controls: true, seek: true },
      hideControls: true,
      resetOnEnd: false,
      i18n: i18n
    });

    if (!vastEnabled || !sourceEl || !src) return;

    var vastPlayed = false;
    var vastPlaying = false;
    var skipBtn = null;

    function clearSkipButton() {
      if (skipBtn && skipBtn.parentNode) {
        skipBtn.parentNode.removeChild(skipBtn);
      }
      skipBtn = null;
    }

    function restoreMainContent() {
      clearSkipButton();
      vastPlaying = false;
      vastPlayed = true;
      sourceEl.setAttribute('src', src);
      sourceEl.setAttribute('type', sourceType);
      if (hlsInstance) {
        try { hlsInstance.destroy(); } catch (e) {}
        hlsInstance = null;
      }
      hlsInstance = attachHlsIfNeeded(el, src);
      el.load();
      player.play().catch(function () {});
    }

    function mountSkipButton() {
      var shell = el.closest('.eda-player-shell');
      if (!shell) return;
      shell.style.position = 'relative';
      skipBtn = document.createElement('button');
      skipBtn.type = 'button';
      skipBtn.textContent = 'Omitir anuncio';
      skipBtn.style.cssText = 'position:absolute;right:12px;top:12px;z-index:20;border:0;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:600;background:rgba(15,23,42,.78);color:#fff;cursor:pointer;';
      skipBtn.disabled = true;
      shell.appendChild(skipBtn);
      var remain = Math.max(0, parseInt(vastSkipSeconds, 10) || 0);
      if (remain === 0) {
        skipBtn.disabled = false;
        return;
      }
      skipBtn.textContent = 'Omitir en ' + remain + 's';
      var t = setInterval(function () {
        remain -= 1;
        if (remain <= 0) {
          clearInterval(t);
          if (!skipBtn) return;
          skipBtn.disabled = false;
          skipBtn.textContent = 'Omitir anuncio';
          return;
        }
        if (skipBtn) skipBtn.textContent = 'Omitir en ' + remain + 's';
      }, 1000);
      skipBtn.addEventListener('click', function () {
        if (skipBtn && skipBtn.disabled) return;
        restoreMainContent();
      });
    }

    player.on('play', function () {
      if (vastPlayed || vastPlaying) return;
      vastPlaying = true;
      player.pause();
      resolveVastMediaUrl().then(function (adUrl) {
        if (!adUrl) {
          vastPlayed = true;
          vastPlaying = false;
          player.play().catch(function () {});
          return;
        }

        if (hlsInstance) {
          try { hlsInstance.destroy(); } catch (e) {}
          hlsInstance = null;
        }

        sourceEl.setAttribute('src', adUrl);
        sourceEl.setAttribute('type', detectType(adUrl));
        mountSkipButton();
        el.load();
        el.onended = restoreMainContent;
        el.onerror = restoreMainContent;
        player.play().catch(function () { restoreMainContent(); });
      });
    });
  });
})();
</script>
@endpush
