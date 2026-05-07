@php
    $encUrl = rawurlencode($shareUrl);
    $encTitle = rawurlencode($shareTitle);
    $waText = rawurlencode(trim($shareTitle . ' ' . $shareUrl));
    $mailSub = rawurlencode($shareTitle);
    $mailBody = rawurlencode($shareTitle . "\n\n" . $shareUrl);
@endphp
<div
    class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-soft sm:p-5"
    role="region"
    aria-label="Compartir en redes sociales"
    data-share-bar
    data-share-url="{{ $shareUrl }}"
    data-share-title="{{ e($shareTitle) }}"
>
    <span class="mb-3 block text-xs font-semibold uppercase tracking-wide text-slate-400">Compartir</span>
    <div class="flex flex-wrap gap-2">
        <a class="inline-flex items-center gap-2 rounded-full border border-[#1877f2]/25 bg-[#1877f2]/8 px-3 py-1.5 text-xs font-semibold text-[#1877f2] transition hover:bg-[#1877f2]/15" href="https://www.facebook.com/sharer/sharer.php?u={{ $encUrl }}" target="_blank" rel="noopener noreferrer">
            <span class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>' !!}</span>
            Facebook
        </a>
        <a class="inline-flex items-center gap-2 rounded-full border border-slate-800/20 bg-slate-900/[0.06] px-3 py-1.5 text-xs font-semibold text-slate-900 transition hover:bg-slate-900/10" href="https://twitter.com/intent/tweet?url={{ $encUrl }}&text={{ $encTitle }}" target="_blank" rel="noopener noreferrer">
            <span class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>' !!}</span>
            X
        </a>
        <a class="inline-flex items-center gap-2 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-500/15" href="https://wa.me/?text={{ $waText }}" target="_blank" rel="noopener noreferrer">
            <span class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>' !!}</span>
            WhatsApp
        </a>
        <a class="inline-flex items-center gap-2 rounded-full border border-sky-500/25 bg-sky-500/10 px-3 py-1.5 text-xs font-semibold text-sky-700 transition hover:bg-sky-500/15" href="https://t.me/share/url?url={{ $encUrl }}&text={{ $encTitle }}" target="_blank" rel="noopener noreferrer">
            <span class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>' !!}</span>
            Telegram
        </a>
        <a class="inline-flex items-center gap-2 rounded-full border border-blue-700/20 bg-blue-700/8 px-3 py-1.5 text-xs font-semibold text-blue-800 transition hover:bg-blue-700/12" href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encUrl }}" target="_blank" rel="noopener noreferrer">
            <span class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>' !!}</span>
            LinkedIn
        </a>
        <a class="inline-flex items-center gap-2 rounded-full border border-orange-500/25 bg-orange-500/10 px-3 py-1.5 text-xs font-semibold text-orange-800 transition hover:bg-orange-500/15" href="https://www.reddit.com/submit?url={{ $encUrl }}&title={{ $encTitle }}" target="_blank" rel="noopener noreferrer">
            <span class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.498l1.732-8.294h-.864l-.566 2.684h-.858l-.566-2.684h-.865l-.858 4.048h-.865l-.858-4.048h-.865l-.858 4.048h-.865z"/></svg>' !!}</span>
            Reddit
        </a>
        <a class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-white" href="mailto:?subject={{ $mailSub }}&body={{ $mailBody }}" rel="noopener noreferrer">
            <span class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>' !!}</span>
            Correo
        </a>
        <button type="button" class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition hover:opacity-90" data-share-copy style="color: var(--menu-color, #d83a7c); border: 1px solid color-mix(in srgb, var(--menu-color, #d83a7c) 35%, transparent); background-color: color-mix(in srgb, var(--menu-color, #d83a7c) 12%, white);">
            <span class="flex h-4 w-4 shrink-0 items-center justify-center text-current" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>' !!}</span>
            <span data-share-copy-label>Copiar enlace</span>
        </button>
        <button type="button" class="hidden inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50" data-share-native hidden>
            <span class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true">{!! '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>' !!}</span>
            Compartir…
        </button>
    </div>
</div>
<script>
(function () {
  var root = document.querySelector('[data-share-bar]');
  if (!root) return;
  var url = root.getAttribute('data-share-url') || '';
  var title = root.getAttribute('data-share-title') || '';
  var copyBtn = root.querySelector('[data-share-copy]');
  var copyLabel = root.querySelector('[data-share-copy-label]');
  if (copyBtn && url) {
    copyBtn.addEventListener('click', function () {
      function ok() {
        if (copyLabel) {
          var prev = copyLabel.textContent;
          copyLabel.textContent = '¡Copiado!';
          setTimeout(function () { copyLabel.textContent = prev; }, 2000);
        }
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(ok).catch(function () {});
      } else {
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand('copy');
          ok();
        } catch (e) {}
        document.body.removeChild(ta);
      }
    });
  }
  var nativeBtn = root.querySelector('[data-share-native]');
  if (nativeBtn && navigator.share) {
    nativeBtn.hidden = false;
    nativeBtn.classList.remove('hidden');
    nativeBtn.addEventListener('click', function () {
      navigator.share({ title: title, text: title, url: url }).catch(function () {});
    });
  }
})();
</script>
