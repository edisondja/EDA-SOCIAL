<?php

namespace App\Http\Controllers\Web;

use App\Category;
use App\Hashtag;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Services\SitemapRegenerator;
use App\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MyVideosController extends Controller
{
    use SharesBranding;

    public function index(Request $request)
    {
        $videos = Video::query()
            ->where('author_id', $request->user()->id)
            ->with(['channel', 'categories'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(20);

        return view('web.account-videos', [
            'videos' => $videos,
            'branding' => $this->branding(),
        ]);
    }

    public function edit(Request $request, Video $video)
    {
        $this->assertAuthorOwnsVideo($request, $video);

        $video->load(['categories', 'hashtags', 'channel', 'media']);

        $categories = Category::query()->orderBy('name')->get();
        $hashtagString = $video->hashtags->pluck('name')->map(function ($n) {
            return '#' . $n;
        })->implode(', ');

        return view('web.account-video-edit', [
            'video' => $video,
            'categories' => $categories,
            'hashtagString' => $hashtagString,
            'branding' => $this->branding(),
        ]);
    }

    public function update(Request $request, Video $video)
    {
        $this->assertAuthorOwnsVideo($request, $video);

        $data = $request->validate([
            'title' => 'required|string|max:180',
            'slug' => ['required', 'string', 'max:220', Rule::unique('videos', 'slug')->ignore($video->id)],
            'description' => 'nullable|string|max:65535',
            'video_url' => 'required|string|max:255',
            'preview_url' => 'nullable|string|max:255',
            'thumbnail_url' => 'nullable|string|max:255',
            'thumbnail_file' => 'nullable|image|max:5120',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'hashtags' => 'nullable|string|max:2000',
        ]);

        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('video-thumbnails/' . $video->id, 'public');
            $data['thumbnail_url'] = Storage::disk('public')->url($path);
        }
        unset($data['thumbnail_file']);

        $previewUrl = trim((string) ($data['preview_url'] ?? ''));
        $thumbUrl = trim((string) ($data['thumbnail_url'] ?? ''));
        $desc = trim((string) ($data['description'] ?? ''));

        $video->update([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'description' => $desc !== '' ? $data['description'] : null,
            'video_url' => $data['video_url'],
            'preview_url' => $previewUrl !== '' ? $previewUrl : null,
            'thumbnail_url' => $thumbUrl !== '' ? $thumbUrl : null,
        ]);

        $video->categories()->sync($data['category_ids'] ?? []);

        $hashtagNames = collect(preg_split('/\s*,\s*/', (string) ($data['hashtags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(function ($item) {
                return Str::lower(ltrim(trim($item), '#'));
            })
            ->filter()
            ->unique()
            ->values();

        if ($hashtagNames->isEmpty()) {
            $video->hashtags()->sync([]);
        } else {
            $hashtagIds = $hashtagNames->map(function ($name) {
                return Hashtag::firstOrCreate(['name' => $name])->id;
            });
            $video->hashtags()->sync($hashtagIds->all());
        }

        SitemapRegenerator::afterContentMutation();

        return redirect()
            ->route('account.videos.edit', $video)
            ->with('status', 'Cambios guardados.');
    }

    private function assertAuthorOwnsVideo(Request $request, Video $video): void
    {
        if ((int) $video->author_id !== (int) $request->user()->id) {
            abort(403);
        }
    }
}
