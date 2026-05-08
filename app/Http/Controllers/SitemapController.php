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
        $base = rtrim((string) (PlatformConfig::get('public_site_url') ?: config('app.url')), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }
        $includeAllPosts = PlatformConfig::get('sitemap_include_all_posts', '1') === '1';

        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        $lines[] = $this->urlXml($base . '/explorar?page=1', now());

        if ($includeAllPosts) {
            $lastPage = (int) ceil(Video::query()
                ->where('is_published', true)
                ->where('moderation_status', 'active')
                ->count() / 20);
            $lastPage = max(1, min($lastPage, 5000));
            for ($p = 2; $p <= $lastPage; $p += 1) {
                $lines[] = $this->urlXml($base . '/explorar?page=' . $p, now());
            }

            Video::query()
                ->where('is_published', true)
                ->where('moderation_status', 'active')
                ->orderByDesc('updated_at')
                ->select(['id', 'slug', 'title', 'updated_at'])
                ->chunkById(500, function ($videos) use (&$lines) {
                    foreach ($videos as $video) {
                        $last = $video->updated_at instanceof Carbon ? $video->updated_at : now();
                        $lines[] = $this->urlXml(route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()]), $last);
                    }
                });
        }

        $lines[] = '</urlset>';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function urlXml(string $loc, Carbon $lastmod): string
    {
        $locEsc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $when = $lastmod->toAtomString();

        return '<url><loc>' . $locEsc . '</loc><lastmod>' . $when . '</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>';
    }
}
