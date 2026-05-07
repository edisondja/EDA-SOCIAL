@php
    $depth = $depth ?? 0;
    $user = $comment->user;
    $avatarUrl = ($user && !empty($user->avatar_url)) ? \App\Support\MediaSrc::web($user->avatar_url) : '';
    $displayName = $user->name ?? 'Usuario';
    $handle = ($user && !empty($user->username)) ? '@'.$user->username : '';
    $when = $comment->created_at ? $comment->created_at->format('d/m/Y H:i') : '';
    $points = max(0, (int) $comment->points);
    $isReply = $depth > 0;
@endphp
<article
    id="c-{{ $comment->id }}"
    class="{{ $isReply ? 'relative mt-4 border-l-2 border-slate-200/90 pl-5 sm:pl-6' : 'rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100/80 sm:p-5' }}"
    @if($isReply)
        style="border-left-color: color-mix(in srgb, var(--menu-color, #d83a7c) 35%, rgb(226 232 240));"
    @endif
>
    <div class="flex gap-3 sm:gap-4">
        <div class="shrink-0 pt-0.5">
            @if($avatarUrl !== '')
                <img
                    class="h-11 w-11 rounded-full object-cover shadow-md ring-2 ring-white sm:h-12 sm:w-12"
                    src="{{ $avatarUrl }}"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    width="48"
                    height="48"
                >
            @else
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-slate-100 to-slate-200 text-base font-semibold text-slate-600 shadow-inner ring-2 ring-white sm:h-12 sm:w-12 sm:text-lg"
                    aria-hidden="true"
                >{{ \Illuminate\Support\Str::substr($displayName, 0, 1) }}</span>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <header class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="text-[15px] font-semibold leading-tight text-slate-900">{{ $displayName }}</span>
                @if($handle !== '')
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ $handle }}</span>
                @endif
                @if($when !== '')
                    <span class="text-[11px] tabular-nums text-slate-400 sm:text-xs">{{ $when }}</span>
                @endif
            </header>
            <div class="mt-2.5 text-[15px] leading-relaxed text-slate-700">
                <p class="whitespace-pre-wrap">{{ $comment->body }}</p>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100/90 pt-3">
                @auth
                    <div class="flex items-center gap-1 rounded-xl bg-slate-50 p-0.5 ring-1 ring-inset ring-slate-100">
                        <form method="post" action="{{ route('posts.comments.vote', $comment) }}" class="inline">
                            @csrf
                            <input type="hidden" name="value" value="1">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-white hover:text-rose-700 hover:shadow-sm"
                                aria-label="Voto positivo"
                                title="Me gusta"
                            >
                                @include('web.partials.form-icon', ['name' => 'heart', 'size' => 15])
                                <span>{{ $points }}</span>
                            </button>
                        </form>
                        <span class="h-4 w-px bg-slate-200" aria-hidden="true"></span>
                        <form method="post" action="{{ route('posts.comments.vote', $comment) }}" class="inline">
                            @csrf
                            <input type="hidden" name="value" value="-1">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg px-2 py-1.5 text-xs font-bold text-slate-500 transition hover:bg-white hover:text-slate-700 hover:shadow-sm"
                                aria-label="Voto negativo"
                                title="No me gusta"
                            >
                                @include('web.partials.form-icon', ['name' => 'minus', 'size' => 15])
                            </button>
                        </form>
                    </div>
                    <button
                        type="button"
                        class="blade-comment-reply inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-brand"
                        data-parent-id="{{ $comment->id }}"
                        data-author-name="{{ $displayName }}"
                    >
                        @include('web.partials.form-icon', ['name' => 'arrow-uturn-left', 'size' => 14])
                        Responder
                    </button>
                @else
                    <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                        @include('web.partials.form-icon', ['name' => 'heart', 'size' => 14])
                        {{ $points }} me gusta
                    </span>
                @endauth
            </div>
        </div>
    </div>
    @if($comment->relationLoaded('replies') && $comment->replies->count())
        <div class="mt-3 space-y-3">
            @foreach($comment->replies as $child)
                @include('web.partials.comment-thread', ['comment' => $child, 'video' => $video, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</article>
