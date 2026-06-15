<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeSectionLayout;
use App\Support\HomeContentSections;
use App\Support\HomeSectionLayoutResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminHomeContentController extends Controller
{
    public function index(string $section)
    {
        try {
            $config = $this->configOrFail($section);
            if (!Schema::hasTable($config['table'])) {
                return response()->json(['message' => 'Table missing. Run: php artisan migrate'], 503);
            }

            /** @var Model $model */
            $model = $config['model'];

            return response()->json(
                $model::query()->orderBy('sort_order')->paginate(50)
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Failed to load section items',
                'error' => app()->isProduction() ? null : $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request, string $section)
    {
        try {
            $config = $this->configOrFail($section);
            if (!Schema::hasTable($config['table'])) {
                return response()->json(['message' => 'Table missing. Run: php artisan migrate'], 503);
            }

            if ($config['type'] === 'video') {
                return $this->storeVideo($request, $config);
            }

            return $this->storeLink($request, $config);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Upload failed on server',
                'error' => app()->isProduction() ? $e->getMessage() : $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $section, int $id)
    {
        $config = $this->configOrFail($section);
        /** @var Model $model */
        $model = $config['model'];
        $item = $model::query()->findOrFail($id);

        if ($config['type'] === 'video') {
            if ($item->thumbnail) {
                Storage::disk('public')->delete($item->thumbnail);
            }
            if ($item->video_path) {
                Storage::disk('public')->delete($item->video_path);
            }
        } else {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
        }

        $item->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function layouts()
    {
        return response()->json(HomeSectionLayoutResolver::allForAdmin());
    }

    public function updateLayout(Request $request, string $section)
    {
        $this->configOrFail($section);

        if (!Schema::hasTable('home_section_layouts')) {
            return response()->json(['message' => 'Table missing. Run: php artisan migrate'], 503);
        }

        $validator = Validator::make($request->all(), [
            'card_width' => 'required|integer|min:80|max:500',
            'card_height' => 'required|integer|min:80|max:600',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $layout = HomeSectionLayout::query()->updateOrCreate(
            ['section_key' => $section],
            [
                'card_width' => (int) $request->card_width,
                'card_height' => (int) $request->card_height,
            ]
        );

        return response()->json([
            'section_key' => $section,
            'card_width' => $layout->card_width,
            'card_height' => $layout->card_height,
        ]);
    }

    protected function storeVideo(Request $request, array $config)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'required|file|mimes:mp4,mov,avi,mkv,webm,quicktime|max:512000',
            'thumbnail' => 'nullable|image|max:4096',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $folder = str_replace('_', '-', $config['table']);
        $videoPath = $request->file('video')->store("home-content/{$folder}", 'public');
        if (!$videoPath) {
            return response()->json(['message' => 'Could not save video. Check storage permissions.'], 500);
        }
        $thumbPath = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store("home-content/{$folder}/thumbnails", 'public')
            : null;

        /** @var Model $model */
        $model = $config['model'];
        $item = $model::create([
            'title' => $request->title,
            'description' => $request->description,
            'video_path' => $videoPath,
            'thumbnail' => $thumbPath,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($item, 201);
    }

    protected function storeLink(Request $request, array $config)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|file|image|max:4096',
            'action_url' => 'required|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $folder = str_replace('_', '-', $config['table']);
        $imagePath = $request->file('image')->store("home-content/{$folder}", 'public');

        /** @var Model $model */
        $model = $config['model'];
        $item = $model::create([
            'title' => $request->title,
            'description' => $request->description,
            'image_path' => $imagePath,
            'action_url' => $request->action_url,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($item, 201);
    }

    protected function configOrFail(string $section): array
    {
        $config = HomeContentSections::get($section);
        if (!$config) {
            abort(404, 'Unknown home section');
        }

        return $config;
    }
}
