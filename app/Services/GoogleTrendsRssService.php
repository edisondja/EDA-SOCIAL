<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Lee el RSS público de búsquedas en tendencia de Google Trends (sin API key oficial).
 *
 * @see https://trends.google.com/trending/rss?geo=MX
 */
class GoogleTrendsRssService
{
    public const RSS_BASE = 'https://trends.google.com/trending/rss';

    /** Códigos de país / región admitidos en el panel (mayúsculas). */
    public const ALLOWED_GEO = [
        'MX', 'US', 'ES', 'AR', 'CO', 'CL', 'PE', 'VE', 'EC', 'BR',
        'FR', 'DE', 'GB', 'IT', 'PT', 'CA', 'AU', 'JP', 'KR', 'IN',
        'NL', 'SE', 'PL', 'TR', 'SA', 'EG', 'ZA', 'NG', 'ID', 'PH',
    ];

    private const HT_NS = 'https://trends.google.com/trending/rss';

    /**
     * @return array{geo: string, items: list<array{title: string, traffic: string, pub_date: string, explore_url: string, news: list<array{title: string, url: string, source: string}>}>}
     */
    public function fetch(string $geo, int $limit = 25, bool $refresh = false): array
    {
        $geo = strtoupper(preg_replace('/[^A-Z0-9]/', '', $geo) ?? '');
        if (!in_array($geo, self::ALLOWED_GEO, true)) {
            throw new \InvalidArgumentException('Región no admitida para Google Trends.');
        }

        $limit = max(1, min(40, $limit));
        $cacheKey = 'google-trends-rss:' . $geo;

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($geo, $limit): array {
            $url = self::RSS_BASE . '?geo=' . rawurlencode($geo);
            $response = Http::timeout(14)
                ->withHeaders([
                    'User-Agent' => 'EDA-SOCIAL-Admin/1.0 (editorial trends)',
                    'Accept' => 'application/rss+xml, application/xml, text/xml, */*;q=0.8',
                ])
                ->get($url);

            if (!$response->successful()) {
                throw new \RuntimeException('Google Trends respondió HTTP ' . $response->status() . '.');
            }

            $items = $this->parseRssXml($response->body(), $geo, $limit);

            return [
                'geo' => $geo,
                'items' => $items,
            ];
        });
    }

    /**
     * @return list<array{title: string, traffic: string, pub_date: string, explore_url: string, news: list<array{title: string, url: string, source: string}>}>
     */
    private function parseRssXml(string $xml, string $geo, int $limit): array
    {
        $useErrors = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        if ($sx === false || !isset($sx->channel->item)) {
            throw new \RuntimeException('No se pudo interpretar el RSS de Google Trends.');
        }

        $out = [];
        $count = 0;
        foreach ($sx->channel->item as $item) {
            if ($count >= $limit) {
                break;
            }

            $title = trim(html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '') {
                continue;
            }

            $ht = $item->children(self::HT_NS);
            $traffic = trim((string) ($ht->approx_traffic ?? ''));
            $pubDate = trim((string) ($item->pubDate ?? ''));

            $news = [];
            if ($ht && isset($ht->news_item)) {
                foreach ($ht->news_item as $ni) {
                    $nt = trim(html_entity_decode((string) $ni->news_item_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $nu = trim((string) $ni->news_item_url);
                    $ns = trim((string) $ni->news_item_source);
                    if ($nt === '' && $nu === '') {
                        continue;
                    }
                    $news[] = [
                        'title' => $nt,
                        'url' => $nu,
                        'source' => $ns,
                    ];
                    if (count($news) >= 4) {
                        break;
                    }
                }
            }

            $exploreUrl = 'https://trends.google.com/trends/explore?q=' . rawurlencode($title) . '&geo=' . rawurlencode($geo);

            $out[] = [
                'title' => $title,
                'traffic' => $traffic,
                'pub_date' => $pubDate,
                'explore_url' => $exploreUrl,
                'news' => $news,
            ];
            $count++;
        }

        return $out;
    }
}
