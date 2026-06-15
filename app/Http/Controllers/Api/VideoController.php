<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Support\VideoProbe;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $videos = Video::with(['channel', 'categories'])
            ->ott()
            ->where('status', 'published')
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate(20);

        return response()->json($videos);
    }

    public function show(Video $video)
    {
        return response()->json(
            $video->load(['channel', 'categories', 'episodes'])
        );
    }

    public function upload(Request $request)
    {
        $request->validate([
            'title'          => 'required|string|max:255',
            'video'          => 'required|file|mimetypes:video/mp4,video/avi|max:512000',
            'thumbnail'      => 'nullable|image|max:2048',
            'type'           => 'nullable|in:movie,series,episode',
            'is_premium'     => 'nullable|boolean',
            'episode_number' => 'nullable|integer',
            'series_id'      => 'nullable|exists:videos,id',
            'categories'       => 'nullable|array',
            'categories.*'     => 'exists:categories,id',
            'duration_seconds' => 'nullable|integer|min:1',
        ]);

        $channel = $request->user()->channel;

        if (!$channel) {
            return response()->json(['message' => 'Create a channel first'], 422);
        }

        $videoPath = $request->file('video')->store('videos', 'public');
        $thumbPath = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store('thumbnails', 'public')
            : null;

        $duration = $request->integer('duration_seconds')
            ?: VideoProbe::durationSeconds(storage_path('app/public/' . $videoPath));

        $video = $channel->videos()->create([
            'title'          => $request->title,
            'description'    => $request->description,
            'video_path'     => $videoPath,
            'thumbnail'      => $thumbPath,
            'type'           => $request->type ?? 'movie',
            'platform'       => 'ott',
            'duration'       => $duration,
            'is_premium'     => $request->boolean('is_premium'),
            'episode_number' => $request->episode_number,
            'series_id'      => $request->series_id,
            'status'         => 'published',
        ]);

        if ($request->categories) {
            $video->categories()->sync($request->categories);
        }

        return response()->json($video->load('categories'), 201);
    }

    public function update(Request $request, Video $video)
    {
        if ($request->user()->id !== $video->channel->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_premium'  => 'nullable|boolean',
            'status'      => 'nullable|in:published,draft',
            'categories'  => 'nullable|array',
            'categories.*'=> 'exists:categories,id',
        ]);

        $video->update($data);

        if ($request->has('categories')) {
            $video->categories()->sync($request->categories);
        }

        return response()->json($video->load('categories'));
    }

    public function destroy(Request $request, Video $video)
    {
        if ($request->user()->id !== $video->channel->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        \Storage::disk('public')->delete($video->video_path);
        if ($video->thumbnail) {
            \Storage::disk('public')->delete($video->thumbnail);
        }

        $video->delete();

        return response()->json(['message' => 'Video deleted']);
    }

    public function stream(Request $request, Video $video)
    {
        if ($video->is_premium && !$request->user()->hasActivePlan()) {
            return response()->json(['message' => 'Subscribe to watch this content'], 403);
        }

        $path = storage_path('app/public/' . $video->video_path);

        if (!file_exists($path)) {
            abort(404);
        }

        $size    = filesize($path);
        $start   = 0;
        $end     = $size - 1;
        $headers = [
            'Content-Type'  => 'video/mp4',
            'Accept-Ranges' => 'bytes',
        ];

        if ($request->hasHeader('Range')) {
            preg_match('/bytes=(\d+)-(\d*)/', $request->header('Range'), $matches);
            $start = (int) $matches[1];
            $end   = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $size - 1;

            $headers['Content-Range']  = "bytes $start-$end/$size";
            $headers['Content-Length'] = $end - $start + 1;

            return response()->stream(function () use ($path, $start, $end) {
                $fp        = fopen($path, 'rb');
                $remaining = $end - $start + 1;
                fseek($fp, $start);
                while (!feof($fp) && $remaining > 0) {
                    $chunk = fread($fp, min(8192, $remaining));
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
                fclose($fp);
            }, 206, $headers);
        }

        $headers['Content-Length'] = $size;

        return response()->file($path, $headers);
    }
}