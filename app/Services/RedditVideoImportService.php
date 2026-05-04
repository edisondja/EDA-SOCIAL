<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Importación básica desde enlaces públicos de Reddit (v.redd.it / HLS / fallback).
 * Si tu repo ed-community tiene lógica más completa, sustituye el cuerpo de extractFromPostData().
 */
class RedditVideoImportService
{
    /** @return array{title: string, video_url: ?string, preview_url: ?string, thumbnail_url: ?string, permalink: ?string} */
    public function fetchMetadata(string $redditUrl): array
    {
        $postId = $this->extractPostId($redditUrl);
        if (!$postId) {
            throw new \InvalidArgumentException('No se pudo leer el id del post de Reddit desde la URL.');
        }

        $jsonUrl = 'https://www.reddit.com/comments/' . $postId . '.json?raw_json=1';
        $client = new Client([
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'EDA_SOCIAL/1.0 (+https://eda.social) LaravelImport',
                'Accept' => 'application/json',
            ],
        ]);

        $response = $client->get($jsonUrl);
        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload) || empty($payload[0]['data']['children'][0]['data'])) {
            throw new \RuntimeException('Respuesta de Reddit inválida o vacía.');
        }

        $post = $payload[0]['data']['children'][0]['data'];

        return $this->extractFromPostData($post);
    }

    private function extractPostId(string $url): ?string
    {
        if (preg_match('~/comments/([a-z0-9]+)/~i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractFromPostData(array $post): array
    {
        $title = (string) ($post['title'] ?? 'Reddit');
        $permalink = isset($post['permalink']) ? 'https://www.reddit.com' . $post['permalink'] : null;

        $thumb = Arr::get($post, 'preview.images.0.source.url');
        if (is_string($thumb)) {
            $thumb = str_replace('&amp;', '&', html_entity_decode($thumb, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        } else {
            $thumb = Arr::get($post, 'thumbnail');
            if (!is_string($thumb) || Str::startsWith($thumb, ['self', 'default', 'nsfw', 'spoiler'])) {
                $thumb = null;
            }
        }

        $videoUrl = null;
        $previewUrl = null;

        $redditVideo = Arr::get($post, 'secure_media.reddit_video')
            ?: Arr::get($post, 'media.reddit_video');

        if (is_array($redditVideo)) {
            $videoUrl = $redditVideo['fallback_url']
                ?? $redditVideo['hls_url']
                ?? $redditVideo['dash_url']
                ?? null;
            $previewUrl = $redditVideo['scrubber_media_url'] ?? $videoUrl;
        }

        if (!$videoUrl && isset($post['url']) && is_string($post['url'])) {
            $candidate = $post['url'];
            if (Str::contains($candidate, ['v.redd.it', 'reddit.com/gallery'])) {
                $videoUrl = $candidate;
            }
        }

        return [
            'title' => Str::limit($title, 180, ''),
            'video_url' => is_string($videoUrl) ? $videoUrl : null,
            'preview_url' => is_string($previewUrl) ? $previewUrl : null,
            'thumbnail_url' => is_string($thumb) ? $thumb : null,
            'permalink' => $permalink,
        ];
    }
}
