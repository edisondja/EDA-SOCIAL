<?php

namespace App\Http\Controllers\Api;

use App\Comment;
use App\Http\Controllers\Controller;
use App\Services\VideoPublisher;
use App\Services\VideoViewTracker;
use App\Support\VideoAdPresentation;
use App\Support\VideoViewStats;
use App\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    /**
     * Permalink web canónico `/playvideo/{id}/{slug}` en respuestas JSON (integración con enlaces compartibles).
     */
    private function appendWebPermalink(Video $video): void
    {
        $video->setAppends(array_merge($video->getAppends(), ['play_path', 'play_url']));
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $q = Video::query()
            ->with(['channel', 'author', 'media', 'categories', 'hashtags'])
            ->where('is_published', true)
            ->where('moderation_status', 'active')
            ->latest('published_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('category_id')) {
            $categoryId = (int) $request->input('category_id');
            $q->whereHas('categories', function ($inner) use ($categoryId) {
                $inner->where('categories.id', $categoryId);
            });
        }

        if ($request->filled('hashtag')) {
            $hashtag = Str::lower(ltrim($request->input('hashtag'), '#'));
            $q->whereHas('hashtags', function ($inner) use ($hashtag) {
                $inner->where('hashtags.name', $hashtag);
            });
        }

        $paginator = $q->paginate($perPage);
        $paginator->getCollection()->each(fn (Video $v) => $this->appendWebPermalink($v));

        return response()->json($paginator);
    }

    public function show(Video $video)
    {
        if (!$video->is_published || $video->moderation_status !== 'active') {
            return response()->json(['message' => 'Publicación no disponible.'], 404);
        }

        $video->load([
            'channel',
            'author',
            'media',
            'categories',
            'hashtags',
        ]);

        $commentsFlat = Comment::query()
            ->where('video_id', $video->id)
            ->with('user:id,name,username,avatar_url')
            ->orderBy('created_at', 'asc')
            ->get();
        $video->setRelation('comments', Comment::nestForDisplay($commentsFlat));

        $categoryIds = $video->categories->pluck('id')->all();
        $hashtagIds = $video->hashtags->pluck('id')->all();

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

        VideoViewTracker::record($video);
        $video->refresh();

        $this->appendWebPermalink($video);
        $related->each(fn (Video $v) => $this->appendWebPermalink($v));

        return response()->json([
            'video' => $video,
            'related_videos' => $related,
            'video_ads' => VideoAdPresentation::resolved(),
            'video_stats' => [
                'total_views' => (int) $video->views_count,
                'daily_views' => VideoViewStats::last30Days($video),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'description' => 'nullable|string',
            'video_url' => 'nullable|string|max:8192',
            'preview_url' => 'nullable|string|max:8192',
            'thumbnail_url' => 'nullable|string|max:8192',
            'duration_seconds' => 'nullable|integer|min:0',
            'is_published' => 'sometimes|boolean',
            'media_items' => 'nullable|array|min:1',
            'media_items.*.type' => 'required_with:media_items|in:image,video',
            'media_items.*.url' => 'required_with:media_items|string|max:8192',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'hashtag_names' => 'nullable|array',
            'hashtag_names.*' => 'string|max:80',
        ]);

        if (empty($data['video_url']) && empty($data['media_items'])) {
            return response()->json([
                'message' => 'Debes enviar video_url o al menos un elemento en media_items.',
            ], 422);
        }

        $video = VideoPublisher::createFromValidated($request->user(), $data);
        $this->appendWebPermalink($video);

        return response()->json($video, 201);
    }
}
