<?php

namespace App\Http\Controllers\Api;

use App\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            Category::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug ?: Str::lower(Str::random(8));
        $attempt = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $attempt;
            $attempt++;
        }

        $category = Category::create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        return response()->json($category, 201);
    }
}
