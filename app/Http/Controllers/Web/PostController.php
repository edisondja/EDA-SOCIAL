<?php

namespace App\Http\Controllers\Web;

use App\Comment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Services\VideoViewTracker;
use App\Support\VideoAdPresentation;
use App\Support\VideoViewStats;
use App\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    use SharesBranding;

    public function show(Request $request, Video $video)
    {
        if (!$video->is_published || $video->moderation_status !== 'active') {
            abort(404);
        }

        $video->load([
            'channel',
            'author',
            'media',
            'categories',
            'hashtags',
        ]);

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

        $commentsTree = Comment::nestForDisplay(
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
        $videoStats = [
            'total_views' => (int) $video->views_count,
            'daily_views' => VideoViewStats::last30Days($video),
        ];

        return view('web.post', compact('video', 'related', 'commentsTree', 'branding', 'videoAds', 'videoStats'));
    }

    public function storeComment(Request $request, Video $video)
    {
        if (!$video->is_published || $video->moderation_status !== 'active') {
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

        Comment::create([
            'video_id' => $video->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId ?: null,
            'body' => $data['body'],
            'points' => 0,
        ]);

        return redirect()->route('posts.show', $video)->with('status', 'Comentario publicado.');
    }

    public function voteComment(Request $request, Comment $comment)
    {
        $data = $request->validate([
            'value' => 'required|integer|in:-1,1',
        ]);

        $comment->increment('points', $data['value']);

        return redirect()->back()->withFragment('comments');
    }
}
