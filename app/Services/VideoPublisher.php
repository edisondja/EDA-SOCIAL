<?php

namespace App\Services;

use App\Hashtag;
use App\Jobs\GenerateVideoCardMediaJob;
use App\Jobs\ProcessVideoMediaJob;
use App\User;
use App\Video;
use Illuminate\Support\Str;

class VideoPublisher
{
    /**
     * Create a video from API-validated payload (same shape as VideoController::store).
     *
     * @param  array<string, mixed>  $data
     */
    public static function createFromValidated(User $user, array $data): Video
    {
        $mediaItems = collect($data['media_items'] ?? [])->values();
        $firstVideoMedia = $mediaItems->first(function ($item) {
            return ($item['type'] ?? null) === 'video';
        });
        $firstMedia = $mediaItems->first();

        $resolvedVideoUrl = $data['video_url']
            ?? ($firstVideoMedia['url'] ?? ($firstMedia['url'] ?? null));

        $video = Video::create([
            'channel_id' => optional($user->channel)->id,
            'author_id' => $user->id,
            'title' => $data['title'],
            'slug' => Str::slug($data['title']) . '-' . Str::lower(Str::random(6)),
            'description' => $data['description'] ?? null,
            'video_url' => $resolvedVideoUrl,
            'preview_url' => $data['preview_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? ($firstMedia['url'] ?? null),
            'duration_seconds' => $data['duration_seconds'] ?? 0,
            'is_published' => (bool) ($data['is_published'] ?? false),
            'published_at' => ($data['is_published'] ?? false) ? now() : null,
        ]);

        foreach ($mediaItems as $index => $item) {
            $video->media()->create([
                'type' => $item['type'],
                'url' => $item['url'],
                'position' => $index,
            ]);
        }

        if (!empty($data['category_ids'])) {
            $video->categories()->sync($data['category_ids']);
        }

        $normalizedHashtags = collect($data['hashtag_names'] ?? [])
            ->map(function ($item) {
                return Str::lower(ltrim(trim($item), '#'));
            })
            ->filter()
            ->unique()
            ->values();

        if ($normalizedHashtags->isNotEmpty()) {
            $hashtagIds = $normalizedHashtags->map(function ($name) {
                return Hashtag::firstOrCreate(['name' => $name])->id;
            });
            $video->hashtags()->sync($hashtagIds->all());
        }

        ProcessVideoMediaJob::dispatch($video->id);

        if ($video->needsGeneratedCardPreview()) {
            GenerateVideoCardMediaJob::dispatch($video->id)->afterResponse();
        }

        SitemapRegenerator::afterContentMutation();

        return $video->load('channel', 'author', 'media', 'categories', 'hashtags');
    }
}
