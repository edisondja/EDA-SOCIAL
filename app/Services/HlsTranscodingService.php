<?php

namespace App\Services;

use App\Support\PublicDiskMediaPathResolver;
use App\Video;
use Illuminate\Support\Facades\Storage;

class HlsTranscodingService
{
    public function isEnabled(): bool
    {
        return (bool) config('hls.enabled', false);
    }

    public function transcodeVideo(Video $video): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $ffmpeg = $this->resolveFfmpegBinary();
        if ($ffmpeg === null) {
            return false;
        }

        $video->loadMissing('media');

        $anyUpdated = false;
        foreach ($this->collectVideoUrls($video) as $url) {
            if (preg_match('/\.m3u8(\?.*)?$/i', $url)) {
                continue;
            }

            $srcRel = $this->resolvePublicDiskRelativePath($url);
            if ($srcRel === null) {
                continue;
            }

            $srcAbs = Storage::disk('public')->path($srcRel);
            if (!is_readable($srcAbs)) {
                continue;
            }

            $baseHash = substr(sha1($srcRel), 0, 12);
            $outDirRel = 'hls/' . $video->id . '/' . $baseHash;
            $playlistRel = $outDirRel . '/index.m3u8';
            $segmentTplAbs = Storage::disk('public')->path($outDirRel . '/segment_%03d.ts');
            $playlistAbs = Storage::disk('public')->path($playlistRel);

            Storage::disk('public')->makeDirectory($outDirRel);

            $segmentTime = max(2, min(20, (int) config('hls.segment_time', 6)));
            $crf = max(18, min(35, (int) config('hls.crf', 24)));
            $preset = (string) config('hls.preset', 'veryfast');

            $cmd = sprintf(
                '%s -y -hide_banner -loglevel error -i %s -c:v libx264 -preset %s -crf %d -c:a aac -b:a 128k -ac 2 -f hls -hls_time %d -hls_playlist_type vod -hls_segment_filename %s %s',
                escapeshellarg($ffmpeg),
                escapeshellarg($srcAbs),
                escapeshellarg($preset),
                $crf,
                $segmentTime,
                escapeshellarg($segmentTplAbs),
                escapeshellarg($playlistAbs)
            );
            $code = $this->runShell($cmd);
            if ($code !== 0 || !is_readable($playlistAbs) || filesize($playlistAbs) < 8) {
                continue;
            }

            $hlsUrl = Storage::disk('public')->url($playlistRel);
            $anyUpdated = $this->replaceUrlReferences($video, $url, $hlsUrl) || $anyUpdated;
            $this->maybeDeleteSource($video, $srcRel);
        }

        if ($anyUpdated) {
            $video->save();
        }

        return $anyUpdated;
    }

    private function collectVideoUrls(Video $video): array
    {
        $urls = [];
        foreach ($video->media as $item) {
            if (($item->type ?? '') === 'video' && !empty($item->url)) {
                $urls[] = trim((string) $item->url);
            }
        }
        if (!empty($video->video_url)) {
            $urls[] = trim((string) $video->video_url);
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function replaceUrlReferences(Video $video, string $oldUrl, string $newUrl): bool
    {
        $changed = false;
        if ((string) $video->video_url === (string) $oldUrl) {
            $video->video_url = $newUrl;
            $changed = true;
        }
        foreach ($video->media as $item) {
            if (($item->type ?? '') === 'video' && (string) $item->url === (string) $oldUrl) {
                $item->url = $newUrl;
                $item->save();
                $changed = true;
            }
        }

        return $changed;
    }

    private function maybeDeleteSource(Video $video, string $srcRel): void
    {
        if (!config('hls.delete_source_mp4', false)) {
            return;
        }

        // Evita borrar si preview_url apunta al mismo archivo.
        if ($video->preview_url) {
            $previewRel = $this->resolvePublicDiskRelativePath((string) $video->preview_url);
            if ($previewRel !== null && $previewRel === $srcRel) {
                return;
            }
        }

        Storage::disk('public')->delete($srcRel);
    }

    private function resolvePublicDiskRelativePath(string $storedUrl): ?string
    {
        return PublicDiskMediaPathResolver::storedUrlToPublicRelative($storedUrl);
    }

    private function resolveFfmpegBinary(): ?string
    {
        $cfg = trim((string) config('hls.ffmpeg_binary', 'ffmpeg'));
        if ($cfg !== '' && $cfg !== 'ffmpeg' && is_executable($cfg)) {
            return $cfg;
        }
        $which = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));

        return ($which !== '' && is_executable($which)) ? $which : null;
    }

    private function runShell(string $command): int
    {
        $output = [];
        $code = 0;
        @exec($command . ' 2>&1', $output, $code);

        return (int) $code;
    }
}

