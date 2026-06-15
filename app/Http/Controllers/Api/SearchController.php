<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'q'          => 'nullable|string|max:100',
            'type'       => 'nullable|in:movie,series,episode',
            'category'   => 'nullable|string',
            'is_premium' => 'nullable|boolean',
            'sort'       => 'nullable|in:latest,oldest,title',
            'per_page'   => 'nullable|integer|min:5|max:50',
        ]);

        $videos = Video::with(['channel', 'categories'])
            ->ott()
            ->where('status', 'published')
            ->when($request->q, fn($q) =>
                $q->where(fn($sub) =>
                    $sub->where('title', 'like', "%{$request->q}%")
                        ->orWhere('description', 'like', "%{$request->q}%")
                )
            )
            ->when($request->type, fn($q) =>
                $q->where('type', $request->type)
            )
            ->when($request->category, fn($q) =>
                $q->whereHas('categories', fn($sub) =>
                    $sub->where('slug', $request->category)
                )
            )
            ->when(!is_null($request->is_premium), fn($q) =>
                $q->where('is_premium', $request->boolean('is_premium'))
            )
            ->when($request->sort === 'oldest', fn($q) => $q->oldest())
            ->when($request->sort === 'title',  fn($q) => $q->orderBy('title'))
            ->when(!$request->sort || $request->sort === 'latest', fn($q) => $q->latest())
            ->paginate($request->per_page ?? 20)
            ->withQueryString();

        return response()->json($videos);
    }
}