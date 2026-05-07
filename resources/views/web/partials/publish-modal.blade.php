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
        <div class="border-b border-slate-100 px-5 pb-4 pt-5 sm:px-10 sm:pb-5 sm:pt-8">
            <h2 id="blade-publish-modal-title" class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Nueva publicación</h2>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
                Adjuntá uno o varios archivos (imagen o video). El primer video define la URL principal si existe.
            </p>
        </div>
        <div class="publish-modal-main">
            <div class="login-card mx-auto max-w-3xl border-0 shadow-none">
                <form method="post" action="{{ route('publish.store') }}" enctype="multipart/form-data" class="create-form px-5 pb-6 pt-2 sm:px-10" id="blade-publish-modal-form">
                    @csrf
                    <label class="field-label label-with-icon" for="blade-publish-title">@include('web.partials.form-icon', ['name' => 'pencil-square']) Título</label>
                    <input id="blade-publish-title" name="title" type="text" value="{{ old('title') }}" required maxlength="180">

                    <label class="field-label label-with-icon" for="blade-publish-description">@include('web.partials.form-icon', ['name' => 'document-text']) Descripción</label>
                    <textarea id="blade-publish-description" name="description" rows="3">{{ old('description') }}</textarea>

                    <label class="field-label label-with-icon" for="blade-publish-media">@include('web.partials.form-icon', ['name' => 'film']) Archivos</label>
                    <input id="blade-publish-media" name="media_files[]" type="file" accept="image/*,video/*" multiple required>
                    <p id="blade-publish-media-preview-hint" class="mb-3 text-xs font-medium text-slate-500" hidden>Vista previa local (no se sube hasta que pulses Publicar).</p>
                    <div id="blade-publish-media-preview" class="publish-media-preview mb-4" role="region" aria-label="Vista previa de archivos seleccionados" hidden></div>

                    <label class="field-label label-with-icon" for="blade-publish-hashtags">@include('web.partials.form-icon', ['name' => 'hashtag']) Hashtags (separados por coma)</label>
                    <input id="blade-publish-hashtags" name="hashtags" type="text" value="{{ old('hashtags') }}" placeholder="musica, live">

                    <label id="blade-publish-categories-label" class="field-label label-with-icon">
                        @include('web.partials.form-icon', ['name' => 'squares-2x2']) Categorías
                    </label>
                    <p class="mb-3 text-xs leading-relaxed text-slate-500">
                        Cada categoría tiene un botón con <strong class="font-semibold text-slate-700">+</strong> para sumarla al video.
                        Al agregarla o quitarla sonará un efecto sonoro breve (activá el audio del dispositivo).
                    </p>
                    <div
                        id="blade-publish-category-chips"
                        class="publish-category-chips"
                        role="group"
                        aria-labelledby="blade-publish-categories-label"
                    >
                        @forelse($publishCategories ?? [] as $cat)
                            @php $catSelected = collect(old('category_ids', []))->contains($cat->id); @endphp
                            <button
                                type="button"
                                class="publish-cat-chip {{ $catSelected ? 'publish-cat-chip--active' : '' }}"
                                data-category-id="{{ $cat->id }}"
                                aria-pressed="{{ $catSelected ? 'true' : 'false' }}"
                                aria-label="{{ $catSelected ? 'Quitar categoría ' . $cat->name : 'Agregar categoría ' . $cat->name }}"
                            >
                                <span class="publish-cat-chip-label">{{ $cat->name }}</span>
                                <span class="publish-cat-chip-icon" aria-hidden="true">
                                    <span class="publish-cat-chip-plus-icon {{ $catSelected ? 'hidden' : 'inline-flex' }} items-center justify-center">
                                        @include('web.partials.form-icon', ['name' => 'plus', 'size' => 18])
                                    </span>
                                    <span class="publish-cat-chip-minus-icon {{ $catSelected ? 'inline-flex' : 'hidden' }} items-center justify-center">
                                        @include('web.partials.form-icon', ['name' => 'minus', 'size' => 18])
                                    </span>
                                </span>
                            </button>
                        @empty
                            <p class="text-sm text-slate-500">No hay categorías todavía. Un administrador puede crearlas desde el panel.</p>
                        @endforelse
                    </div>
                    <select id="blade-publish-categories" name="category_ids[]" multiple class="publish-category-sync-select" tabindex="-1" aria-hidden="true">
                        @foreach($publishCategories ?? [] as $cat)
                            <option value="{{ $cat->id }}" {{ collect(old('category_ids', []))->contains($cat->id) ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn-primary label-with-icon mt-4 w-full justify-center sm:w-auto">
                        @include('web.partials.form-icon', ['name' => 'paper-airplane']) Publicar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function wirePublishCategoryChips() {
        var sel = document.getElementById('blade-publish-categories');
        var chipsRoot = document.getElementById('blade-publish-category-chips');
        if (!sel || !chipsRoot) return;

        var audioCtx = null;

        function sfx(kind) {
            try {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                function playTone() {
                    if (!audioCtx) return;
                    var t = audioCtx.currentTime;
                    var osc = audioCtx.createOscillator();
                    var gain = audioCtx.createGain();
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    if (kind === 'add') {
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(659.25, t);
                        osc.frequency.exponentialRampToValueAtTime(987.77, t + 0.07);
                        gain.gain.setValueAtTime(0.0001, t);
                        gain.gain.linearRampToValueAtTime(0.11, t + 0.018);
                        gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.13);
                        osc.start(t);
                        osc.stop(t + 0.13);
                    } else {
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(349.23, t);
                        gain.gain.setValueAtTime(0.0001, t);
                        gain.gain.linearRampToValueAtTime(0.085, t + 0.012);
                        gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.09);
                        osc.start(t);
                        osc.stop(t + 0.09);
                    }
                }
                if (audioCtx.state === 'suspended') {
                    var p = audioCtx.resume();
                    if (p && typeof p.then === 'function') {
                        p.then(playTone).catch(playTone);
                    } else {
                        playTone();
                    }
                } else {
                    playTone();
                }
            } catch (e) {}
        }

        function findOption(val) {
            var s = String(val);
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === s) {
                    return sel.options[i];
                }
            }
            return null;
        }

        chipsRoot.querySelectorAll('.publish-cat-chip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-category-id');
                var opt = findOption(id);
                if (!opt) return;
                var nowSelected = !opt.selected;
                opt.selected = nowSelected;
                btn.setAttribute('aria-pressed', nowSelected ? 'true' : 'false');
                btn.classList.toggle('publish-cat-chip--active', nowSelected);
                var labelEl = btn.querySelector('.publish-cat-chip-label');
                var name = labelEl ? labelEl.textContent.trim() : '';
                btn.setAttribute(
                    'aria-label',
                    (nowSelected ? 'Quitar categoría ' : 'Agregar categoría ') + name
                );
                var plusIc = btn.querySelector('.publish-cat-chip-plus-icon');
                var minusIc = btn.querySelector('.publish-cat-chip-minus-icon');
                if (plusIc && minusIc) {
                    plusIc.classList.toggle('hidden', nowSelected);
                    plusIc.classList.toggle('inline-flex', !nowSelected);
                    minusIc.classList.toggle('hidden', !nowSelected);
                    minusIc.classList.toggle('inline-flex', nowSelected);
                }
                sfx(nowSelected ? 'add' : 'remove');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wirePublishCategoryChips);
    } else {
        wirePublishCategoryChips();
    }
})();
</script>
