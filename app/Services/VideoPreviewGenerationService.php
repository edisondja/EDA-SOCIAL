<?php

namespace App\Services;

use App\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoPreviewGenerationService
{
    private const PREVIEW_SUBDIR = 'generated-previews';

    /**
     * Procesa videos que carecen de miniatura y/o vista previa (hover).
     *
     * @return array{processed:int, skipped:int, failed:int, messages:string[]}
     */
    public function processBatchMissing(int $limit): array
    {
        $limit = max(1, min(200, $limit));
        $ffmpeg = $this->resolveFfmpegBinary();
        if ($ffmpeg === null) {
            return [
                'processed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'messages' => ['No se encontró ffmpeg. Instalalo o definí FFMPEG_BINARY en .env.'],
            ];
        }

        $videos = Video::query()
            ->with(['media'])
            ->where(function ($w) {
                $w->where(function ($a) {
                    $a->whereNull('thumbnail_url')->orWhere('thumbnail_url', '');
                })->orWhere(function ($b) {
                    $b->whereNull('preview_url')->orWhere('preview_url', '');
                });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $messages = [];

        Storage::disk('public')->makeDirectory(self::PREVIEW_SUBDIR);

        foreach ($videos as $video) {
            $r = $this->generateForVideo($video, $ffmpeg);
            if ($r['status'] === 'ok') {
                $processed++;
                if (!empty($r['detail'])) {
                    $messages[] = "Vídeo #{$video->id}: {$r['detail']}";
                }
            } elseif ($r['status'] === 'skip') {
                $skipped++;
                $messages[] = "Vídeo #{$video->id}: {$r['detail']}";
            } else {
                $failed++;
                $messages[] = "Vídeo #{$video->id}: {$r['detail']}";
            }
        }

        return compact('processed', 'skipped', 'failed', 'messages');
    }

    /**
     * @return array{status:string,detail:string}
     */
    public function generateForVideo(Video $video, ?string $ffmpegBinary = null): array
    {
        $ffmpegBinary = $ffmpegBinary ?? $this->resolveFfmpegBinary();
        if ($ffmpegBinary === null) {
            return ['status' => 'fail', 'detail' => 'ffmpeg no disponible'];
        }

        $video->loadMissing('media');

        $needsPoster = trim((string) $video->thumbnail_url) === '';
        $needsPreview = trim((string) $video->preview_url) === '';

        if (!$needsPoster && !$needsPreview) {
            return ['status' => 'skip', 'detail' => 'Ya tiene miniatura y vista previa'];
        }

        $sourceUrl = $this->primaryVideoSourceUrl($video);
        if ($sourceUrl === null) {
            return ['status' => 'skip', 'detail' => 'Sin URL de vídeo local o en medios'];
        }

        $input = $this->resolveFfmpegInput($sourceUrl);
        if ($input === null) {
            return ['status' => 'skip', 'detail' => 'No se pudo resolver el archivo de origen'];
        }

        $disk = Storage::disk('public');
        $posterRel = self::PREVIEW_SUBDIR.'/'.$video->id.'_poster.jpg';
        $previewRel = self::PREVIEW_SUBDIR.'/'.$video->id.'_preview.mp4';

        $posterPath = $disk->path($posterRel);
        $previewPath = $disk->path($previewRel);

        $detailParts = [];

        $seek = (float) config('ffmpeg.poster_seek_seconds', 1);
        $seek = max(0, min(600, $seek));
        $dur = (int) config('ffmpeg.preview_duration_seconds', 12);
        $dur = max(3, min(120, $dur));
        $maxW = (int) config('ffmpeg.preview_max_width', 720);
        $maxW = max(320, min(1920, $maxW));

        if ($needsPoster) {
            $cmd = sprintf(
                '%s -y -hide_banner -loglevel error -ss %s -i %s -frames:v 1 -q:v 3 %s',
                escapeshellarg($ffmpegBinary),
                escapeshellarg((string) $seek),
                escapeshellarg($input),
                escapeshellarg($posterPath)
            );
            $code = $this->runShell($cmd);
            if ($code !== 0 || !is_readable($posterPath) || filesize($posterPath) < 32) {
                @unlink($posterPath);

                return ['status' => 'fail', 'detail' => 'Error al generar poster (ffmpeg código '.$code.')'];
            }
            $video->thumbnail_url = $disk->url($posterRel);
            $detailParts[] = 'poster';
        }

        if ($needsPreview) {
            $vf = sprintf("scale='min(%d,iw)':-2,fps=24", $maxW);
            $cmd = sprintf(
                '%s -y -hide_banner -loglevel error -ss 0 -i %s -t %d -vf %s -an -c:v libx264 -preset veryfast -crf 26 -pix_fmt yuv420p -movflags +faststart %s',
                escapeshellarg($ffmpegBinary),
                escapeshellarg($input),
                $dur,
                escapeshellarg($vf),
                escapeshellarg($previewPath)
            );
            $code = $this->runShell($cmd);
            if ($code !== 0 || !is_readable($previewPath) || filesize($previewPath) < 64) {
                @unlink($previewPath);
                if ($needsPoster && $video->thumbnail_url) {
                    $video->save();
                }

                return ['status' => 'fail', 'detail' => 'Error al generar clip de vista previa (¿libx264 instalado?)'];
            }
            $video->preview_url = $disk->url($previewRel);
            $detailParts[] = 'preview';
        }

        $video->save();

        return ['status' => 'ok', 'detail' => implode(' + ', $detailParts)];
    }

    /**
     * Genera solamente el poster si el vídeo aún no tiene miniatura.
     *
     * @return array{status:string,detail:string}
     */
    public function generatePosterIfMissing(Video $video, ?string $ffmpegBinary = null): array
    {
        $ffmpegBinary = $ffmpegBinary ?? $this->resolveFfmpegBinary();
        if ($ffmpegBinary === null) {
            return ['status' => 'fail', 'detail' => 'ffmpeg no disponible'];
        }

        $video->loadMissing('media');
        if (trim((string) $video->thumbnail_url) !== '') {
            return ['status' => 'skip', 'detail' => 'Ya tiene miniatura'];
        }

        $sourceUrl = $this->primaryVideoSourceUrl($video);
        if ($sourceUrl === null) {
            return ['status' => 'skip', 'detail' => 'Sin URL de vídeo local o en medios'];
        }
        $input = $this->resolveFfmpegInput($sourceUrl);
        if ($input === null) {
            return ['status' => 'skip', 'detail' => 'No se pudo resolver el archivo de origen'];
        }

        $disk = Storage::disk('public');
        $posterRel = self::PREVIEW_SUBDIR.'/'.$video->id.'_poster.jpg';
        $posterPath = $disk->path($posterRel);
        $seek = (float) config('ffmpeg.poster_seek_seconds', 1);
        $seek = max(0, min(600, $seek));

        $cmd = sprintf(
            '%s -y -hide_banner -loglevel error -ss %s -i %s -frames:v 1 -q:v 3 %s',
            escapeshellarg($ffmpegBinary),
            escapeshellarg((string) $seek),
            escapeshellarg($input),
            escapeshellarg($posterPath)
        );
        $code = $this->runShell($cmd);
        if ($code !== 0 || !is_readable($posterPath) || filesize($posterPath) < 32) {
            @unlink($posterPath);

            return ['status' => 'fail', 'detail' => 'Error al generar poster (ffmpeg código '.$code.')'];
        }

        $video->thumbnail_url = $disk->url($posterRel);
        $video->save();

        return ['status' => 'ok', 'detail' => 'poster'];
    }

    public function resolveFfmpegBinary(): ?string
    {
        $cfg = trim((string) config('ffmpeg.binary', 'ffmpeg'));
        if ($cfg !== '' && $cfg !== 'ffmpeg' && is_executable($cfg)) {
            return $cfg;
        }
        if ($cfg === 'ffmpeg' || $cfg === '') {
            $which = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
            if ($which !== '' && is_executable($which)) {
                return $which;
            }
        }

        return null;
    }

    /**
     * URL al archivo de vídeo principal (media o video_url).
     */
    public function primaryVideoSourceUrl(Video $video): ?string
    {
        foreach ($video->media->sortBy('position') as $m) {
            if ($m->type === 'video') {
                $u = trim((string) $m->url);
                if ($u !== '') {
                    return $u;
                }
            }
        }
        $u = trim((string) $video->video_url);

        return $u !== '' ? $video->video_url : null;
    }

    /**
     * Ruta local o URL http(s) consumible por ffmpeg.
     */
    public function resolveFfmpegInput(string $storedUrl): ?string
    {
        $storedUrl = trim($storedUrl);
        if ($storedUrl === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $storedUrl)) {
            $path = parse_url($storedUrl, PHP_URL_PATH);
            $host = parse_url($storedUrl, PHP_URL_HOST);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if (is_string($path) && $path !== '' && $host && $appHost && strcasecmp((string) $host, (string) $appHost) === 0) {
                $local = public_path(ltrim($path, '/'));
                if (is_readable($local)) {
                    return $local;
                }
                if (Str::startsWith(ltrim($path, '/'), 'storage/')) {
                    $inside = substr(ltrim($path, '/'), strlen('storage/'));
                    $full = Storage::disk('public')->path($inside);
                    if (is_readable($full)) {
                        return $full;
                    }
                }
            }

            return $storedUrl;
        }

        $rel = ltrim($storedUrl, '/');

        if (Str::startsWith($rel, 'storage/')) {
            $full = storage_path('app/public/'.substr($rel, strlen('storage/')));
            if (is_readable($full)) {
                return $full;
            }
        }

        $publicTry = public_path($rel);
        if (is_readable($publicTry)) {
            return $publicTry;
        }

        return null;
    }

    private function runShell(string $command): int
    {
        $output = [];
        $code = 0;
        @exec($command.' 2>&1', $output, $code);

        return (int) $code;
    }
}
