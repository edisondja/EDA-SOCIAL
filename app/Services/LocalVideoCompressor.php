<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class LocalVideoCompressor
{
    public function isEnabled(): bool
    {
        if (!config('media.ffmpeg.enabled')) {
            return false;
        }

        $binary = (string) config('media.ffmpeg.binary', 'ffmpeg');
        $process = new Process([$binary, '-version']);
        $process->setTimeout(15);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Resuelve ruta absoluta en disco "public" a partir de URL guardada (/storage/... o URL absoluta del mismo host).
     */
    public function resolvePublicDiskPath(?string $url): ?string
    {
        if (!$url || !is_string($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            if (Str::startsWith($url, '/storage/')) {
                $path = $url;
            } else {
                return null;
            }
        }

        if (!Str::startsWith($path, '/storage/')) {
            return null;
        }

        $relative = ltrim(substr($path, strlen('/storage/')), '/');
        if ($relative === '') {
            return null;
        }

        $full = Storage::disk('public')->path($relative);

        return is_file($full) ? $full : null;
    }

    public function shouldTryCompress(string $absolutePath): bool
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'mov', 'webm', 'avi', 'mkv', 'm4v'], true)) {
            return false;
        }

        $min = (int) config('media.ffmpeg.min_bytes_to_process', 200000);
        $max = (int) config('media.ffmpeg.max_bytes_to_process', 500 * 1024 * 1024);
        $size = @filesize($absolutePath) ?: 0;

        return $size >= $min && $size <= $max;
    }

    /**
     * Comprime el archivo in-place si el resultado ocupa menos espacio. Devuelve true si hubo compresión aplicada.
     */
    public function compressInPlace(string $absoluteInputPath): bool
    {
        if (!$this->isEnabled() || !$this->shouldTryCompress($absoluteInputPath)) {
            return false;
        }

        $tmp = $absoluteInputPath . '.ffmpeg-tmp.mp4';
        @unlink($tmp);

        $ok = $this->runFfmpeg($absoluteInputPath, $tmp, true);
        if (!$ok) {
            $ok = $this->runFfmpeg($absoluteInputPath, $tmp, false);
        }

        if (!$ok || !is_file($tmp)) {
            @unlink($tmp);

            return false;
        }

        $inSize = (int) filesize($absoluteInputPath);
        $outSize = (int) filesize($tmp);
        if ($outSize <= 0 || $outSize >= (int) max(1, $inSize * 0.99)) {
            @unlink($tmp);

            return false;
        }

        try {
            File::delete($absoluteInputPath);
            File::move($tmp, $absoluteInputPath);
        } catch (\Throwable $e) {
            @unlink($tmp);
            Log::error('ffmpeg replace failed', ['path' => $absoluteInputPath, 'e' => $e->getMessage()]);

            return false;
        }

        Log::info('Video compressed with ffmpeg', [
            'path' => $absoluteInputPath,
            'bytes_before' => $inSize,
            'bytes_after' => $outSize,
        ]);

        return true;
    }

    private function runFfmpeg(string $input, string $output, bool $withAudio): bool
    {
        $binary = (string) config('media.ffmpeg.binary', 'ffmpeg');
        $crf = (int) config('media.ffmpeg.crf', 28);
        $preset = (string) config('media.ffmpeg.preset', 'medium');
        $maxW = (int) config('media.ffmpeg.max_width', 1280);
        $audioBr = (string) config('media.ffmpeg.audio_bitrate', '128k');

        $vf = sprintf("scale='min(%d,iw)':-2", $maxW);

        $cmd = [
            $binary,
            '-nostdin',
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-i',
            $input,
            '-vf',
            $vf,
            '-c:v',
            'libx264',
            '-crf',
            (string) $crf,
            '-preset',
            $preset,
            '-movflags',
            '+faststart',
        ];

        if ($withAudio) {
            $cmd[] = '-c:a';
            $cmd[] = 'aac';
            $cmd[] = '-b:a';
            $cmd[] = $audioBr;
        } else {
            $cmd[] = '-an';
        }

        $cmd[] = $output;

        $process = new Process($cmd);
        $process->setTimeout((int) config('media.ffmpeg.timeout', 900));
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning('ffmpeg run failed', [
                'with_audio' => $withAudio,
                'error' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);

            return false;
        }

        return true;
    }
}
