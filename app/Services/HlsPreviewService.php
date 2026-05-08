<?php

namespace App\Services;

use App\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HlsPreviewService
{
    public function randomSegmentUrlForVideo(Video $video): ?string
    {
        $playlistUrl = $this->resolveHlsPlaylistFromVideo($video);
        if ($playlistUrl === null) {
            return null;
        }

        $cacheKey = 'hls:segments:' . sha1($playlistUrl);
        $segments = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($playlistUrl) {
            return $this->extractSegmentsFromPlaylist($playlistUrl);
        });

        if (empty($segments)) {
            return null;
        }

        return $segments[array_rand($segments)];
    }

    private function resolveHlsPlaylistFromVideo(Video $video): ?string
    {
        $video->loadMissing('media');

        $urls = [];
        foreach ($video->media as $item) {
            if (($item->type ?? '') === 'video' && !empty($item->url)) {
                $urls[] = trim((string) $item->url);
            }
        }
        if (!empty($video->video_url)) {
            $urls[] = trim((string) $video->video_url);
        }

        foreach (array_values(array_unique(array_filter($urls))) as $url) {
            if (preg_match('/\.m3u8(\?.*)?$/i', $url)) {
                return $url;
            }
        }

        return null;
    }

    private function extractSegmentsFromPlaylist(string $playlistUrl): array
    {
        $content = $this->readPlaylistContents($playlistUrl);
        if ($content === null) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $entries = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || Str::startsWith($line, '#')) {
                continue;
            }
            $entries[] = $line;
        }
        if (empty($entries)) {
            return [];
        }

        // Playlist maestro: toma el primer sub-playlist y extrae segmentos reales.
        if (preg_match('/\.m3u8(\?.*)?$/i', $entries[0])) {
            $nestedUrl = $this->resolveRelativeUrl($playlistUrl, $entries[0]);
            if ($nestedUrl === null) {
                return [];
            }

            return $this->extractSegmentsFromPlaylist($nestedUrl);
        }

        $segments = [];
        foreach ($entries as $entry) {
            $resolved = $this->resolveRelativeUrl($playlistUrl, $entry);
            if ($resolved !== null) {
                $segments[] = $resolved;
            }
        }

        return array_values(array_unique($segments));
    }

    private function readPlaylistContents(string $playlistUrl): ?string
    {
        $localPath = $this->resolvePublicStorageAbsolutePath($playlistUrl);
        if ($localPath !== null && is_readable($localPath)) {
            $raw = @file_get_contents($localPath);

            return is_string($raw) ? $raw : null;
        }

        return null;
    }

    private function resolvePublicStorageAbsolutePath(string $url): ?string
    {
        $path = '';
        if (preg_match('#^https?://#i', $url)) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($host === '' || $appHost === '' || strcasecmp($host, $appHost) !== 0) {
                return null;
            }
            $path = (string) parse_url($url, PHP_URL_PATH);
        } else {
            $path = $url;
        }

        $path = ltrim($path, '/');
        if (!Str::startsWith($path, 'storage/')) {
            return null;
        }

        $relative = ltrim(substr($path, strlen('storage/')), '/');

        return Storage::disk('public')->path($relative);
    }

    private function resolveRelativeUrl(string $baseUrl, string $entry): ?string
    {
        if (preg_match('#^https?://#i', $entry)) {
            return $entry;
        }
        if (strpos($entry, '/') === 0) {
            return rtrim((string) config('app.url'), '/') . $entry;
        }

        $basePath = (string) parse_url($baseUrl, PHP_URL_PATH);
        if ($basePath === '') {
            return null;
        }

        $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        if ($dir === '' || $dir === '.') {
            $dir = '';
        }

        return rtrim((string) config('app.url'), '/') . '/' . ltrim($dir . '/' . $entry, '/');
    }
}
