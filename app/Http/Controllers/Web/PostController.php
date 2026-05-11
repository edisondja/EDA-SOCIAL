<?php

namespace App\Http\Controllers\Web;

use App\Comment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Jobs\GenerateVideoCardMediaJob;
use App\Jobs\GenerateVideoHlsJob;
use App\Services\CommentNotificationDispatcher;
use App\Services\VideoViewTracker;
use App\Support\PlatformConfig;
use App\Support\VideoAdPresentation;
use App\Video;
use App\VideoRating;
use App\VideoReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PostController extends Controller
{
    use SharesBranding;

    public function show(Request $request, Video $video, string $slug)
    {
        if (!$video->is_published || $video->moderation_status !== 'active') {
            abort(404);
        }

        if (!$video->slugMatchesPlayRoute($slug)) {
            return redirect()->route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()], 301);
        }

        $this->queueCardMediaGenerationIfNeeded($video);
        $this->queueHlsGenerationIfNeeded($video);

        $video->load([
            'channel',
            'author',
            'media',
            'categories',
            'hashtags',
        ]);

        $categoryIds = $video->categories->pluck('id')->all();
        $hashtagIds = $video->hashtags->pluck('id')->all();

        $relatedCacheKey = 'post:related:' . $video->id;
        if ($this->useRedisQueryCache()) {
            $related = Cache::remember($relatedCacheKey, now()->addSeconds(45), function () use ($video, $categoryIds, $hashtagIds) {
                $related = collect([]);
                if (!empty($categoryIds) || !empty($hashtagIds)) {
                    $related = Video::query()
                        ->with(['channel', 'author', 'media', 'categories', 'hashtags'])
                        ->where('id', '!=', $video->id)
                        ->where('is_published', true)
                        ->where('moderation_status', 'active')
                        ->where(function ($builder) use ($categoryIds, $hashtagIds) {
                            if (!empty($categoryIds)) {
                                $builder->whereHas('categories', function ($inner) use ($categoryIds) {
                                    $inner->whereIn('categories.id', $categoryIds);
                                });
                            }
                            if (!empty($hashtagIds)) {
                                $builder->orWhereHas('hashtags', function ($inner) use ($hashtagIds) {
                                    $inner->whereIn('hashtags.id', $hashtagIds);
                                });
                            }
                        })
                        ->latest('published_at')
                        ->limit(8)
                        ->get();
                }

                return $related;
            });
        } else {
            $related = collect([]);
            if (!empty($categoryIds) || !empty($hashtagIds)) {
                $related = Video::query()
                    ->with(['channel', 'author', 'media', 'categories', 'hashtags'])
                    ->where('id', '!=', $video->id)
                    ->where('is_published', true)
                    ->where('moderation_status', 'active')
                    ->where(function ($builder) use ($categoryIds, $hashtagIds) {
                        if (!empty($categoryIds)) {
                            $builder->whereHas('categories', function ($inner) use ($categoryIds) {
                                $inner->whereIn('categories.id', $categoryIds);
                            });
                        }
                        if (!empty($hashtagIds)) {
                            $builder->orWhereHas('hashtags', function ($inner) use ($hashtagIds) {
                                $inner->whereIn('hashtags.id', $hashtagIds);
                            });
                        }
                    })
                    ->latest('published_at')
                    ->limit(8)
                    ->get();
            }
        }

        $commentsCacheKey = 'post:comments:' . $video->id;
        $commentsTree = $this->useRedisQueryCache()
            ? Cache::remember($commentsCacheKey, now()->addSeconds(20), function () use ($video) {
                return Comment::nestForDisplay(
                    Comment::query()
                        ->where('video_id', $video->id)
                        ->with('user:id,name,username,avatar_url')
                        ->orderBy('created_at', 'asc')
                        ->get()
                );
            })
            : Comment::nestForDisplay(
                Comment::query()
                    ->where('video_id', $video->id)
                    ->with('user:id,name,username,avatar_url')
                    ->orderBy('created_at', 'asc')
                    ->get()
            );

        $branding = $this->branding();

        VideoViewTracker::record($video);
        $video->refresh();

        $videoAds = VideoAdPresentation::resolved();

        $ratingStats = $this->useRedisQueryCache()
            ? Cache::remember('post:rating:' . $video->id, now()->addSeconds(20), function () use ($video) {
                $count = (int) VideoRating::query()->where('video_id', $video->id)->count();
                $avgRaw = VideoRating::query()->where('video_id', $video->id)->avg('score');
                return [
                    'count' => $count,
                    'avg' => $avgRaw !== null ? round((float) $avgRaw, 2) : null,
                ];
            })
            : [
                'count' => (int) VideoRating::query()->where('video_id', $video->id)->count(),
                'avg' => (($tmp = VideoRating::query()->where('video_id', $video->id)->avg('score')) !== null ? round((float) $tmp, 2) : null),
            ];
        $ratingCount = (int) ($ratingStats['count'] ?? 0);
        $ratingAvg = $ratingStats['avg'] ?? null;
        $userRating = null;
        if ($request->user()) {
            $userRating = VideoRating::query()
                ->where('video_id', $video->id)
                ->where('user_id', $request->user()->id)
                ->value('score');
        }

        $seoOgImage = $video->openGraphImageAbsolute();
        $rawDesc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($video->description ?? ''))));
        $seoOgDescription = $rawDesc !== '' ? Str::limit($rawDesc, 300, '…') : '';
        $seoOgType = 'article';

        return view('web.post', compact(
            'video',
            'related',
            'commentsTree',
            'branding',
            'videoAds',
            'ratingCount',
            'ratingAvg',
            'userRating',
            'seoOgImage',
            'seoOgDescription',
            'seoOgType'
        ));
    }

    public function storeRating(Request $request, Video $video, string $slug)
    {
        if (!$video->is_published || $video->moderation_status !== 'active') {
            abort(404);
        }

        if (!$video->slugMatchesPlayRoute($slug)) {
            abort(404);
        }

        $data = $request->validate([
            'score' => 'required|integer|min:1|max:5',
        ]);

        VideoRating::updateOrCreate(
            [
                'video_id' => $video->id,
                'user_id' => $request->user()->id,
            ],
            ['score' => (int) $data['score']]
        );

        $avgRaw = VideoRating::query()->where('video_id', $video->id)->avg('score');
        $count = VideoRating::query()->where('video_id', $video->id)->count();
        $this->flushVideoPageCache($video->id);

        $payload = [
            'ok' => true,
            'average' => $avgRaw !== null ? round((float) $avgRaw, 2) : null,
            'count' => (int) $count,
            'yourScore' => (int) $data['score'],
        ];

        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()])
            ->withFragment('valoracion')
            ->with('status', 'Valoración guardada.');
    }

    public function storeComment(Request $request, Video $video, string $slug)
    {
        if (!$video->is_published || $video->moderation_status !== 'active') {
            abort(404);
        }

        if (!$video->slugMatchesPlayRoute($slug)) {
            abort(404);
        }

        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId === 0) {
            $parentId = null;
        }
        $parentError = Comment::replyParentError($video, $parentId);
        if ($parentError !== null) {
            return redirect()->back()->withErrors(['parent_id' => $parentError])->withInput();
        }

        $comment = Comment::create([
            'video_id' => $video->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId ?: null,
            'body' => $data['body'],
            'points' => 0,
        ]);
        CommentNotificationDispatcher::afterCommentStored($comment);
        $this->flushVideoPageCache($video->id);

        return redirect()->route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()])
            ->with('status', 'Comentario publicado.');
    }

    public function voteComment(Request $request, Comment $comment)
    {
        $data = $request->validate([
            'value' => 'required|integer|in:-1,1',
        ]);

        $comment->increment('points', $data['value']);
        $comment->refresh();
        if ((int) $data['value'] === 1 && $request->user()) {
            CommentNotificationDispatcher::afterCommentUpvoted($comment, $request->user());
        }
        $this->flushVideoPageCache($comment->video_id);

        return redirect()->back()->withFragment('comments');
    }

    public function storeReport(Request $request, Video $video, string $slug)
    {
        if (!$video->is_published || $video->moderation_status !== 'active') {
            abort(404);
        }

        if (!$video->slugMatchesPlayRoute($slug)) {
            abort(404);
        }

        $data = $request->validate([
            'reason' => 'required|string|in:spam,inappropriate,copyright,misleading,violence,other',
            'details' => 'nullable|string|max:2000',
        ]);

        VideoReport::create([
            'video_id' => $video->id,
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
            'details' => isset($data['details']) ? trim((string) $data['details']) : null,
            'status' => VideoReport::STATUS_PENDING,
        ]);

        return redirect()->route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()])
            ->with('status', 'Gracias: tu reporte fue enviado al equipo de moderación.');
    }

    private function useRedisQueryCache(): bool
    {
        return PlatformConfig::get('feature_redis_cache') === '1' && config('cache.default') === 'redis';
    }

    private function flushVideoPageCache(int $videoId): void
    {
        if (!$this->useRedisQueryCache()) {
            return;
        }
        Cache::forget('post:related:' . $videoId);
        Cache::forget('post:comments:' . $videoId);
        Cache::forget('post:rating:' . $videoId);
    }

    private function queueHlsGenerationIfNeeded(Video $video): void
    {
        if (!config('hls.enabled', false)) {
            return;
        }
        $urls = [];
        if (!empty($video->video_url)) {
            $urls[] = (string) $video->video_url;
        }
        if (!$video->relationLoaded('media')) {
            $video->load('media');
        }
        foreach ($video->media as $m) {
            if (($m->type ?? '') === 'video' && !empty($m->url)) {
                $urls[] = (string) $m->url;
            }
        }
        $needs = false;
        foreach ($urls as $u) {
            if (preg_match('/\.(mp4|mov|m4v|webm)(\?.*)?$/i', trim($u))) {
                $needs = true;
                break;
            }
        }
        if (!$needs) {
            return;
        }

        $key = 'hls:queued:video:' . $video->id;
        if (Cache::add($key, '1', now()->addMinutes(10))) {
            GenerateVideoHlsJob::dispatch($video->id)->afterResponse();
        }
    }

    private function queueCardMediaGenerationIfNeeded(Video $video): void
    {
        if (!$video->needsGeneratedCardPreview()) {
            return;
        }

        $key = 'card-media:queued:video:' . $video->id;
        if (Cache::add($key, '1', now()->addMinutes(10))) {
            GenerateVideoCardMediaJob::dispatch($video->id)->afterResponse();
        }
    }
}
