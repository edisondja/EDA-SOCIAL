@php
    /** @var array<string, mixed> $st */
    $fmtB = function (int $b) {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $b;
        while ($v >= 1024 && $i < count($u) - 1) {
            $v /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $v : number_format($v, 2, ',', '.')) . ' ' . $u[$i];
    };
@endphp
@if(!empty($st['error']))
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" style="margin:12px 0;">{{ $st['error'] }}</div>
@elseif(!empty($st))
    <h3 class="mt-6 text-base font-bold text-slate-900">Almacenamiento de medios (público)</h3>
    <p class="hint-text" style="margin:6px 0 10px;">Suma de archivos bajo <code>storage/app/public</code> referenciados en vídeos y medios, más carpetas HLS y portadas generadas. Los duplicados por contenido usan huella del primer 64&nbsp;KiB + tamaño (muy baja colisión).</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:12px;">
        <div class="aspecto-card" style="margin:0;border-left:4px solid #6366f1;">
            <div class="aspecto-card-title">Imágenes (refs.)</div>
            <p class="text-lg font-bold">{{ $st['bytes_images_label'] ?? $fmtB((int) ($st['bytes_images'] ?? 0)) }}</p>
        </div>
        <div class="aspecto-card" style="margin:0;border-left:4px solid #d83a7c;">
            <div class="aspecto-card-title">Vídeo local (refs., sin HLS)</div>
            <p class="text-lg font-bold">{{ $st['bytes_videos_local_label'] ?? $fmtB((int) ($st['bytes_videos_local'] ?? 0)) }}</p>
        </div>
        <div class="aspecto-card" style="margin:0;border-left:4px solid #0d9488;">
            <div class="aspecto-card-title">Carpeta HLS (total)</div>
            <p class="text-lg font-bold">{{ $st['bytes_hls_folder_total_label'] ?? $fmtB((int) ($st['bytes_hls_folder_total'] ?? 0)) }}</p>
            <p class="hint-text" style="font-size:11px;">Incluye segmentos .ts</p>
        </div>
        <div class="aspecto-card" style="margin:0;border-left:4px solid #94a3b8;">
            <div class="aspecto-card-title">Portadas / previews</div>
            <p class="text-lg font-bold">{{ $st['bytes_generated_previews_label'] ?? $fmtB((int) ($st['bytes_generated_previews_folder'] ?? 0)) }}</p>
        </div>
        <div class="aspecto-card" style="margin:0;border-left:4px solid #1e293b;">
            <div class="aspecto-card-title">Total estimado</div>
            <p class="text-lg font-bold">{{ $st['bytes_grand_total_estimate_label'] ?? $fmtB((int) ($st['bytes_grand_total_estimate'] ?? 0)) }}</p>
            <p class="hint-text" style="font-size:11px;">{{ (int) ($st['referenced_files_count'] ?? 0) }} archivos únicos referenciados · {{ (int) ($st['missing_references'] ?? 0) }} refs. rotas</p>
        </div>
    </div>

    @foreach($st['alerts'] ?? [] as $al)
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900" style="margin:8px 0;" role="alert">
            <strong>Alerta ({{ $al['type'] ?? 'aviso' }}):</strong> {{ $al['message'] ?? '' }}
        </div>
    @endforeach

    @if(!empty($st['path_duplicates']))
        <div class="aspecto-card" style="margin:12px 0;">
            <div class="aspecto-card-title">Misma ruta en varias filas</div>
            <p class="hint-text">No duplican bytes en disco; revisá si querés unificar datos.</p>
            <ul class="hint-text" style="font-size:12px;max-height:160px;overflow:auto;">
                @foreach($st['path_duplicates'] as $pd)
                    <li><code>{{ $pd['relative'] ?? '' }}</code> — {{ count($pd['refs'] ?? []) }} refs.</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($st['content_duplicate_groups']))
        <div class="aspecto-card" style="margin:12px 0;">
            <div class="aspecto-card-title">Archivos repetidos (contenido)</div>
            <p class="hint-text">Elegí cuál conservar; el resto se borra y las URLs en BD apuntan al conservado.</p>
            @foreach($st['content_duplicate_groups'] as $g)
                <div style="margin:10px 0;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#fafafa;">
                    <p class="hint-text" style="margin:0 0 6px;"><strong>~{{ $fmtB((int) ($g['wasted_bytes'] ?? 0)) }}</strong> recuperables · {{ $fmtB((int) ($g['bytes_each'] ?? 0)) }} c/u</p>
                    <ul style="margin:0 0 8px;padding-left:18px;font-size:12px;">
                        @foreach($g['paths'] ?? [] as $p)
                            <li style="word-break:break-all;"><code>{{ $p }}</code></li>
                        @endforeach
                    </ul>
                    @foreach($g['paths'] ?? [] as $keepPath)
                        <form method="post" action="{{ route('admin.storage.duplicate_content') }}" style="display:inline;margin-right:6px;margin-bottom:6px;" onsubmit="return confirm('¿Conservar {{ $keepPath }} y borrar las otras copias?');">
                            @csrf
                            <input type="hidden" name="fingerprint" value="{{ $g['fingerprint'] }}">
                            <input type="hidden" name="keep_relative" value="{{ $keepPath }}">
                            <button type="submit" class="btn-secondary" style="font-size:11px;">Conservar esta: {{ \Illuminate\Support\Str::limit($keepPath, 42) }}</button>
                        </form>
                    @endforeach
                </div>
            @endforeach
            @if(($st['content_duplicate_groups_count'] ?? 0) > count($st['content_duplicate_groups']))
                <p class="hint-text">… y {{ (int) $st['content_duplicate_groups_count'] - count($st['content_duplicate_groups']) }} grupos más (refrescá tras limpiar).</p>
            @endif
        </div>
    @endif

    @if(!empty($st['orphan_files']))
        <div class="aspecto-card" style="margin:12px 0;">
            <div class="aspecto-card-title">Huérfanos (no en BD)</div>
            <p class="hint-text">~{{ $fmtB((int) ($st['orphan_bytes_total'] ?? 0)) }} en {{ (int) ($st['orphan_files_count'] ?? 0) }} archivos listados (tope de escaneo).</p>
            @if(!empty($st['orphan_scan_capped']))
                <p class="hint-text" style="font-size:11px;color:#b45309;">El recorrido de carpetas se detuvo antes de terminar (límite de seguridad). La lista de huérfanos puede ser incompleta.</p>
            @endif
            <form method="post" action="{{ route('admin.storage.orphans') }}" onsubmit="return confirm('¿Eliminar los huérfanos marcados del disco?');">
                @csrf
                @foreach($st['orphan_files'] as $of)
                    <label class="flex items-start gap-2 hint-text" style="font-size:12px;margin:4px 0;">
                        <input type="checkbox" name="relative_paths[]" value="{{ $of['relative'] ?? '' }}">
                        <span><code>{{ $of['relative'] ?? '' }}</code> ({{ $fmtB((int) ($of['bytes'] ?? 0)) }})</span>
                    </label>
                @endforeach
                <button type="submit" class="btn-secondary label-with-icon mt-2" style="margin-top:8px;">@include('web.partials.form-icon', ['name' => 'no-symbol', 'size' => 14]) Eliminar seleccionados</button>
            </form>
        </div>
    @endif

    @if(!empty($st['hls_redundant_rows']))
        <div class="aspecto-card" style="margin:12px 0;">
            <div class="aspecto-card-title">HLS activo: borrar MP4/WebM de respaldo</div>
            <p class="hint-text">Si la reproducción principal ya es <code>.m3u8</code> bajo <code>hls/</code>, podés quitar clips MP4 locales en preview o medios para ahorrar espacio (no toca miniaturas ni el HLS).</p>
            @foreach($st['hls_redundant_rows'] as $row)
                <div style="margin:8px 0;padding:8px;border:1px solid #e2e8f0;border-radius:8px;">
                    <p style="margin:0;font-size:13px;"><strong>#{{ $row['video_id'] ?? '' }}</strong> {{ \Illuminate\Support\Str::limit($row['title'] ?? '', 60) }} — ~{{ $fmtB((int) ($row['recoverable_bytes'] ?? 0)) }}</p>
                    <ul class="hint-text" style="font-size:11px;margin:4px 0;">
                        @foreach($row['rows'] ?? [] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <form method="post" action="{{ route('admin.storage.hls_purge') }}" style="display:inline;" onsubmit="return confirm('¿Borrar archivos de vídeo de respaldo del vídeo #{{ $row['video_id'] ?? '' }}?');">
                        @csrf
                        <input type="hidden" name="video_id" value="{{ $row['video_id'] ?? '' }}">
                        <button type="submit" class="btn-secondary" style="font-size:11px;">Liberar origen (este vídeo)</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
@else
    <p class="hint-text text-slate-600" style="margin:12px 0;">Sin datos de almacenamiento.</p>
@endif
