<?php

namespace App\Services;

use App\Support\PublicDiskMediaPathResolver;
use App\Video;
use App\VideoMedia;
use Illuminate\Support\Facades\Storage;

/**
 * Auditoría de espacio en disco público (storage/app/public) para el panel Monitoreo:
 * totales por tipo, duplicados por ruta o por contenido, huérfanos, limpieza de MP4 si ya hay HLS.
 */
final class AdminStorageAuditService
{
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg'];

    private const VIDEO_EXT = ['mp4', 'webm', 'mov', 'm4v', 'mkv', 'avi', 'ogv', 'ts'];

    /** Máx. archivos a hashear para duplicados por contenido (evita timeouts). */
    private const MAX_FILES_FOR_CONTENT_HASH = 400;

    /** Máx. huérfanos a listar. */
    private const MAX_ORPHAN_FILES = 500;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $disk = Storage::disk('public');
        $refsByRel = [];
        $this->collectVideoRefs($refsByRel);

        $bytesByRel = [];
        $missing = 0;
        foreach (array_keys($refsByRel) as $rel) {
            $rel = $this->normalizeRel($rel);
            if (!$disk->exists($rel)) {
                $missing++;
                continue;
            }
            $abs = $disk->path($rel);
            $sz = @filesize($abs);
            if ($sz === false || $sz < 0) {
                continue;
            }
            $bytesByRel[$rel] = (int) $sz;
        }

        $bytesImages = 0;
        $bytesVideos = 0;
        $bytesHlsReferenced = 0;
        foreach ($bytesByRel as $rel => $sz) {
            $cat = $this->classifyPath($rel);
            if ($cat === 'hls') {
                $bytesHlsReferenced += $sz;
            } elseif ($cat === 'image') {
                $bytesImages += $sz;
            } elseif ($cat === 'video') {
                $bytesVideos += $sz;
            }
        }

        $bytesHlsFolder = $this->directoryTotalBytes($disk, 'hls');
        $bytesGeneratedPreviews = $this->directoryTotalBytes($disk, 'generated-previews');

        $pathDupes = [];
        foreach ($refsByRel as $rel => $refs) {
            $rel = $this->normalizeRel($rel);
            if (count($refs) > 1 && isset($bytesByRel[$rel])) {
                $pathDupes[] = [
                    'relative' => $rel,
                    'bytes' => $bytesByRel[$rel],
                    'refs' => $refs,
                    'wasted_bytes' => $bytesByRel[$rel] * (count($refs) - 1),
                ];
            }
        }

        $contentGroups = $this->buildContentDuplicateGroups($disk, array_keys($bytesByRel), self::MAX_FILES_FOR_CONTENT_HASH);

        $referencedRels = array_map([$this, 'normalizeRel'], array_keys($refsByRel));
        $orphans = $this->findOrphanFiles($disk, $referencedRels);

        $hlsCleanup = $this->hlsRedundantLocalVideoRows();

        $fmt = fn (int $b): string => $this->formatBytes($b);

