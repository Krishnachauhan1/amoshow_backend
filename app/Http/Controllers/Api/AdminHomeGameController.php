<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeGame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminHomeGameController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('home_games')) {
            return response()->json([
                'message' => 'home_games table missing. Run: php artisan migrate',
            ], 503);
        }

        $games = HomeGame::query()
            ->orderBy('sort_order')
            ->paginate(50);

        return response()->json($games);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('home_games')) {
            return response()->json([
                'message' => 'home_games table missing. Run: php artisan migrate',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|file|image|max:4096',
            'action_url' => 'required|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $request->file('image')->store('home-games', 'public');

        $game = HomeGame::create([
            'title' => $request->title,
            'description' => $request->description,
            'image_path' => $path,
            'action_url' => $request->action_url,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($game, 201);
    }

    public function update(Request $request, HomeGame $homeGame)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'sometimes|file|image|max:4096',
            'action_url' => 'sometimes|url|max:500',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($homeGame->image_path);
            $data['image_path'] = $request->file('image')->store('home-games', 'public');
        }

        unset($data['image']);
        $homeGame->update($data);

        return response()->json($homeGame->fresh());
    }

    public function destroy(HomeGame $homeGame)
    {
        Storage::disk('public')->delete($homeGame->image_path);
        $homeGame->delete();

        return response()->json(['message' => 'Game deleted']);
    }
}

