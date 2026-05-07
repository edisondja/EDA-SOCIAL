<?php

namespace App\Support;

/**
 * Convierte URLs de multimedia guardadas en BD para el navegador.
 * Si la URL absoluta es del mismo host que sirve la página (p. ej. APP_URL ≠ navegador),
 * se usa solo path + query para que cargue bien; si es otro dominio (CDN), se deja igual.
 */
class MediaSrc
{
    public static function web(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            if (isset($url[0]) && $url[0] === '/') {
                return $url;
            }

            return url($url);
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return $url;
        }

        $storedHost = strtolower((string) $parsed['host']);
        $appHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));
        $sameOrigin = $appHost !== '' && $storedHost === $appHost;

        if (!$sameOrigin && !app()->runningInConsole()) {
            try {
                $sameOrigin = $storedHost === strtolower(request()->getHost());
            } catch (\Throwable $e) {
                // ignorar (CLI, tests)
            }
        }

        if (!$sameOrigin) {
            return $url;
        }

        $path = isset($parsed['path']) && is_string($parsed['path']) ? $parsed['path'] : '';
        if ($path === '') {
            return $url;
        }

        $qs = isset($parsed['query']) && is_string($parsed['query']) ? $parsed['query'] : '';
        $frag = isset($parsed['fragment']) && is_string($parsed['fragment']) ? $parsed['fragment'] : '';

        $out = $path;
        if ($qs !== '') {
            $out .= '?' . $qs;
        }
        if ($frag !== '') {
            $out .= '#' . $frag;
        }

        return $out;
    }
}
