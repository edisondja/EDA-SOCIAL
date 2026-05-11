<?php

namespace App\Http\Controllers\Web;

use App\Category;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Jobs\GenerateVideoCardMediaJob;
use App\Services\HlsPreviewService;
use App\Support\PlatformConfig;
use App\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExploreController extends Controller
{
    use SharesBranding;

    public function index(Request $request, HlsPreviewService $hlsPreviewService)
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

        $this->attachCardPreviewUrls($videos, $hlsPreviewService);

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

    /**
     * Encola ffmpeg (poster + clip hover) cuando el usuario pasa el cursor por la tarjeta.
     * Dedupe con caché (Redis si CACHE_DRIVER=redis) para no saturar la cola.
     */
    public function enqueueHoverCardMedia(Request $request, Video $video)
    {
        abort_unless($video->is_published && $video->moderation_status === 'active', 404);

        if (!$video->needsGeneratedCardPreview()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $key = 'hover-card:queued:' . $video->id;
        if (!Cache::add($key, 1, now()->addMinutes(15))) {
            return response()->json(['ok' => true, 'queued' => false, 'deduped' => true]);
        }

        try {
            GenerateVideoCardMediaJob::dispatch($video->id);
        } catch (\Throwable $e) {
            Cache::forget($key);
            report($e);

            return response()->json(['ok' => false, 'message' => 'No se pudo encolar la generación.'], 500);
        }

        return response()->json(['ok' => true, 'queued' => true]);
    }

    private function attachCardPreviewUrls($videos, HlsPreviewService $hlsPreviewService): void
    {
        $items = method_exists($videos, 'items') ? $videos->items() : [];
        foreach ($items as $video) {
            if (!$video instanceof Video) {
                continue;
            }

            /*
             * El <video> del feed solo admite contenedores típicos (MP4/WebM/MOV…).
             * Un segmento .ts de HLS como src suele romper el decodificador: tras hover queda en error y “desaparece” la vista previa.
             */
            $previewUrl = trim((string) $video->preview_url);
            if ($previewUrl !== '' && !$this->isDirectVideoElementPlayableUrl($previewUrl)) {
                $previewUrl = '';
            }
            if ($previewUrl === '') {
                $candidate = (string) $hlsPreviewService->randomSegmentUrlForVideo($video);
                if ($candidate !== '' && $this->isDirectVideoElementPlayableUrl($candidate)) {
                    $previewUrl = $candidate;
                }
            }

            $video->setAttribute('card_preview_url', $previewUrl);
        }
    }

    /**
     * URLs que el elemento HTML {@code <video src>} puede reproducir de forma fiable (no .ts suelto ni .m3u8).
     */
    private function isDirectVideoElementPlayableUrl(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: $url));

        return (bool) preg_match('/\.(mp4|webm|mov|m4v|mkv|ogv)(\?.*)?$/i', $path);
    }
}
