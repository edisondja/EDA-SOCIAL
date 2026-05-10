<?php

namespace App\Http\Controllers;

use App\Support\PlatformConfig;
use App\Video;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public const POSTS_PER_SITEMAP = 20;

    /**
     * Índice (varios sitemap-posts-N.xml) si hay publicaciones; si no, urlset vacío.
     */
    public function showIndex(): Response
    {
        $lines = [];
        $this->eachIndexLine(function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Un archivo de sitemap con hasta 20 URLs de posts (página 1-based).
     */
    public function showPostsChunk(int $page): Response
    {
        $total = $this->publishedPostsCount();
        $pages = $this->chunkPageCount($total);
        if ($pages === 0 || $page < 1 || $page > $pages) {
            abort(404);
        }

        $lines = [];
        $this->eachPostsChunkLine($page, function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Escribe sitemap.xml y todos los sitemap-posts-N.xml en public/, y borra trozos sobrantes.
     */
    public function writePublicSitemaps(): void
    {
        $publicDir = public_path();
        if (!is_dir($publicDir) || !is_writable($publicDir)) {
            throw new \RuntimeException('public/ no es escribible; no se puede escribir el sitemap.');
        }

        $this->pruneStaleChunkFiles($this->chunkPageCount($this->publishedPostsCount()));

        $indexPath = $publicDir . DIRECTORY_SEPARATOR . 'sitemap.xml';
        $this->writeLinesToPath($indexPath, function (callable $emit): void {
            $this->eachIndexLine($emit);
        });

        $total = $this->publishedPostsCount();
        $pages = $this->chunkPageCount($total);
        for ($p = 1; $p <= $pages; $p += 1) {
            $chunkPath = $publicDir . DIRECTORY_SEPARATOR . 'sitemap-posts-' . $p . '.xml';
            $this->writeLinesToPath($chunkPath, function (callable $emit) use ($p): void {
                $this->eachPostsChunkLine($p, $emit);
            });
        }
    }

    /**
     * @deprecated Usar writePublicSitemaps()
     */
    public function writeToPath(string $path): void
    {
        $this->writePublicSitemaps();
    }

    /**
     * @param  callable(string): void  $emit
     */
    private function eachIndexLine(callable $emit): void
    {
        $emit('<?xml version="1.0" encoding="UTF-8"?>');
        $total = $this->publishedPostsCount();
        if ($total === 0) {
            $emit('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
            $emit('</urlset>');

            return;
        }

        $base = $this->publicBaseUrl();
        $pages = $this->chunkPageCount($total);
        $emit('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
        for ($p = 1; $p <= $pages; $p += 1) {
            $loc = $base . '/sitemap-posts-' . $p . '.xml';
            $lastmod = $this->lastmodForChunkPage($p);
            $emit($this->sitemapIndexEntryXml($loc, $lastmod));
        }
        $emit('</sitemapindex>');
    }

    /**
     * @param  callable(string): void  $emit
     */
    private function eachPostsChunkLine(int $page, callable $emit): void
    {
        $emit('<?xml version="1.0" encoding="UTF-8"?>');
        $emit('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        $offset = ($page - 1) * self::POSTS_PER_SITEMAP;
        Video::query()
            ->where('is_published', true)
            ->where('moderation_status', 'active')
            ->orderBy('id')
            ->skip($offset)
            ->take(self::POSTS_PER_SITEMAP)
            ->select(['id', 'slug', 'title', 'updated_at'])
            ->each(function (Video $video) use ($emit): void {
                $last = $video->updated_at instanceof Carbon ? $video->updated_at : now();
                $emit($this->urlEntryXml(route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()]), $last));
            });

        $emit('</urlset>');
    }

    private function publishedPostsCount(): int
    {
        return (int) Video::query()
            ->where('is_published', true)
            ->where('moderation_status', 'active')
            ->count();
    }

    private function chunkPageCount(int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) ceil($total / self::POSTS_PER_SITEMAP);
    }

    private function lastmodForChunkPage(int $page): Carbon
    {
        $offset = ($page - 1) * self::POSTS_PER_SITEMAP;
        $dates = Video::query()
            ->where('is_published', true)
            ->where('moderation_status', 'active')
            ->orderBy('id')
            ->skip($offset)
            ->take(self::POSTS_PER_SITEMAP)
            ->pluck('updated_at');
        $max = $dates->max();

        if ($max instanceof Carbon) {
            return $max;
        }
        if (is_string($max) && $max !== '') {
            return Carbon::parse($max);
        }

        return now();
    }

    private function sitemapIndexEntryXml(string $loc, Carbon $lastmod): string
    {
        $locEsc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $when = $lastmod->toAtomString();

        return '<sitemap><loc>' . $locEsc . '</loc><lastmod>' . $when . '</lastmod></sitemap>';
    }

    private function urlEntryXml(string $loc, Carbon $lastmod): string
    {
        $locEsc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $when = $lastmod->toAtomString();

        return '<url><loc>' . $locEsc . '</loc><lastmod>' . $when . '</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>';
    }

    private function publicBaseUrl(): string
    {
        $base = rtrim((string) (PlatformConfig::get('public_site_url') ?: config('app.url')), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        return $base;
    }

    /**
     * @param  callable(callable(string): void): void  $writer
     */
    private function writeLinesToPath(string $path, callable $writer): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el archivo para el sitemap: ' . $path);
        }

        try {
            $writer(function (string $line) use ($handle): void {
                fwrite($handle, $line . "\n");
            });
        } finally {
            fclose($handle);
        }
    }

    private function pruneStaleChunkFiles(int $keepPages): void
    {
        $pattern = public_path('sitemap-posts-*.xml');
        foreach (glob($pattern) ?: [] as $file) {
            $base = basename((string) $file);
            if (!preg_match('/^sitemap-posts-(\d+)\.xml$/', $base, $m)) {
                continue;
            }
            $n = (int) $m[1];
            if ($n > $keepPages) {
                @unlink($file);
            }
        }
    }
}