        return [
            'captured_at' => now()->toDateTimeString(),
            'referenced_files_count' => count($bytesByRel),
            'missing_references' => $missing,
            'bytes_images' => $bytesImages,
            'bytes_images_label' => $fmt($bytesImages),
            'bytes_videos_local' => $bytesVideos,
            'bytes_videos_local_label' => $fmt($bytesVideos),
            'bytes_hls_referenced' => $bytesHlsReferenced,
            'bytes_hls_folder_total' => $bytesHlsFolder,
            'bytes_hls_folder_total_label' => $fmt($bytesHlsFolder),
            'bytes_generated_previews_folder' => $bytesGeneratedPreviews,
            'bytes_generated_previews_label' => $fmt($bytesGeneratedPreviews),
            'bytes_total_unique_referenced' => array_sum($bytesByRel),
            'bytes_total_unique_referenced_label' => $fmt((int) array_sum($bytesByRel)),
            'bytes_grand_total_estimate' => $bytesImages + $bytesVideos + $bytesHlsFolder + $bytesGeneratedPreviews,
            'bytes_grand_total_estimate_label' => $fmt($bytesImages + $bytesVideos + $bytesHlsFolder + $bytesGeneratedPreviews),
            'path_duplicates' => array_values(array_slice($pathDupes, 0, 40)),
            'path_duplicates_count' => count($pathDupes),
            'content_duplicate_groups' => array_slice($contentGroups, 0, 25),
            'content_duplicate_groups_count' => count($contentGroups),
            'orphan_files' => array_slice($orphans, 0, 40),
            'orphan_files_count' => count($orphans),
            'orphan_bytes_total' => array_sum(array_column($orphans, 'bytes')),
            'hls_redundant_rows' => array_slice($hlsCleanup, 0, 30),
            'hls_redundant_rows_count' => count($hlsCleanup),
            'alerts' => $this->buildAlerts($pathDupes, $contentGroups, $orphans, $hlsCleanup),
        ];
    }

    /**
     * Elimina archivos duplicados por contenido, conservando uno. Actualiza URLs en BD al `keep_relative`.
     *
     * @return array{deleted: list<string>, updated_rows: int, errors: list<string>}
     */
    public function deleteContentDuplicatesKeep(string $fingerprint, string $keepRelative): array
    {
        $keepRelative = $this->normalizeRel($keepRelative);
        if (!$this->isSafePublicRelative($keepRelative)) {
            return ['deleted' => [], 'updated_rows' => 0, 'errors' => ['Ruta a conservar no válida.']];
        }
        $disk = Storage::disk('public');
        if (!$disk->exists($keepRelative)) {
            return ['deleted' => [], 'updated_rows' => 0, 'errors' => ['El archivo a conservar no existe: ' . $keepRelative]];
        }

        $refsByRel = [];
        $this->collectVideoRefs($refsByRel);
        $rels = [];
        foreach (array_keys($refsByRel) as $rel) {
            $rel = $this->normalizeRel((string) $rel);
            if ($disk->exists($rel)) {
                $rels[] = $rel;
            }
        }
        $groups = $this->buildContentDuplicateGroups($disk, $rels, PHP_INT_MAX);
        $group = null;
        foreach ($groups as $g) {
            if (($g['fingerprint'] ?? '') === $fingerprint) {
                $group = $g;
                break;
            }
        }
        if ($group === null) {
            return ['deleted' => [], 'updated_rows' => 0, 'errors' => ['Grupo de duplicados no encontrado (refrescá el monitoreo y reintentá).']];
        }

        $toDelete = [];
        foreach ($group['paths'] as $p) {
            $p = $this->normalizeRel((string) $p);
            if ($p !== '' && $p !== $keepRelative) {
                $toDelete[] = $p;
            }
        }

        $keepUrl = $disk->url($keepRelative);
        $updated = 0;
        $deleted = [];
        $errors = [];

        foreach ($toDelete as $rel) {
            if (!$this->isSafePublicRelative($rel)) {
                $errors[] = 'Ruta no permitida: ' . $rel;
                continue;
            }
            try {
                $oldUrls = $this->urlsMatchingRelative($rel);
                $updated += $this->replaceUrlsInDatabase($oldUrls, $keepUrl);
                if ($disk->exists($rel)) {
                    $disk->delete($rel);
                    $deleted[] = $rel;
                }
            } catch (\Throwable $e) {
                $errors[] = $rel . ': ' . $e->getMessage();
                report($e);
            }
        }

        return ['deleted' => $deleted, 'updated_rows' => $updated, 'errors' => $errors];
    }

    /**
     * Elimina archivos huérfanos (no referenciados en BD). Solo bajo storage/app/public.
     *
     * @param  list<string>  $relativePaths
     * @return array{deleted: list<string>, errors: list<string>}
     */
    public function deleteOrphanFiles(array $relativePaths): array
    {
        $disk = Storage::disk('public');
        $allRefs = [];
        $this->collectVideoRefs($allRefs);
        $referenced = array_fill_keys(array_map([$this, 'normalizeRel'], array_keys($allRefs)), true);

        $deleted = [];
        $errors = [];
        foreach ($relativePaths as $rel) {
            $rel = $this->normalizeRel((string) $rel);
            if (!$this->isSafePublicRelative($rel)) {
                $errors[] = 'Ruta no permitida: ' . $rel;
                continue;
            }
            if (isset($referenced[$rel])) {
                $errors[] = 'Todavía referenciado, no se borra: ' . $rel;
                continue;
            }
            if (!$disk->exists($rel)) {
                continue;
            }
            try {
                $disk->delete($rel);
                $deleted[] = $rel;
            } catch (\Throwable $e) {
                $errors[] = $rel . ': ' . $e->getMessage();
            }
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }

    /**
     * Para un vídeo cuya reproducción principal ya es HLS local, borra archivos de vídeo locales
     * adicionales (preview / filas video_media) para liberar espacio. No modifica thumbnail ni m3u8.
     *
     * @return array{deleted: list<string>, cleared_fields: list<string>, errors: list<string>}
     */
    public function purgeLocalVideoSourcesWhenHlsMain(int $videoId): array
    {
        $video = Video::query()->find($videoId);
        if (!$video) {
            return ['deleted' => [], 'cleared_fields' => [], 'errors' => ['Vídeo no encontrado.']];
        }

        $main = trim((string) $video->video_url);
        if ($main === '' || !preg_match('#/hls/.+\.m3u8(\?|$)#i', $main)) {
            return ['deleted' => [], 'cleared_fields' => [], 'errors' => ['La URL principal no es HLS local (.m3u8 bajo /hls/).']];
        }

        $disk = Storage::disk('public');
        $deleted = [];
        $cleared = [];
        $errors = [];

        $candidates = [];
        $pv = trim((string) $video->preview_url);
        if ($pv !== '' && $this->isLocalVideoExtension($pv)) {
            $candidates[] = ['type' => 'preview_url', 'url' => $pv];
        }
        $video->loadMissing('media');
        foreach ($video->media as $m) {
            if (($m->type ?? '') === 'video' && $this->isLocalVideoExtension((string) $m->url)) {
                $candidates[] = ['type' => 'video_media', 'id' => $m->id, 'url' => (string) $m->url];
            }
        }

        foreach ($candidates as $c) {
            $rel = PublicDiskMediaPathResolver::storedUrlToPublicRelative($c['url']);
            if ($rel === null || !$this->isSafePublicRelative($rel)) {
                continue;
            }
            if (str_contains(strtolower($rel), 'hls/')) {
                continue;
            }
            if (!$this->isVideoExtensionPath($rel)) {
                continue;
            }
            try {
                if ($disk->exists($rel)) {
                    $disk->delete($rel);
                    $deleted[] = $rel;
                }
                if ($c['type'] === 'preview_url') {
                    $video->preview_url = null;
                    $cleared[] = 'preview_url';
                } elseif ($c['type'] === 'video_media' && isset($c['id'])) {
                    VideoMedia::query()->where('id', $c['id'])->delete();
                    $cleared[] = 'video_media:' . $c['id'];
                }
            } catch (\Throwable $e) {
                $errors[] = (string) ($c['url'] ?? '') . ': ' . $e->getMessage();
            }
        }

        if (in_array('preview_url', $cleared, true)) {
            $video->save();
        }

        return ['deleted' => $deleted, 'cleared_fields' => $cleared, 'errors' => $errors];
    }

    /**
     * @param  array<string, list<array{video_id:int, field:string, detail?:string}>>  $refsByRel
     */
    private function collectVideoRefs(array &$refsByRel): void
    {
        Video::query()
            ->select(['id', 'video_url', 'preview_url', 'thumbnail_url'])
            ->orderBy('id')
            ->chunkById(200, function ($videos) use (&$refsByRel): void {
                foreach ($videos as $v) {
                    $this->pushRef($refsByRel, (string) $v->video_url, (int) $v->id, 'video_url');
                    $this->pushRef($refsByRel, (string) $v->preview_url, (int) $v->id, 'preview_url');
                    $this->pushRef($refsByRel, (string) $v->thumbnail_url, (int) $v->id, 'thumbnail_url');
                }
            });

        VideoMedia::query()
            ->select(['id', 'video_id', 'type', 'url'])
            ->orderBy('id')
            ->chunkById(300, function ($rows) use (&$refsByRel): void {
                foreach ($rows as $row) {
                    $this->pushRef(
                        $refsByRel,
                        (string) $row->url,
                        (int) $row->video_id,
                        'video_media:' . $row->type . ':' . $row->id
                    );
                }
            });
    }

    /**
     * @param  array<string, list<array{video_id:int, field:string, detail?:string}>>  $refsByRel
     */
    private function pushRef(array &$refsByRel, string $url, int $videoId, string $field): void
    {
        $rel = PublicDiskMediaPathResolver::storedUrlToPublicRelative($url);
        if ($rel === null) {
            return;
        }
        $rel = $this->normalizeRel($rel);
        if (!isset($refsByRel[$rel])) {
            $refsByRel[$rel] = [];
        }
        $refsByRel[$rel][] = ['video_id' => $videoId, 'field' => $field];
    }

    private function normalizeRel(string $rel): string
    {
        return trim(str_replace('\\', '/', $rel), '/');
    }

    private function isSafePublicRelative(string $rel): bool
    {
        $rel = $this->normalizeRel($rel);
        if ($rel === '' || str_contains($rel, '..')) {
            return false;
        }

        return true;
    }

    private function classifyPath(string $rel): string
    {
        $rel = strtolower($rel);
        if (str_starts_with($rel, 'hls/')) {
            return 'hls';
        }
        $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));

        if (in_array($ext, self::IMAGE_EXT, true)) {
            return 'image';
        }
        if (in_array($ext, self::VIDEO_EXT, true)) {
            return 'video';
        }
        if ($ext === 'm3u8') {
            return 'hls';
        }

        return 'other';
    }

    private function isVideoExtensionPath(string $rel): bool
    {
        $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));

        return in_array($ext, self::VIDEO_EXT, true);
    }

    private function isLocalVideoExtension(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: $url));

        return (bool) preg_match('/\.(mp4|webm|mov|m4v|mkv|avi|ogv)(\?.*)?$/i', $path);
    }

    /**
     * @param  list<string>  $relativePaths
     * @return list<array{fingerprint: string, bytes_each: int, paths: list<string>, wasted_bytes: int}>
     */
    private function buildContentDuplicateGroups($disk, array $relativePaths, int $maxFiles): array
    {
        $paths = array_values(array_unique(array_map([$this, 'normalizeRel'], $relativePaths)));
        sort($paths);
        if ($maxFiles < PHP_INT_MAX) {
            $paths = array_slice($paths, 0, $maxFiles);
        }

        $byFp = [];
        foreach ($paths as $rel) {
            if (!$disk->exists($rel)) {
                continue;
            }
            $abs = $disk->path($rel);
            $sz = @filesize($abs);
            if ($sz === false || $sz < 128) {
                continue;
            }
            $fp = $this->quickFingerprint($abs, (int) $sz);
            if (!isset($byFp[$fp])) {
                $byFp[$fp] = [];
            }
            $byFp[$fp][] = $rel;
        }

        $out = [];
        foreach ($byFp as $fp => $list) {
            $list = array_values(array_unique($list));
            if (count($list) < 2) {
                continue;
            }
            $bytes = 0;
            foreach ($list as $one) {
                $p = $disk->path($one);
                $b = @filesize($p);
                if ($b !== false) {
                    $bytes = max($bytes, (int) $b);
                }
            }
            $wasted = $bytes * (count($list) - 1);
            $out[] = [
                'fingerprint' => $fp,
                'bytes_each' => $bytes,
                'paths' => $list,
                'wasted_bytes' => $wasted,
            ];
        }

        usort($out, fn ($a, $b) => ($b['wasted_bytes'] ?? 0) <=> ($a['wasted_bytes'] ?? 0));

        return $out;
    }

    private function quickFingerprint(string $absolutePath, int $size): string
    {
        $h = @fopen($absolutePath, 'rb');
        if ($h === false) {
            return 'x:' . $size;
        }
        $chunk = fread($h, 65536);
        fclose($h);
        $chunk = $chunk === false ? '' : $chunk;

        return hash('sha256', $chunk . '|len=' . $size) . ':' . $size;
    }

    /**
     * @param  list<string>  $referencedNormalized
     * @return list<array{relative: string, bytes: int}>
     */
    private function findOrphanFiles($disk, array $referencedNormalized): array
    {
        $refSet = [];
        foreach ($referencedNormalized as $k) {
            $refSet[$this->normalizeRel((string) $k)] = true;
        }

        $rootAbs = realpath($disk->path('.'));
        if ($rootAbs === false) {
            return [];
        }
        $rootNorm = rtrim(str_replace('\\', '/', $rootAbs), '/');

        $roots = ['uploads', 'videos', 'media', 'generated-previews'];
        $out = [];
        foreach ($roots as $root) {
            if (!$disk->exists($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($disk->path($root), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (count($out) >= self::MAX_ORPHAN_FILES) {
                    break 2;
                }
                if (!$file->isFile()) {
                    continue;
                }
                $fullNorm = str_replace('\\', '/', $file->getPathname());
                if (!str_starts_with($fullNorm, $rootNorm)) {
                    continue;
                }
                $rel = $this->normalizeRel(substr($fullNorm, strlen($rootNorm) + 1));
                if (isset($refSet[$rel])) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, array_merge(self::IMAGE_EXT, self::VIDEO_EXT), true)) {
                    continue;
                }
                $sz = $file->getSize();
                $out[] = ['relative' => $rel, 'bytes' => (int) $sz];
            }
        }

        usort($out, fn ($a, $b) => ($b['bytes'] ?? 0) <=> ($a['bytes'] ?? 0));

        return $out;
    }

    /**
     * @return list<array{video_id: int, title: string, main: string, rows: list<string>, recoverable_bytes: int}>
     */
    private function hlsRedundantLocalVideoRows(): array
    {
        $out = [];
        $disk = Storage::disk('public');

        Video::query()
            ->select(['id', 'title', 'video_url', 'preview_url'])
            ->orderByDesc('id')
            ->chunkById(100, function ($videos) use (&$out, $disk): void {
                foreach ($videos as $v) {
                    $main = trim((string) $v->video_url);
                    if ($main === '' || !preg_match('#/hls/.+\.m3u8(\?|$)#i', $main)) {
                        continue;
                    }
                    $rows = [];
                    $bytes = 0;
                    $pv = trim((string) $v->preview_url);
                    if ($pv !== '' && $this->isLocalVideoExtension($pv)) {
                        $rel = PublicDiskMediaPathResolver::storedUrlToPublicRelative($pv);
                        if ($rel && $this->isSafePublicRelative($rel) && !str_contains(strtolower($rel), 'hls/') && $disk->exists($rel)) {
                            $sz = @filesize($disk->path($rel));
                            if ($sz !== false) {
                                $bytes += (int) $sz;
                            }
                            $rows[] = 'preview_url → ' . $rel;
                        }
                    }
                    foreach (VideoMedia::query()->where('video_id', $v->id)->where('type', 'video')->get(['id', 'url']) as $m) {
                        $u = trim((string) $m->url);
                        if ($u === '' || !$this->isLocalVideoExtension($u)) {
                            continue;
                        }
                        $rel = PublicDiskMediaPathResolver::storedUrlToPublicRelative($u);
                        if ($rel && $this->isSafePublicRelative($rel) && !str_contains(strtolower($rel), 'hls/') && $disk->exists($rel)) {
                            $sz = @filesize($disk->path($rel));
                            if ($sz !== false) {
                                $bytes += (int) $sz;
                            }
                            $rows[] = 'video_media#' . $m->id . ' → ' . $rel;
                        }
                    }
                    if ($rows !== []) {
                        $out[] = [
                            'video_id' => (int) $v->id,
                            'title' => (string) $v->title,
                            'main' => $main,
                            'rows' => $rows,
                            'recoverable_bytes' => $bytes,
                        ];
                    }
                }
            });

        usort($out, fn ($a, $b) => ($b['recoverable_bytes'] ?? 0) <=> ($a['recoverable_bytes'] ?? 0));

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $pathDupes
     * @param  list<array<string, mixed>>  $contentGroups
     * @param  list<array<string, mixed>>  $orphans
     * @param  list<array<string, mixed>>  $hlsRows
     * @return list<array{type: string, message: string}>
     */
    private function buildAlerts(array $pathDupes, array $contentGroups, array $orphans, array $hlsRows): array
    {
        $alerts = [];
        if (count($pathDupes) > 0) {
            $w = array_sum(array_column($pathDupes, 'wasted_bytes'));
            $alerts[] = [
                'type' => 'path_duplicate',
                'message' => 'Mismo archivo referenciado en varias filas (' . count($pathDupes) . ' rutas; ~' . $this->formatBytes($w) . ' contados extra en referencias). No duplica disco, pero conviene revisar consistencia.',
            ];
        }
        if (count($contentGroups) > 0) {
            $w = array_sum(array_column($contentGroups, 'wasted_bytes'));
            $alerts[] = [
                'type' => 'content_duplicate',
                'message' => 'Archivos distintos con el mismo contenido (aprox.): ' . count($contentGroups) . ' grupos, espacio recuperable estimado ~' . $this->formatBytes($w) . '.',
            ];
        }
        if (count($orphans) > 0) {
            $b = array_sum(array_column($orphans, 'bytes'));
            $alerts[] = [
                'type' => 'orphan',
                'message' => 'Posibles archivos huérfanos (imagen/vídeo en disco no referenciados en BD): ' . count($orphans) . ' (~' . $this->formatBytes($b) . ').',
            ];
        }
        if (count($hlsRows) > 0) {
            $b = array_sum(array_column($hlsRows, 'recoverable_bytes'));
            $alerts[] = [
                'type' => 'hls_source',
                'message' => 'Publicaciones con reproducción HLS que aún tienen MP4/WebM local en preview o medios: ' . count($hlsRows) . ' (~' . $this->formatBytes($b) . ' recuperables si borrás el origen).',
            ];
        }

        return $alerts;
    }

    public function formatBytes(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($u) - 1) {
            $v /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $v : number_format($v, 2, ',', '.')) . ' ' . $u[$i];
    }

    private function directoryTotalBytes($disk, string $relativeDir): int
    {
        $relativeDir = $this->normalizeRel($relativeDir);
        if ($relativeDir === '' || !$disk->exists($relativeDir)) {
            return 0;
        }
        $total = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($disk->path($relativeDir), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total += (int) $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    private function urlsMatchingRelative(string $relative): array
    {
        $relative = $this->normalizeRel($relative);
        $disk = Storage::disk('public');
        $urls = array_filter([
            $disk->url($relative),
            '/storage/' . $relative,
            'storage/' . $relative,
        ]);

        $app = rtrim((string) config('app.url'), '/');
        $urls[] = $app . '/storage/' . $relative;

        return array_values(array_unique($urls));
    }

    /**
     * @param  list<string>  $fromUrls
     */
    private function replaceUrlsInDatabase(array $fromUrls, string $toUrl): int
    {
        $updated = 0;
        $fromList = array_values(array_unique($fromUrls));

        foreach ($fromList as $from) {
            $updated += Video::query()->where('video_url', $from)->update(['video_url' => $toUrl]);
            $updated += Video::query()->where('preview_url', $from)->update(['preview_url' => $toUrl]);
            $updated += Video::query()->where('thumbnail_url', $from)->update(['thumbnail_url' => $toUrl]);
            $updated += VideoMedia::query()->where('url', $from)->update(['url' => $toUrl]);
        }

        return $updated;
    }
}
