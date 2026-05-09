@extends('web.layout')

@section('title', ($branding['site_name'] ?? 'EDA_SOCIAL') . ' · Explorar')

@section('content')
<section class="mb-8 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft sm:p-6">
    <form method="get" action="{{ route('explore.index') }}" class="flex flex-col gap-6">
        <div class="w-full">
            <label class="eda-label" for="explore-search">
                @include('web.partials.form-icon', ['name' => 'magnifying-glass']) Buscar
            </label>
            <div class="mt-1.5 flex flex-col gap-3 sm:flex-row sm:items-stretch">
                <input
                    type="search"
                    name="search"
                    id="explore-search"
                    value="{{ request('search') }}"
                    class="eda-input min-h-[44px] flex-1 sm:min-w-0"
                    placeholder="Título, descripción, etiqueta, categoría o canal…"
                    autocomplete="off"
                    maxlength="160"
                    enterkeyhint="search"
                >
                <button type="submit" class="eda-btn-primary shrink-0 justify-center px-8 sm:w-auto">
                    @include('web.partials.form-icon', ['name' => 'magnifying-glass']) Buscar
                </button>
            </div>
        </div>
        @php
            $exploreSecondaryOpen = request()->filled('categoria') || request()->filled('hashtag');
        @endphp
        <details class="explore-secondary-details rounded-xl border border-slate-200/90 bg-slate-50/50 open:bg-white open:shadow-inner sm:rounded-2xl" @if($exploreSecondaryOpen) open @endif>
            <summary class="explore-secondary-summary flex cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-3.5 text-left text-sm font-semibold text-slate-800 outline-none ring-slate-300 transition hover:bg-slate-100/90 focus-visible:ring-2">
                <span class="inline-flex flex-wrap items-center gap-2">
                    <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center [&_svg]:block [&_svg]:h-[18px] [&_svg]:w-[18px] [&_svg]:max-h-[18px] [&_svg]:max-w-[18px]" aria-hidden="true">
                        @include('web.partials.form-icon', ['name' => 'funnel', 'size' => 18])
                    </span>
                    <span>Filtros secundarios</span>
                    @if($exploreSecondaryOpen)
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm" style="background: var(--menu-color, #d83a7c);">Activos</span>
                    @endif
                </span>
                <svg class="explore-secondary-chevron shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </summary>
            <div class="border-t border-slate-200/80 px-3 pb-4 pt-3 sm:px-4">
                <div class="flex flex-col gap-5 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="min-w-[180px] flex-1">
                        <label class="eda-label" for="categoria">
                            @include('web.partials.form-icon', ['name' => 'squares-2x2']) Categoría
                        </label>
                        <select name="categoria" id="categoria" class="eda-input mt-1.5 bg-white" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (string) request('categoria') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[200px] flex-1">
                        <label class="eda-label" for="hashtag">
                            @include('web.partials.form-icon', ['name' => 'hashtag']) Hashtag
                        </label>
                        <input type="text" name="hashtag" id="hashtag" class="eda-input mt-1.5 bg-white" placeholder="sin #" value="{{ request('hashtag') }}" autocomplete="off">
                    </div>
                    <button type="submit" class="eda-btn-secondary shrink-0 border-slate-300 sm:mb-0">
                        @include('web.partials.form-icon', ['name' => 'funnel']) Aplicar filtros
                    </button>
                </div>
                <p class="mt-3 text-xs leading-snug text-slate-500">La categoría se aplica al elegirla. Para hashtag usá «Aplicar filtros».</p>
            </div>
        </details>
        @if(request()->hasAny(['search', 'categoria', 'hashtag']))
            <p class="text-center text-sm sm:text-left">
                <a href="{{ route('explore.index') }}" class="text-link font-semibold">Quitar filtros y búsqueda</a>
            </p>
        @endif
    </form>
</section>

<div class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
    <h2 class="text-2xl font-bold tracking-tight text-slate-900">Publicaciones</h2>
    <p id="explore-feed-status" class="text-sm text-slate-500">
        Mostrando <span id="explore-loaded-count" class="font-semibold text-slate-700">{{ $videos->count() }}</span>
        de <span id="explore-total-count" class="font-semibold text-slate-700">{{ $videos->total() }}</span> en total.
    </p>
</div>

<main class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3" id="explore-video-grid">
    @include('web.partials.explore-video-cards', ['videos' => $videos])
</main>

@if($videos->hasMorePages())
    <div id="explore-scroll-sentinel" class="mt-8 h-px w-full" aria-hidden="true"></div>
@endif

<p id="explore-infinite-loading" class="hidden py-6 text-center text-sm font-medium text-slate-500">Cargando más…</p>
<p id="explore-infinite-end" class="hidden py-6 text-center text-sm text-slate-500">No hay más publicaciones.</p>

@php
    $exploreFeedBootstrap = [
        'exploreUrl' => route('explore.index'),
        'hasMore' => $videos->hasMorePages(),
        'nextPage' => $videos->hasMorePages() ? ($videos->currentPage() + 1) : null,
        'lastPage' => $videos->lastPage(),
        'perPage' => $videos->perPage(),
        'loaded' => $videos->count(),
        'total' => $videos->total(),
    ];
@endphp
<script type="application/json" id="explore-feed-bootstrap">@json($exploreFeedBootstrap)</script>

<script>
(function () {
  function bindHoverPreviews(scope) {
    var root = scope || document;
    root.querySelectorAll('.js-video-hover-preview').forEach(function (el) {
      if (el.dataset.edaHoverBound === '1') return;
      el.dataset.edaHoverBound = '1';
      var vid = el.querySelector('video.video-card-hover-video');
      if (!vid) return;
      el.addEventListener('mouseenter', function () {
        el.classList.add('is-preview-active');
        if (Number.isFinite(vid.duration) && vid.duration > 2) {
          try {
            var start = Math.random() * Math.max(0.5, vid.duration - 1.5);
            vid.currentTime = start;
          } catch (e) {}
        }
        vid.play().catch(function () {});
      });
      el.addEventListener('mouseleave', function () {
        el.classList.remove('is-preview-active');
        vid.pause();
        try { vid.currentTime = 0; } catch (e) {}
      });
    });
  }

  function bindEnqueueHoverCardMedia(scope) {
    var root = scope || document;
    root.querySelectorAll('a.js-video-hover-preview[data-hover-card-queue]').forEach(function (el) {
      if (el.dataset.edaHoverQueueBound === '1') return;
      el.dataset.edaHoverQueueBound = '1';
      var url = el.getAttribute('data-hover-card-queue');
      if (!url) return;
      el.addEventListener('mouseenter', function () {
        if (el.dataset.edaHoverQueued === '1') return;
        el.dataset.edaHoverQueued = '1';
        var meta = document.querySelector('meta[name="csrf-token"]');
        var token = meta && meta.getAttribute('content');
        if (!token) return;
        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: '{}',
        }).catch(function () {});
      });
    });
  }

  bindHoverPreviews(document.getElementById('explore-video-grid'));
  bindEnqueueHoverCardMedia(document.getElementById('explore-video-grid'));

  var bootEl = document.getElementById('explore-feed-bootstrap');
  var sentinel = document.getElementById('explore-scroll-sentinel');
  var grid = document.getElementById('explore-video-grid');
  var loadingEl = document.getElementById('explore-infinite-loading');
  var endEl = document.getElementById('explore-infinite-end');
  var loadedSpan = document.getElementById('explore-loaded-count');
  var totalSpan = document.getElementById('explore-total-count');

  if (!bootEl || !sentinel || !grid) return;

  var boot = {};
  try {
    boot = JSON.parse(bootEl.textContent || '{}');
  } catch (e) {
    return;
  }

  if (!boot.hasMore || boot.nextPage == null) return;

  var loading = false;
  var nextPage = boot.nextPage;
  var lastPage = boot.lastPage || 1;
  var loaded = typeof boot.loaded === 'number' ? boot.loaded : grid.querySelectorAll('.video-card').length;
  var total = typeof boot.total === 'number' ? boot.total : loaded;

  function setLoading(on) {
    loading = on;
    if (loadingEl) loadingEl.classList.toggle('hidden', !on);
  }

  function updateStatus() {
    if (loadedSpan) loadedSpan.textContent = String(loaded);
    if (totalSpan) totalSpan.textContent = String(total);
  }

  var io = null;

  function finishNoMore() {
    if (io) {
      try { io.disconnect(); } catch (e) {}
      io = null;
    }
    if (sentinel && sentinel.parentNode) sentinel.parentNode.removeChild(sentinel);
    if (endEl) endEl.classList.remove('hidden');
  }

  function fetchNext() {
    if (loading || nextPage > lastPage) return;
    loading = true;
    setLoading(true);

    var params = new URLSearchParams(window.location.search);
    params.set('fragment', '1');
    params.set('page', String(nextPage));
    if (boot.perPage) params.set('per_page', String(boot.perPage));

    var base = boot.exploreUrl.split('?')[0];
    var url = base + '?' + params.toString();

    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
      credentials: 'same-origin'
    })
      .then(function (r) {
        if (!r.ok) throw new Error('fetch');
        return r.text();
      })
      .then(function (html) {
        var trimmed = html.trim();
        if (!trimmed) {
          finishNoMore();
          return;
        }
        var tpl = document.createElement('template');
        tpl.innerHTML = trimmed;
        var nodes = tpl.content.querySelectorAll('.video-card');
        if (!nodes.length) {
          finishNoMore();
          return;
        }
        nodes.forEach(function (node) {
          grid.appendChild(node);
        });
        loaded += nodes.length;
        bindHoverPreviews(grid);
        bindEnqueueHoverCardMedia(grid);

        nextPage += 1;
        if (nextPage > lastPage || nodes.length < (boot.perPage || 20)) {
          finishNoMore();
        }
        updateStatus();
      })
      .catch(function () {})
      .then(function () {
        setLoading(false);
      });

  }

  io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      fetchNext();
    });
  }, { root: null, rootMargin: '280px', threshold: 0 });

  io.observe(sentinel);
})();
</script>
@endsection
