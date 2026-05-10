<?php

namespace App\Http\Controllers;

use App\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SitemapController extends Controller
{
    /**
     * Límite de URLs por sitemap según el protocolo (Google, etc.).
     * Si superás esto, habría que volver a un índice con varios urlset.
     */
    public const MAX_URLS_PER_FILE = 50000;

    /**
     * Un solo sitemap: todas las fichas públicas `/playvideo/{id}/{slug}`.
     */
    public function showIndex(): StreamedResponse
    {
        return response()->stream(function (): void {
            $this->emitFullUrlset(function (string $line): void {
                echo $line . "\n";
            });
        }, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Compatibilidad con despliegues antiguos que usaban sitemap-posts-N.xml.
     */
    public function showPostsChunk(): RedirectResponse
    {
        return redirect()->to(url('/sitemap.xml'), 301);
    }

    /**
     * Escribe únicamente `public/sitemap.xml` con todas las URLs y borra `sitemap-posts-*.xml` heredados.
     *
     * @throws \RuntimeException Si hay más de {@see MAX_URLS_PER_FILE} publicaciones indexables.
     */
    public function writePublicSitemaps(): void
    {
        $publicDir = public_path();
        if (!is_dir($publicDir) || !is_writable($publicDir)) {
            throw new \RuntimeException('public/ no es escribible; no se puede escribir el sitemap.');
        }

        $total = $this->publishedPostsCount();
        if ($total > self::MAX_URLS_PER_FILE) {
            throw new \RuntimeException(
                'Hay más de ' . self::MAX_URLS_PER_FILE . ' publicaciones activas; un solo sitemap supera el límite del protocolo. Dividí el generador o reducí el alcance.'
            );
        }

        $this->deleteLegacyPostChunkFiles();

        $indexPath = $publicDir . DIRECTORY_SEPARATOR . 'sitemap.xml';
        $this->writeLinesToPath($indexPath, function (callable $emit): void {
            $this->emitFullUrlset($emit);
        });
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
    private function emitFullUrlset(callable $emit): void
    {
        $emit('<?xml version="1.0" encoding="UTF-8"?>');
        $emit('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        Video::query()
            ->where('is_published', true)
            ->where('moderation_status', 'active')
            ->orderBy('id')
            ->select(['id', 'slug', 'title', 'updated_at'])
            ->each(function (Video $video) use ($emit): void {
                $last = $video->updated_at instanceof Carbon ? $video->updated_at : now();
                $emit($this->urlEntryXml(
                    route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()]),
                    $last
                ));
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

    private function urlEntryXml(string $loc, Carbon $lastmod): string
    {
        $locEsc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $when = $lastmod->toAtomString();

        return '<url><loc>' . $locEsc . '</loc><lastmod>' . $when . '</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>';
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

    private function deleteLegacyPostChunkFiles(): void
    {
        $pattern = public_path('sitemap-posts-*.xml');
        foreach (glob($pattern) ?: [] as $file) {
            @unlink($file);
        }
    }
}
