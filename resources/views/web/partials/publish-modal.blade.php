<div
    id="blade-publish-modal"
    class="publish-page-shell publish-modal-root"
    role="dialog"
    aria-modal="true"
    aria-labelledby="blade-publish-modal-title"
    aria-hidden="true"
>
    <div class="publish-modal-backdrop" id="blade-publish-modal-backdrop" tabindex="-1"></div>
    <div class="publish-modal-dialog">
        <button type="button" class="publish-modal-close" id="blade-publish-modal-close" aria-label="Cerrar ventana de publicar">×</button>
        <div class="publish-modal-topbar" style="padding: 16px 48px 10px 18px;">
            <h2 id="blade-publish-modal-title" class="publish-page-title">Nueva publicación</h2>
            <p class="hint-text" style="margin: 6px 0 0;">Adjunta uno o varios archivos (imagen o video). El primer video define la URL principal si existe.</p>
        </div>
        <div class="publish-modal-main">
            <div class="login-card publish-form-card publish-modal-form-card" style="max-width: 720px; margin: 0 18px 18px;">
                <form method="post" action="{{ route('publish.store') }}" enctype="multipart/form-data" class="create-form" id="blade-publish-modal-form">
                    @csrf
                    <label class="field-label label-with-icon" for="blade-publish-title">@include('web.partials.form-icon', ['name' => 'pencil-square']) Título</label>
                    <input id="blade-publish-title" name="title" type="text" value="{{ old('title') }}" required maxlength="180">

                    <label class="field-label label-with-icon" for="blade-publish-description">@include('web.partials.form-icon', ['name' => 'document-text']) Descripción</label>
                    <textarea id="blade-publish-description" name="description" rows="3">{{ old('description') }}</textarea>

                    <label class="field-label label-with-icon" for="blade-publish-media">@include('web.partials.form-icon', ['name' => 'film']) Archivos</label>
                    <input id="blade-publish-media" name="media_files[]" type="file" accept="image/*,video/*" multiple required>
                    <p id="blade-publish-media-preview-hint" class="publish-media-preview-hint" hidden>Vista previa local (no se sube hasta que pulses Publicar).</p>
                    <div id="blade-publish-media-preview" class="publish-media-preview" role="region" aria-label="Vista previa de archivos seleccionados" hidden></div>

                    <label class="field-label label-with-icon" for="blade-publish-hashtags">@include('web.partials.form-icon', ['name' => 'hashtag']) Hashtags (separados por coma)</label>
                    <input id="blade-publish-hashtags" name="hashtags" type="text" value="{{ old('hashtags') }}" placeholder="musica, live">

                    <label class="field-label label-with-icon" for="blade-publish-categories">@include('web.partials.form-icon', ['name' => 'squares-2x2']) Categorías (Ctrl/Cmd + clic)</label>
                    <select id="blade-publish-categories" name="category_ids[]" multiple class="create-category-select" size="8">
                        @foreach($publishCategories ?? [] as $cat)
                            <option value="{{ $cat->id }}" {{ collect(old('category_ids', []))->contains($cat->id) ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn-primary label-with-icon create-submit-btn" style="margin-top:14px;">@include('web.partials.form-icon', ['name' => 'paper-airplane']) Publicar</button>
                </form>
            </div>
        </div>
    </div>
</div>
