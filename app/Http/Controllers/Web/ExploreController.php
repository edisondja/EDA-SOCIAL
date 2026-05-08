<?php

namespace App\Http\Controllers\Web;

use App\Category;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Jobs\GenerateVideoPosterJob;
use App\Support\PlatformConfig;
use App\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExploreController extends Controller
{
    use SharesBranding;

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $q = Video::query()
            ->with(['channel', 'author', 'media', 'categories', 'hashtags'])
            ->where('is_published', true)
            ->where('moderation_status', 'active')
            ->latest('published_at');

        $search = $request->input('search');
        if (is_string($search)) {
            $search = trim(mb_substr($search, 0, 160));
        } else {
            $search = '';
        }
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $q->where(function ($inner) use ($like) {
                $inner->where('videos.title', 'like', $like)
                    ->orWhere('videos.description', 'like', $like)
                    ->orWhereHas('hashtags', function ($h) use ($like) {
                        $h->where('hashtags.name', 'like', $like);
                    })
                    ->orWhereHas('categories', function ($c) use ($like) {
                        $c->where('categories.name', 'like', $like);
                    })
                    ->orWhereHas('channel', function ($ch) use ($like) {
                        $ch->where('channels.display_name', 'like', $like);
                    })
                    ->orWhereHas('author', function ($u) use ($like) {
                        $u->where('users.name', 'like', $like);
                    });
            });
        }

        if ($request->filled('categoria')) {
            $categoryId = (int) $request->input('categoria');
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

        $videos = $this->useRedisQueryCache()
            ? Cache::remember(
                'explore:videos:' . md5(json_encode([
                    'search' => $search,
                    'categoria' => (string) $request->input('categoria', ''),
                    'hashtag' => (string) $request->input('hashtag', ''),
                    'page' => (int) $request->input('page', 1),
                    'per_page' => $perPage,
                ])),
                now()->addSeconds(45),
                function () use ($q, $perPage) {
                    return $q->paginate($perPage)->withQueryString();
                }
            )
            : $q->paginate($perPage)->withQueryString();

        $this->queueMissingPostersFromPage($videos);

        if ($request->boolean('fragment')) {
            return response()->view('web.partials.explore-video-cards', compact('videos'));
        }

        $categories = $this->useRedisQueryCache()
            ? Cache::remember('explore:categories', now()->addMinutes(10), function () {
                return Category::query()->orderBy('name')->get();
            })
            : Category::query()->orderBy('name')->get();
        $branding = $this->branding();

        return view('web.explore', compact('videos', 'categories', 'branding'));
    }

    private function useRedisQueryCache(): bool
    {
        return PlatformConfig::get('feature_redis_cache') === '1' && config('cache.default') === 'redis';
    }

    private function queueMissingPostersFromPage($videos): void
    {
        $items = method_exists($videos, 'items') ? $videos->items() : [];
        foreach ($items as $video) {
            if (!$video instanceof Video) {
                continue;
            }
            if (trim((string) $video->thumbnail_url) !== '') {
                continue;
            }
            $key = 'poster:queued:video:' . $video->id;
            if (Cache::add($key, '1', now()->addMinutes(10))) {
                GenerateVideoPosterJob::dispatch($video->id);
            }
        }
    }
}
