@php
    $ratingAvg = $ratingAvg ?? null;
    $ratingCount = (int) ($ratingCount ?? 0);
    $userRating = isset($userRating) ? (int) $userRating : null;
    $avgBars = ($ratingAvg !== null && $ratingCount > 0)
        ? min(5, max(0, (int) round((float) $ratingAvg)))
        : 0;
    $liveScore = ($userRating !== null && $userRating >= 1 && $userRating <= 5) ? $userRating : 0;
@endphp
<section
    id="valoracion"
    class="mt-6 rounded-2xl border border-sky-100/90 bg-gradient-to-br from-sky-50/70 via-white to-sky-50/40 px-5 py-5 shadow-soft ring-1 ring-sky-100/50 sm:px-6"
    aria-labelledby="eda-rating-heading"
>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <h2 id="eda-rating-heading" class="text-base font-bold tracking-tight text-slate-900">Ranking del video</h2>
        <p id="eda-rating-summary" class="text-sm text-slate-600">
            @if($ratingCount > 0 && $ratingAvg !== null)
                Media {{ number_format((float) $ratingAvg, 1, ',', '') }}/5
                · {{ $ratingCount }} {{ $ratingCount === 1 ? 'valoración' : 'valoraciones' }}
            @else
                Sin valoraciones todavía.
            @endif
        </p>
    </div>

    @guest
        <div class="mt-4 flex flex-wrap items-center gap-2.5">
            <div class="flex gap-1.5" role="img" aria-label="Media visual del ranking">
                @for($i = 1; $i <= 5; $i++)
                    <span class="eda-rating-dot {{ $i <= $avgBars ? 'eda-rating-dot--on' : '' }}"></span>
                @endfor
            </div>
            <p class="text-sm text-slate-600">
                <a href="{{ route('login') }}" class="text-link font-semibold">Iniciá sesión</a>
                para votar del 1 al 5.
            </p>
        </div>
    @endguest

    @auth
        <div
            id="eda-video-rating"
            class="mt-4 flex flex-wrap items-center gap-1.5 sm:gap-2"
            data-rating-url="{{ $video->ratingStoreUrl() }}"
            data-user-score="{{ $liveScore }}"
            role="group"
            aria-label="Tu puntuación de 1 a 5"
        >
            @for($i = 1; $i <= 5; $i++)
                <button
                    type="button"
                    class="eda-rating-dot-btn group inline-flex rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:ring-offset-1"
                    data-score="{{ $i }}"
                    aria-pressed="{{ $liveScore === $i ? 'true' : 'false' }}"
                    aria-label="Dar nota {{ $i }} de 5"
                >
                    <span class="eda-rating-dot transition duration-150 group-hover:scale-105 group-active:scale-95 {{ $i <= $liveScore ? 'eda-rating-dot--on' : '' }}"></span>
                </button>
            @endfor
        </div>
        <p id="eda-rating-feedback" class="mt-2 min-h-[1.25rem] text-xs font-medium text-sky-900/85" aria-live="polite"></p>
        <p class="text-xs text-slate-500">Tocá una bolita para votar (1–5). Podés cambiar tu nota cuando quieras.</p>

        @push('scripts')
        <script>
        (function () {
            var root = document.getElementById('eda-video-rating');
            if (!root) return;
            var url = root.getAttribute('data-rating-url');
            var summaryEl = document.getElementById('eda-rating-summary');
            var feedbackEl = document.getElementById('eda-rating-feedback');
            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
            var dots = root.querySelectorAll('.eda-rating-dot-btn');

            function paint(score) {
                var s = score > 0 ? score : 0;
                dots.forEach(function (btn) {
                    var n = parseInt(btn.getAttribute('data-score'), 10);
                    var inner = btn.querySelector('.eda-rating-dot');
                    if (inner) {
                        inner.classList.toggle('eda-rating-dot--on', n <= s);
                    }
                    btn.setAttribute('aria-pressed', s > 0 && n === s ? 'true' : 'false');
                });
            }

            function summaryText(avg, count) {
                if (!count || avg === null || avg === undefined) {
                    return 'Sin valoraciones todavía.';
                }
                var a = typeof avg === 'number' ? avg : parseFloat(avg);
                var txt = isNaN(a) ? '0,0' : a.toFixed(1).replace('.', ',');
                var lab = count === 1 ? 'valoración' : 'valoraciones';
                return 'Media ' + txt + '/5 · ' + count + ' ' + lab;
            }

            var initial = parseInt(root.getAttribute('data-user-score') || '0', 10) || 0;
            paint(initial);

            dots.forEach(function (btn) {
                btn.addEventListener('mouseenter', function () {
                    paint(parseInt(btn.getAttribute('data-score'), 10));
                });
                btn.addEventListener('mouseleave', function () {
                    var cur = parseInt(root.getAttribute('data-user-score') || '0', 10) || 0;
                    paint(cur);
                });
                btn.addEventListener('click', function () {
                    var score = parseInt(btn.getAttribute('data-score'), 10);
                    if (!url || !token) return;
                    if (feedbackEl) feedbackEl.textContent = 'Guardando…';
                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({ score: score })
                    })
                        .then(function (r) {
                            if (!r.ok) throw new Error('bad');
                            return r.json();
                        })
                        .then(function (data) {
                            if (!data || !data.ok) throw new Error('bad');
                            root.setAttribute('data-user-score', String(data.yourScore));
                            paint(data.yourScore);
                            if (summaryEl) {
                                summaryEl.textContent = summaryText(data.average, data.count);
                            }
                            if (feedbackEl) {
                                feedbackEl.textContent = '¡Listo! Tu nota quedó guardada.';
                                setTimeout(function () {
                                    if (feedbackEl && feedbackEl.textContent.indexOf('¡Listo!') === 0) {
                                        feedbackEl.textContent = '';
                                    }
                                }, 2800);
                            }
                        })
                        .catch(function () {
                            if (feedbackEl) feedbackEl.textContent = 'No se pudo guardar. Probá de nuevo.';
                        });
                });
            });
        })();
        </script>
        @endpush
    @endauth
</section>
