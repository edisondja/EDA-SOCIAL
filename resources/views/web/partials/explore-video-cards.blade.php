@foreach($videos as $video)
    @php
        $thumb = $video->thumbnail_url;
        if (!$thumb && $video->relationLoaded('media') && $video->media->count()) {
            $first = $video->media->sortBy('position')->first();
            $thumb = $first->url ?? null;
        }
        $thumbUrl = $thumb ? \App\Support\MediaSrc::web($thumb) : '';
        $preview = $video->card_preview_url ?? $video->preview_url;
        $previewUrl = $preview ? \App\Support\MediaSrc::web($preview) : '';
        $sec = max(0, (int) ($video->duration_seconds ?? 0));
        $dur = sprintf('%02d:%02d', intdiv($sec, 60), $sec % 60);
    @endphp
    <article class="video-card group flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-soft transition duration-300 hover:border-slate-300 hover:shadow-lift">
        <a href="{{ $video->playUrl() }}" class="js-video-hover-preview relative block aspect-video overflow-hidden bg-slate-950 text-inherit no-underline">
            @if($thumbUrl)
                <img class="edc-card-thumb" src="{{ $thumbUrl }}" alt="{{ $video->title }}" loading="lazy" decoding="async">
            @elseif($previewUrl)
                <div class="edc-card-thumb bg-gradient-to-br from-slate-800 to-slate-950" aria-hidden="true"></div>
            @endif
            @if($previewUrl)
                <video class="video-card-hover-video edc-card-preview-video pointer-events-none" src="{{ $previewUrl }}" muted loop playsinline preload="none" poster="{{ $thumbUrl ?: '' }}"></video>
            @endif
            @if(!$thumbUrl)
                <span class="pointer-events-none absolute left-2 bottom-2 rounded-md bg-slate-900/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm">
                    Generando portada…
                </span>
            @endif
            <span class="pointer-events-none absolute bottom-2 right-2 rounded-md bg-black/75 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-white backdrop-blur-sm">{{ $dur }}</span>
            @if($previewUrl)
                <span class="pointer-events-none absolute left-2 top-2 rounded-md bg-black/55 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100" aria-hidden="true">Hover · vista previa</span>
            @endif
        </a>
        <div class="flex flex-1 flex-col gap-2 p-4">
            <h3 class="line-clamp-2 text-base font-semibold leading-snug text-slate-900">
                <a href="{{ $video->playUrl() }}" class="transition hover:text-brand">{{ $video->title }}</a>
            </h3>
            <p class="truncate text-sm text-slate-600">{{ optional($video->channel)->display_name ?? optional($video->author)->name ?? 'Canal' }}</p>
            <div class="mt-auto flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span>{{ number_format((int) ($video->views_count ?? 0)) }} vistas</span>
                @if($video->categories->count())
                    <span class="hidden h-3 w-px bg-slate-200 sm:inline" aria-hidden="true"></span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($video->categories as $cat)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">#{{ $cat->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </article>
@endforeach
