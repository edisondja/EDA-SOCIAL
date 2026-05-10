@extends('web.layout')

@section('title', 'Mis publicaciones')

@section('content')
<main class="login-card mx-auto mt-6 max-w-3xl border border-slate-200/80 px-6 py-8 sm:px-10 sm:py-10">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Mis publicaciones</h1>
    <p class="mt-2 text-sm text-slate-500">Solo ves y editás los vídeos que publicaste con tu cuenta.</p>

    <p class="mt-6">
        <a href="{{ route('account.show') }}" class="text-link text-sm">← Mi cuenta</a>
        <span class="text-slate-300">·</span>
        <a href="{{ route('publish.create') }}" class="text-link text-sm js-open-publish-modal">Publicar nuevo</a>
    </p>

    @if($videos->isEmpty())
        <p class="mt-10 text-center text-slate-600">Todavía no publicaste ningún vídeo.</p>
        <p class="mt-4 text-center"><a href="{{ route('publish.create') }}" class="eda-btn-primary js-open-publish-modal inline-flex justify-center">Publicar</a></p>
    @else
        <ul class="mt-8 divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/50">
            @foreach($videos as $v)
                <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900">{{ \Illuminate\Support\Str::limit($v->title, 100) }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            @if($v->moderation_status === 'blocked')
                                <span class="font-medium text-red-700">Bloqueado</span>
                            @elseif($v->moderation_status === 'review')
                                <span class="font-medium text-amber-700">En revisión</span>
                            @else
                                <span class="text-emerald-700">Activo</span>
                            @endif
                            · {{ $v->is_published ? 'Visible en el feed' : 'No publicado' }}
                            · {{ number_format((int) $v->views_count, 0, ',', '.') }} vistas
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if($v->is_published && $v->moderation_status === 'active')
                            <a href="{{ route('posts.show', ['video' => $v->id, 'slug' => $v->playSlug()]) }}" class="eda-btn-secondary !px-3 !py-2 text-sm" target="_blank" rel="noopener noreferrer">Ver</a>
                        @endif
                        <a href="{{ route('account.videos.edit', $v) }}" class="eda-btn-primary !px-3 !py-2 text-sm">Editar</a>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $videos->links() }}</div>
    @endif
</main>
@endsection
