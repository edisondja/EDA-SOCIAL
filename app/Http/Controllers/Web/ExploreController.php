<?php

namespace App\Http\Controllers\Web;

use App\Category;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Video;
use Illuminate\Http\Request;
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

        if ($request->filled('search')) {
            $search = $request->input('search');
            $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
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

        $videos = $q->paginate($perPage)->withQueryString();
        $categories = Category::query()->orderBy('name')->get();
        $branding = $this->branding();

        return view('web.explore', compact('videos', 'categories', 'branding'));
    }
}
