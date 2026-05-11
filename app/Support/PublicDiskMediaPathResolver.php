<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Convierte URLs guardadas en BD a ruta relativa del disco `public` de Laravel.
 * Misma regla que {@see \App\Services\HlsTranscodingService} (solo mismo host / storage/).
 */
final class PublicDiskMediaPathResolver
{
    public static function storedUrlToPublicRelative(?string $storedUrl): ?string
    {
        $storedUrl = trim((string) $storedUrl);
        if ($storedUrl === '') {
            return null;
        }

        $path = '';
        if (preg_match('#^https?://#i', $storedUrl)) {
            $host = (string) (parse_url($storedUrl, PHP_URL_HOST) ?: '');
            $appHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: '');
            $reqHost = '';
            try {
                $reqHost = (string) request()->getHost();
            } catch (\Throwable $e) {
                $reqHost = '';
            }
            if ($host === '' || !in_array(strtolower($host), array_filter([strtolower($appHost), strtolower($reqHost)]), true)) {
                return null;
            }
            $path = (string) (parse_url($storedUrl, PHP_URL_PATH) ?: '');
        } else {
            $path = $storedUrl;
        }

        $path = ltrim($path, '/');
        if (!Str::startsWith($path, 'storage/')) {
            return null;
        }

        return ltrim(substr($path, strlen('storage/')), '/');
    }
}
