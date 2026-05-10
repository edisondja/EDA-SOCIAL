<?php

namespace App\Services;

use App\Http\Controllers\SitemapController;

/**
 * Regenera los archivos del sitemap en public/ al final del ciclo HTTP (tras enviar la respuesta).
 */
final class SitemapRegenerator
{
    private static bool $scheduled = false;

    public static function afterContentMutation(): void
    {
        if (self::$scheduled) {
            return;
        }
        self::$scheduled = true;

        app()->terminating(function (): void {
            self::$scheduled = false;
            try {
                if (!is_dir(public_path()) || !is_writable(public_path())) {
                    return;
                }
                app(SitemapController::class)->writePublicSitemaps();
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
