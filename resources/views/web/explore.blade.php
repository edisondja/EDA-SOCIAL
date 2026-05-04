@extends('web.layout')

@section('title', ($branding['site_name'] ?? 'EDA_SOCIAL') . ' · Explorar')

@section('content')
<section class="filter-row">
    <form method="get" action="{{ route('explore.index') }}" class="filter-row-form">
        <label class="filter-label label-with-icon" for="categoria">@include('web.partials.form-icon', ['name' => 'squares-2x2']) Categoría</label>
        <select name="categoria" id="categoria" onchange="this.form.submit()">
            <option value="">Todas</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ (string) request('categoria') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        <label class="filter-label filter-label-hashtag label-with-icon" for="hashtag">@include('web.partials.form-icon', ['name' => 'hashtag']) Hashtag</label>
        <input type="text" name="hashtag" id="hashtag" placeholder="sin #" value="{{ request('hashtag') }}">
        <button type="submit" class="btn-primary label-with-icon">@include('web.partials.form-icon', ['name' => 'funnel']) Aplicar</button>
    </form>
</section>

<section class="section-header">
    <h2>Publicaciones</h2>
    <small>Mostrando {{ $videos->count() }} de esta página ({{ $videos->total() }} en total).</small>
</section>

<main class="video-grid">
    @foreach($videos as $video)
        @php
            $thumb = $video->thumbnail_url;
            if (!$thumb && $video->relationLoaded('media') && $video->media->count()) {
                $first = $video->media->sortBy('position')->first();
                $thumb = $first->url ?? null;
            }
            $thumbUrl = $thumb && (\Illuminate\Support\Str::startsWith($thumb, 'http://') || \Illuminate\Support\Str::startsWith($thumb, 'https://')) ? $thumb : ($thumb ? url($thumb) : '');
            $preview = $video->preview_url ?: $video->video_url;
            $previewUrl = $preview && (\Illuminate\Support\Str::startsWith($preview, 'http://') || \Illuminate\Support\Str::startsWith($preview, 'https://')) ? $preview : ($preview ? url($preview) : '');
            $sec = max(0, (int) ($video->duration_seconds ?? 0));
            $dur = sprintf('%02d:%02d', intdiv($sec, 60), $sec % 60);
        @endphp
        <article class="video-card">
            <a href="{{ route('posts.show', $video) }}" class="video-preview video-preview-link js-video-hover-preview" style="text-decoration:none;color:inherit;">
                @if($thumbUrl)
                    <img class="video-card-thumb" src="{{ $thumbUrl }}" alt="{{ $video->title }}" loading="lazy" decoding="async">
                @elseif($previewUrl)
                    <div class="video-card-thumb video-card-thumb-placeholder" aria-hidden="true"></div>
                @endif
                @if($previewUrl)
                    <video class="video-card-hover-video" src="{{ $previewUrl }}" muted loop playsinline preload="metadata" poster="{{ $thumbUrl ?: '' }}"></video>
                @endif
                <span class="duration">{{ $dur }}</span>
                @if($previewUrl)
                    <span class="video-card-preview-hint" aria-hidden="true">Vista previa</span>
                @endif
            </a>
            <div class="video-meta">
                <h3><a href="{{ route('posts.show', $video) }}">{{ $video->title }}</a></h3>
                <p>{{ optional($video->channel)->display_name ?? optional($video->author)->name ?? 'Canal' }}</p>
                <span>{{ $video->views_count ?? 0 }} vistas</span>
                @if($video->categories->count())
                    <div class="tags-row">
                        @foreach($video->categories as $cat)
                            <small>#{{ $cat->name }}</small>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>
    @endforeach
</main>

<div class="pagination-wrap" style="padding:16px;text-align:center;">
    {{ $videos->links() }}
</div>

<script>
(function () {
  document.querySelectorAll('.js-video-hover-preview').forEach(function (el) {
    var vid = el.querySelector('video.video-card-hover-video');
    if (!vid) return;
    el.addEventListener('mouseenter', function () {
      el.classList.add('is-preview-active');
      vid.play().catch(function () {});
    });
    el.addEventListener('mouseleave', function () {
      el.classList.remove('is-preview-active');
      vid.pause();
      try { vid.currentTime = 0; } catch (e) {}
    });
  });
})();
</script>
@endsection
