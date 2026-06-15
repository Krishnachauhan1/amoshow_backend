<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\WatchHistory;
use Illuminate\Http\Request;

class WatchHistoryController extends Controller
{
    public function update(Request $request, Video $video)
    {
        $request->validate([
            'watched_seconds' => 'required|integer|min:0',
            'total_seconds'   => 'required|integer|min:1',
        ]);

        $completed = $request->watched_seconds >= ($request->total_seconds * 0.9);

        WatchHistory::updateOrCreate(
            [
                'user_id'  => $request->user()->id,
                'video_id' => $video->id,
            ],
            [
                'watched_seconds' => $request->watched_seconds,
                'total_seconds'   => $request->total_seconds,
                'completed'       => $completed,
                'last_watched_at' => now(),
            ]
        );

        $progress = (int) (($request->watched_seconds / $request->total_seconds) * 100);

        return response()->json(['progress' => $completed ? 100 : $progress]);
    }

    public function continueWatching(Request $request)
    {
        $history = $request->user()
            ->watchHistory()
            ->with(['video.channel', 'video.categories'])
            ->where('completed', false)
            ->latest('last_watched_at')
            ->take(20)
            ->get()
            ->map(fn($h) => [
                'video'           => $h->video,
                'watched_seconds' => $h->watched_seconds,
                'total_seconds'   => $h->total_seconds,
                'progress'        => $h->progress,
                'last_watched_at' => $h->last_watched_at,
            ]);

        return response()->json($history);
    }

    public function index(Request $request)
    {
        $history = $request->user()
            ->watchHistory()
            ->with('video.channel')
            ->paginate(30);

        return response()->json($history);
    }

    public function getProgress(Request $request, Video $video)
    {
        $history = WatchHistory::where('user_id', $request->user()->id)
            ->where('video_id', $video->id)
            ->first();

        return response()->json([
            'watched_seconds' => $history?->watched_seconds ?? 0,
            'progress'        => $history?->progress ?? 0,
            'completed'       => $history?->completed ?? false,
        ]);
    }

    public function destroy(Request $request, Video $video)
    {
        WatchHistory::where('user_id', $request->user()->id)
            ->where('video_id', $video->id)
            ->delete();

        return response()->json(['message' => 'Removed from history']);
    }
}