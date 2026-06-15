<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminHomeBannerController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('home_banners')) {
            return response()->json([
                'message' => 'home_banners table missing. Run: php artisan migrate',
            ], 503);
        }

        return response()->json(
            HomeBanner::query()->orderBy('sort_order')->paginate(50)
        );
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('home_banners')) {
            return response()->json([
                'message' => 'home_banners table missing. Run: php artisan migrate',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image' => 'required|file|image|max:4096',
            'link_url' => 'nullable|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $request->file('image')->store('home-banners', 'public');

        $banner = HomeBanner::create([
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'image_path' => $path,
            'link_url' => $request->filled('link_url') ? $request->link_url : null,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($banner, 201);
    }

    public function update(Request $request, HomeBanner $homeBanner)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image' => 'sometimes|file|image|max:4096',
            'link_url' => 'nullable|url|max:500',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($homeBanner->image_path);
            $data['image_path'] = $request->file('image')->store('home-banners', 'public');
        }

        unset($data['image']);
        $homeBanner->update($data);

        return response()->json($homeBanner->fresh());
    }

    public function destroy(HomeBanner $homeBanner)
    {
        Storage::disk('public')->delete($homeBanner->image_path);
        $homeBanner->delete();

        return response()->json(['message' => 'Banner deleted']);
    }
}
