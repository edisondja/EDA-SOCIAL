@extends('web.layout')

@section('title', $video->title . ' · ' . ($branding['site_name'] ?? 'EDA_SOCIAL'))

@section('content')
@php
    $ads = $videoAds ?? [];
    $stats = $videoStats ?? ['total_views' => (int)($video->views_count ?? 0), 'daily_views' => []];
    $daily = $stats['daily_views'] ?? [];
    $totalStat = (int)($stats['total_views'] ?? $video->views_count ?? 0);
@endphp
<main class="single-page-wrap" style="max-width:1100px;margin:0 auto;padding:16px;">
    <p><a href="{{ route('explore.index') }}" class="text-link">← Volver al feed</a></p>
    <h1 style="margin-top:8px;">{{ $video->title }}</h1>
    <p class="hint-text">Por {{ optional($video->author)->name ?? 'Autor' }} · {{ optional($video->channel)->display_name ?? 'Canal' }}</p>

    @if(!empty($ads['banner_top_enabled']) && !empty($ads['banner_top_html']))
        <div class="video-ad-slot video-ad-slot--top" style="margin-top:16px;">{!! $ads['banner_top_html'] !!}</div>
    @endif

    <div class="carousel single-carousel" style="margin-top:16px;">
        @foreach($video->media->sortBy('position') as $m)
            @php $u = $m->url && (\Illuminate\Support\Str::startsWith($m->url, 'http://') || \Illuminate\Support\Str::startsWith($m->url, 'https://')) ? $m->url : url($m->url); @endphp
            <div class="carousel-media" style="margin-bottom:12px;">
                @if($m->type === 'video')
                    <video src="{{ $u }}" controls playsinline style="width:100%;max-height:520px;border-radius:12px;"></video>
                @else
                    <img src="{{ $u }}" alt="" style="width:100%;max-height:520px;object-fit:contain;border-radius:12px;">
                @endif
            </div>
        @endforeach
    </div>

    @if(!empty($ads['banner_bottom_enabled']) && !empty($ads['banner_bottom_html']))
        <div class="video-ad-slot video-ad-slot--bottom">{!! $ads['banner_bottom_html'] !!}</div>
    @endif

    @if(count($daily))
        @php
            $maxY = max(1, ...array_column($daily, 'views'));
            $w = 320; $h = 56; $pad = 4;
            $innerW = $w - $pad * 2;
            $innerH = $h - $pad * 2;
            $n = count($daily);
            $step = $n > 1 ? $innerW / ($n - 1) : 0;
            $pts = [];
            foreach ($daily as $i => $row) {
                $x = $pad + $i * $step;
                $v = (int)($row['views'] ?? 0);
                $y = $pad + $innerH - ($v / $maxY) * $innerH;
                $pts[] = round($x, 2) . ',' . round($y, 2);
            }
            $poly = $pad . ',' . ($pad + $innerH) . ' ' . implode(' ', $pts) . ' ' . ($pad + $innerW) . ',' . ($pad + $innerH);
            $mc = $branding['menu_color'] ?? '#d83a7c';
        @endphp
        <div class="video-stats-card" aria-label="Vistas por día últimos 30 días">
            <div class="video-stats-card-head">
                <span class="minimal-panel-title" style="margin-bottom:0;">Vistas (30 días)</span>
                <strong class="video-stats-total">{{ number_format($totalStat, 0, ',', '.') }} total</strong>
            </div>
            <svg class="video-stats-svg" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" role="img" aria-hidden="true">
                <defs>
                    <linearGradient id="bladeSparkFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="{{ e($mc) }}" stop-opacity="0.35" />
                        <stop offset="100%" stop-color="{{ e($mc) }}" stop-opacity="0.02" />
                    </linearGradient>
                </defs>
                <polygon fill="url(#bladeSparkFill)" points="{{ $poly }}" />
                <polyline fill="none" stroke="{{ e($mc) }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="{{ implode(' ', $pts) }}" />
            </svg>
            <div class="video-stats-axis">
                <span>{{ \Illuminate\Support\Str::after($daily[0]['date'] ?? '', '-') }}</span>
                <span>{{ \Illuminate\Support\Str::after($daily[$n - 1]['date'] ?? '', '-') }}</span>
            </div>
        </div>
    @endif

    <p style="margin-top:16px;">{{ $video->description }}</p>

    @if($video->hashtags->count())
        <div class="tags-row">
            @foreach($video->hashtags as $tag)
                <a href="{{ route('explore.index', ['hashtag' => $tag->name]) }}" class="tag-btn">#{{ $tag->name }}</a>
            @endforeach
        </div>
    @endif

    @if($related->count())
        <section class="post-related-minimal">
            <h2 class="minimal-panel-title">Relacionados</h2>
            <div class="related-minimal-list">
                @foreach($related as $r)
                    @php
                        $thumb = $r->thumbnail_url;
                        if (!$thumb && $r->relationLoaded('media') && $r->media->count()) {
                            $first = $r->media->sortBy('position')->first();
                            $thumb = $first->url ?? null;
                        }
                        $thumbUrl = $thumb && (\Illuminate\Support\Str::startsWith($thumb, 'http://') || \Illuminate\Support\Str::startsWith($thumb, 'https://')) ? $thumb : ($thumb ? url($thumb) : '');
                    @endphp
                    <a href="{{ route('posts.show', $r) }}" class="related-minimal-item">
                        @if($thumbUrl)
                            <img src="{{ $thumbUrl }}" alt="" loading="lazy" decoding="async">
                        @else
                            <span class="related-minimal-placeholder" aria-hidden="true"></span>
                        @endif
                        <span class="related-minimal-title">{{ $r->title }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section id="comments" class="comment-box comment-thread--minimal post-comments-block">
        <h2 class="minimal-panel-title">Comentarios</h2>
        @auth
            <p id="blade-reply-hint" class="comment-reply-banner" style="display:none;">
                Respondiendo a <strong id="blade-reply-name"></strong>
                <button type="button" class="comment-reply-cancel" id="blade-reply-cancel">Cancelar</button>
            </p>
            <form method="post" action="{{ route('posts.comments.store', $video) }}" class="comment-form comment-form--minimal" id="blade-main-comment-form">
                @csrf
                <input type="hidden" name="parent_id" value="" id="blade-comment-parent-id">
                <textarea id="comment-body" name="body" rows="2" placeholder="Escribe un comentario…" required maxlength="2000"></textarea>
                <button type="submit" class="btn-primary comment-form-submit-minimal">Enviar</button>
            </form>
        @else
            <p class="minimal-empty-hint"><a href="{{ route('login') }}">Inicia sesión</a> para comentar.</p>
        @endauth

        <div class="comment-list comment-list--minimal">
            @forelse($commentsTree as $comment)
                @include('web.partials.comment-thread', ['comment' => $comment, 'video' => $video, 'depth' => 0])
            @empty
                <p class="minimal-empty-hint">Todavía no hay comentarios.</p>
            @endforelse
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
    hint.style.display = 'none';
    if (ta) { ta.placeholder = 'Escribe un comentario…'; }
  }
  document.querySelectorAll('.blade-comment-reply').forEach(function (btn) {
    btn.addEventListener('click', function () {
      parentField.value = btn.getAttribute('data-parent-id') || '';
      nameEl.textContent = btn.getAttribute('data-author-name') || '';
      hint.style.display = 'block';
      if (ta) { ta.placeholder = 'Escribe tu respuesta…'; ta.focus(); }
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
        <div class="video-pop-ad-backdrop" id="blade-video-pop-backdrop" style="display:none;">
            <div class="video-pop-ad-dialog" role="dialog" aria-modal="true" aria-labelledby="blade-pop-title">
                <button type="button" class="video-pop-ad-close" id="blade-video-pop-close" aria-label="Cerrar">×</button>
                <h3 id="blade-pop-title" class="video-pop-ad-title">{{ $ads['pop_title'] ?? 'Información' }}</h3>
                <div class="video-pop-ad-body">{!! $ads['pop_body_html'] !!}</div>
                <button type="button" class="btn-primary video-pop-ad-cta" id="blade-video-pop-cta">Cerrar</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var root = document.getElementById('blade-video-pop-root');
        if (!root) return;
        var backdrop = document.getElementById('blade-video-pop-backdrop');
        var delay = parseInt(root.getAttribute('data-delay') || '3500', 10) || 0;
        function hide() { if (backdrop) backdrop.style.display = 'none'; }
        function show() { if (backdrop) backdrop.style.display = 'grid'; }
        setTimeout(show, Math.max(0, delay));
        var c = document.getElementById('blade-video-pop-close');
        var b = document.getElementById('blade-video-pop-cta');
        if (c) c.addEventListener('click', hide);
        if (b) b.addEventListener('click', hide);
    })();
    </script>
@endif
@endsection
