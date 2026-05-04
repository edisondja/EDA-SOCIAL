<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Services\VideoPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PublishController extends Controller
{
    use SharesBranding;

    public function create()
    {
        return redirect()->route('explore.index')->with('open_publish_modal', true);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:180',
            'description' => 'nullable|string',
            'media_files' => 'required|array|min:1',
            'media_files.*' => 'file|max:51200',
            'hashtags' => 'nullable|string|max:2000',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('open_publish_modal', true);
        }

        $validated = $validator->validated();

        $mediaItems = [];
        foreach ($request->file('media_files', []) as $file) {
            $mime = (string) $file->getMimeType();
            $isVideo = strncmp($mime, 'video/', 6) === 0;
            $isImage = strncmp($mime, 'image/', 6) === 0;
            if (!$isVideo && !$isImage) {
                return back()->withErrors(['media_files' => 'Tipo de archivo no permitido.'])->withInput()->with('open_publish_modal', true);
            }
            $path = $file->store('uploads', 'public');
            $url = Storage::disk('public')->url($path);
            $mediaItems[] = [
                'type' => $isVideo ? 'video' : 'image',
                'url' => $url,
            ];
        }

        $hashtagNames = collect(preg_split('/\s*,\s*/', (string) ($validated['hashtags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(function ($item) {
                return ltrim(trim($item), '#');
            })
            ->filter()
            ->values()
            ->all();

        $data = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'video_url' => null,
            'media_items' => $mediaItems,
            'category_ids' => $validated['category_ids'] ?? [],
            'hashtag_names' => $hashtagNames,
            'is_published' => true,
        ];

        VideoPublisher::createFromValidated($request->user(), $data);

        return redirect()->route('explore.index')->with('status', 'Publicación creada.');
    }
}
