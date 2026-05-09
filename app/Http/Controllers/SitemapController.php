<?php

namespace App\Http\Controllers;

use App\Support\PlatformConfig;
use App\Video;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function show(): Response
    {
        $lines = [];
        $this->eachSitemapLine(function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Escribe el XML al disco línea a línea (menos memoria que acumular todo el string en PHP).
     */
    public function writeToPath(string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el archivo para el sitemap: ' . $path);
        }

        try {
            $this->eachSitemapLine(function (string $line) use ($handle): void {
                fwrite($handle, $line . "\n");
            });
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  callable(string): void  $emit
     */
    private function eachSitemapLine(callable $emit): void
    {
        $base = rtrim((string) (PlatformConfig::get('public_site_url') ?: config('app.url')), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }
        $includeAllPosts = PlatformConfig::get('sitemap_include_all_posts', '1') === '1';

        $emit('<?xml version="1.0" encoding="UTF-8"?>');
        $emit('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        $emit($this->urlXml($base . '/explorar?page=1', now()));

        if ($includeAllPosts) {
            $lastPage = (int) ceil(Video::query()
                ->where('is_published', true)
                ->where('moderation_status', 'active')
                ->count() / 20);
            $lastPage = max(1, min($lastPage, 5000));
            for ($p = 2; $p <= $lastPage; $p += 1) {
                $emit($this->urlXml($base . '/explorar?page=' . $p, now()));
            }

            Video::query()
                ->where('is_published', true)
                ->where('moderation_status', 'active')
                ->orderByDesc('updated_at')
                ->select(['id', 'slug', 'title', 'updated_at'])
                ->chunkById(500, function ($videos) use ($emit) {
                    foreach ($videos as $video) {
                        $last = $video->updated_at instanceof Carbon ? $video->updated_at : now();
                        $emit($this->urlXml(route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()]), $last));
                    }
                });
        }

        $emit('</urlset>');
    }

    private function urlXml(string $loc, Carbon $lastmod): string
    {
        $locEsc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $when = $lastmod->toAtomString();

        return '<url><loc>' . $locEsc . '</loc><lastmod>' . $when . '</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>';
    }
}
