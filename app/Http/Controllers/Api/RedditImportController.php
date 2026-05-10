<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoMediaJob;
use App\Services\SitemapRegenerator;
use App\Services\RedditVideoImportService;
use App\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RedditImportController extends Controller
{
    public function import(Request $request, RedditVideoImportService $reddit)
    {
        $data = $request->validate([
            'reddit_url' => ['required', 'string', 'max:512'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        $user = $request->user();
        if (!optional($user->channel)->id) {
            return response()->json([
                'message' => 'Tu usuario no tiene canal asociado; no se puede importar.',
            ], 422);
        }

        $meta = $reddit->fetchMetadata($data['reddit_url']);
        if (empty($meta['video_url'])) {
            return response()->json([
                'message' => 'No se encontró URL de video en el post (puede ser imagen, texto o Reddit bloqueó la lectura).',
                'meta' => $meta,
            ], 422);
        }

        $title = Str::limit(trim($data['title']), 180, '');
        $baseDescription = isset($data['description']) ? trim((string) $data['description']) : '';
        if ($baseDescription === '') {
            $baseDescription = 'Importado desde Reddit.';
        }
        $description = $baseDescription;
        if (! empty($meta['permalink'])) {
            $description .= "\n\n— ".$meta['permalink'];
        }

        $slugBase = Str::slug(Str::limit($title, 160, ''));
        $slugBase = $slugBase !== '' ? $slugBase : 'reddit';
        $slugBase = Str::limit($slugBase, 160, '');

        $video = Video::create([
            'channel_id' => $user->channel->id,
            'author_id' => $user->id,
            'title' => $title,
            'slug' => $slugBase.'-'.Str::lower(Str::random(8)),
            'description' => $description,
            'video_url' => $meta['video_url'],
            'preview_url' => $meta['preview_url'],
            'thumbnail_url' => $meta['thumbnail_url'],
            'duration_seconds' => 0,
            'is_published' => true,
            'published_at' => now(),
            'moderation_status' => 'active',
        ]);

        $video->media()->create([
            'type' => 'video',
            'url' => $meta['video_url'],
            'position' => 0,
        ]);

        ProcessVideoMediaJob::dispatch($video->id);

        if (! empty($data['category_ids'])) {
            $video->categories()->sync(array_values(array_unique($data['category_ids'])));
        }

        SitemapRegenerator::afterContentMutation();

        return response()->json($video->load('channel', 'author', 'media', 'categories'), 201);
    }
}
