@extends('web.layout')

@section('title', 'Editar publicación')

@section('content')
<main class="login-card mx-auto mt-6 max-w-2xl border border-slate-200/80 px-6 py-8 sm:px-10 sm:py-10">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Editar publicación</h1>
    <p class="mt-2 text-sm text-slate-500">Podés cambiar título, URL amigable, descripción, enlaces de media y categorías. La moderación la gestiona el equipo del sitio.</p>

    <p class="mt-6 text-sm">
        <a href="{{ route('account.videos.index') }}" class="text-link">← Mis publicaciones</a>
        @if($video->is_published && $video->moderation_status === 'active')
            <span class="text-slate-300">·</span>
            <a href="{{ route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()]) }}" class="text-link" target="_blank" rel="noopener noreferrer">Ver en el sitio</a>
        @endif
    </p>

    @if($video->moderation_status !== 'active')
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
            Esta publicación está <strong>{{ $video->moderation_status === 'blocked' ? 'bloqueada' : 'en revisión' }}</strong>. Podés seguir editando los datos; cuando un moderador la reactive, volverá a mostrarse según las reglas del sitio.
        </div>
    @endif

    <form method="post" action="{{ route('account.videos.update', $video) }}" enctype="multipart/form-data" class="mt-8 flex flex-col gap-4">
        @csrf
        @method('PUT')

        <div>
            <label class="eda-label" for="mv-title">Título</label>
            <input id="mv-title" type="text" name="title" value="{{ old('title', $video->title) }}" maxlength="180" required class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300">
        </div>

        <div>
            <label class="eda-label" for="mv-slug">Slug (fragmento de la URL)</label>
            <input id="mv-slug" type="text" name="slug" value="{{ old('slug', $video->slug) }}" maxlength="220" required class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300">
            <p class="mt-1 text-xs text-slate-500">Solo letras, números y guiones. Debe ser único en toda la plataforma.</p>
        </div>

        <div>
            <label class="eda-label" for="mv-desc">Descripción</label>
            <textarea id="mv-desc" name="description" rows="4" maxlength="65535" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300">{{ old('description', $video->description) }}</textarea>
        </div>

        <div>
            <label class="eda-label" for="mv-video-url">URL del vídeo</label>
            <input id="mv-video-url" type="text" name="video_url" value="{{ old('video_url', $video->video_url) }}" maxlength="255" required class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300">
        </div>

        <div>
            <label class="eda-label" for="mv-preview-url">URL vista previa (opcional)</label>
            <input id="mv-preview-url" type="text" name="preview_url" value="{{ old('preview_url', $video->preview_url) }}" maxlength="255" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300">
        </div>

        <div>
            <label class="eda-label" for="mv-thumb-url">URL miniatura (opcional)</label>
            <input id="mv-thumb-url" type="text" name="thumbnail_url" value="{{ old('thumbnail_url', $video->thumbnail_url) }}" maxlength="255" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300">
        </div>

        <div>
            <label class="eda-label" for="mv-thumb-file">O subir miniatura (imagen)</label>
            <input id="mv-thumb-file" type="file" name="thumbnail_file" accept="image/jpeg,image/png,image/gif,image/webp" class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200">
            <p class="mt-1 text-xs text-slate-500">Si subís un archivo, sustituye la URL de miniatura.</p>
        </div>

        <div>
            <span class="eda-label">Categorías</span>
            <select name="category_ids[]" multiple size="6" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300">
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ in_array($cat->id, old('category_ids', $video->categories->pluck('id')->all()), true) ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Mantén pulsada Ctrl o Cmd para elegir varias.</p>
        </div>

        <div>
            <label class="eda-label" for="mv-tags">Hashtags</label>
            <input id="mv-tags" type="text" name="hashtags" value="{{ old('hashtags', $hashtagString) }}" maxlength="2000" placeholder="#humor, #gaming" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300">
            <p class="mt-1 text-xs text-slate-500">Separados por comas. Dejá vacío para quitar todos.</p>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="eda-btn-primary">Guardar cambios</button>
            <a href="{{ route('account.videos.index') }}" class="eda-btn-secondary inline-flex items-center justify-center">Cancelar</a>
        </div>
    </form>
</main>
@endsection
