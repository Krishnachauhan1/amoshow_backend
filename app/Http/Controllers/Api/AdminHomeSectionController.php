<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use App\Models\Video;
use Illuminate\Http\Request;

class AdminHomeSectionController extends Controller
{
    public function index()
    {
        $sections = HomeSection::query()
            ->orderBy('sort_order')
            ->with(['category:id,name,slug', 'videos:id,title,thumbnail,type,status,platform'])
            ->paginate(50);

        return response()->json($sections);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:category',
            'category_id' => 'required_if:type,category|exists:categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $section = HomeSection::create([
            'title' => $data['title'],
            'type' => $data['type'],
            'category_id' => $data['category_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'limit' => $data['limit'] ?? 10,
        ]);

        return response()->json($section->load(['category', 'videos']), 201);
    }

    public function update(Request $request, HomeSection $homeSection)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $homeSection->update($data);

        return response()->json($homeSection->load(['category', 'videos']));
    }

    public function destroy(HomeSection $homeSection)
    {
        $homeSection->delete();
        return response()->json(['message' => 'Home section deleted']);
    }

    /**
     * Replace section videos and ordering.
     * Payload:
     * - videos: [{ "id": 123, "sort_order": 0 }, ...]
     */
    public function syncVideos(Request $request, HomeSection $homeSection)
    {
        $data = $request->validate([
            'videos' => 'required|array',
            'videos.*.id' => 'required|exists:videos,id',
            'videos.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $ids = collect($data['videos'])->pluck('id')->unique()->values();

        $validIds = Video::query()
            ->whereIn('id', $ids)
            ->ott()
            ->where('status', 'published')
            ->pluck('id')
            ->all();

        $sync = [];
        foreach ($data['videos'] as $row) {
            if (!in_array($row['id'], $validIds, true)) continue;
            $sync[$row['id']] = ['sort_order' => $row['sort_order'] ?? 0];
        }

        $homeSection->videos()->sync($sync);

        return response()->json($homeSection->load(['category', 'videos']));
    }
}

