@extends('web.layout')

@section('title', 'Publicar')

@section('content')
<main class="login-card publish-form-card" style="max-width:720px;margin:20px auto;">
    <h1>Nueva publicación</h1>
    <p class="hint-text">Adjunta uno o varios archivos (imagen o video). El primer video define la URL principal si existe.</p>
    <form method="post" action="{{ route('publish.store') }}" enctype="multipart/form-data" class="create-form">
        @csrf
        <label class="field-label label-with-icon" for="title">@include('web.partials.form-icon', ['name' => 'pencil-square']) Título</label>
        <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="180">

        <label class="field-label label-with-icon" for="description">@include('web.partials.form-icon', ['name' => 'document-text']) Descripción</label>
        <textarea id="description" name="description" rows="3">{{ old('description') }}</textarea>

        <label class="field-label label-with-icon" for="media_files">@include('web.partials.form-icon', ['name' => 'film']) Archivos</label>
        <input id="media_files" name="media_files[]" type="file" accept="image/*,video/*" multiple required>

        <label class="field-label label-with-icon" for="hashtags">@include('web.partials.form-icon', ['name' => 'hashtag']) Hashtags (separados por coma)</label>
        <input id="hashtags" name="hashtags" type="text" value="{{ old('hashtags') }}" placeholder="musica, live">

        <label class="field-label label-with-icon" for="category_ids">@include('web.partials.form-icon', ['name' => 'squares-2x2']) Categorías (Ctrl/Cmd + clic)</label>
        <select id="category_ids" name="category_ids[]" multiple class="create-category-select" size="8">
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ collect(old('category_ids', []))->contains($cat->id) ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn-primary label-with-icon create-submit-btn" style="margin-top:14px;">@include('web.partials.form-icon', ['name' => 'paper-airplane']) Publicar</button>
    </form>
</main>
@endsection
