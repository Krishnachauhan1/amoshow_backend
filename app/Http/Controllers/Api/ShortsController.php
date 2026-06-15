<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShortResource;
use App\Models\Channel;
use App\Models\ShortLike;
use App\Models\Video;
use App\Support\VideoProbe;
use App\Support\VideoThumbnail;
use Illuminate\Http\Request;

class ShortsController extends Controller
{
    private const MAX_DURATION_SECONDS = 60;

    public function feed(Request $request)
    {
        $request->validate([
            'genre'    => 'nullable|string|max:50',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:30',
        ]);

        $user = $request->user();

        $videos = Video::with(['channel.user', 'shortLikes'])
            ->shorts()
            ->published()
            ->when($request->genre, fn ($q) => $q->where('genre', $request->genre))
            ->when($user, function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('visibility', 'public')
                        ->orWhereHas('channel', fn ($c) => $c->where('user_id', $user->id));
                });
            }, fn ($q) => $q->where('visibility', 'public'))
            ->whereHas('channel', fn ($q) => $q->where('is_private', false))
            ->inRandomOrder()
            ->paginate($request->per_page ?? 15);

        return ShortResource::collection($videos);
    }

    public function trending(Request $request)
    {
        $videos = Video::with(['channel.user', 'shortLikes'])
            ->shorts()
            ->published()
            ->where('visibility', 'public')
            ->whereHas('channel', fn ($q) => $q->where('is_private', false))
            ->orderByDesc('views_count')
            ->limit($request->limit ?? 20)
            ->get();

        return ShortResource::collection($videos);
    }

    public function show(Request $request, Video $video)
    {
        if ($video->platform !== 'shorts') {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($video->visibility === 'private' && $request->user()?->id !== $video->channel->user_id) {
            return response()->json(['message' => 'Private short'], 403);
        }

        return new ShortResource($video->load(['channel.user', 'shortLikes']));
    }

    public function channelShorts(Channel $channel)
    {
        if ($channel->is_private) {
            return response()->json(['message' => 'Channel is private'], 403);
        }

        $videos = $channel->videos()
            ->shorts()
            ->published()
            ->where('visibility', 'public')
            ->with(['channel.user', 'shortLikes'])
            ->latest()
            ->paginate(20);

        return ShortResource::collection($videos);
    }

    public function search(Request $request)
    {
        $request->validate([
            'q'    => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $q = $request->q;

        $videos = Video::with(['channel.user', 'shortLikes'])
            ->shorts()
            ->published()
            ->where('visibility', 'public')
            ->whereHas('channel', fn ($query) => $query->where('is_private', false))
            ->when($q, fn ($query) => $query->where(fn ($sub) =>
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('channel', fn ($c) => $c->where('name', 'like', "%{$q}%"))
            ))
            ->latest()
            ->paginate(20);

        return ShortResource::collection($videos);
    }

    public function recordView(Request $request, Video $video)
    {
        if ($video->platform !== 'shorts') {
            return response()->json(['message' => 'Not a short'], 404);
        }

        if ($video->visibility === 'private' && $request->user()?->id !== $video->channel->user_id) {
            return response()->json(['message' => 'Private short'], 403);
        }

        $video->increment('views_count');

        return response()->json([
            'views_count' => (int) $video->views_count,
        ]);
    }

    public function toggleLike(Request $request, Video $video)
    {
        if ($video->platform !== 'shorts') {
            return response()->json(['message' => 'Not a short'], 404);
        }

        $existing = ShortLike::where('user_id', $request->user()->id)
            ->where('video_id', $video->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $video->decrement('likes_count');

            return response()->json([
                'liked'       => false,
                'likes_count' => (int) $video->fresh()->likes_count,
            ]);
        }

        ShortLike::create([
            'user_id'  => $request->user()->id,
            'video_id' => $video->id,
        ]);
        $video->increment('likes_count');

        return response()->json([
            'liked'       => true,
            'likes_count' => (int) $video->fresh()->likes_count,
        ]);
    }

    public function myShorts(Request $request)
    {
        $channel = $request->user()->channel;

        if (!$channel) {
            return response()->json(['data' => []]);
        }

        $videos = $channel->videos()
            ->shorts()
            ->with(['channel.user', 'shortLikes'])
            ->latest()
            ->paginate(20);

        return ShortResource::collection($videos);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'title'            => 'required|string|max:100',
            'description'      => 'nullable|string|max:500',
            'video'            => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:102400',
            'thumbnail'        => 'nullable|image|max:4096',
            'visibility'       => 'nullable|in:public,private,unlisted',
            'genre'            => 'nullable|string|max:50',
            'duration_seconds' => 'nullable|integer|min:1|max:' . self::MAX_DURATION_SECONDS,
            'comments_enabled' => 'nullable|boolean',
        ]);

        $channel = $request->user()->channel;

        if (!$channel) {
            return response()->json(['message' => 'Create a channel first'], 422);
        }

        $videoPath = $request->file('video')->store('shorts', 'public');
        $fullPath  = storage_path('app/public/' . $videoPath);

        $duration = $request->integer('duration_seconds')
            ?: VideoProbe::durationSeconds($fullPath);

        $thumbPath = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store('thumbnails', 'public')
            : VideoThumbnail::generateFromVideo($fullPath);

        if ($duration && $duration > self::MAX_DURATION_SECONDS) {
            \Storage::disk('public')->delete($videoPath);
            if ($thumbPath) {
                \Storage::disk('public')->delete($thumbPath);
            }

            return response()->json([
                'message' => 'Shorts must be ' . self::MAX_DURATION_SECONDS . ' seconds or less',
            ], 422);
        }

        $video = $channel->videos()->create([
            'title'            => $request->title,
            'description'      => $request->description,
            'video_path'       => $videoPath,
            'thumbnail'        => $thumbPath,
            'type'             => 'movie',
            'platform'         => 'shorts',
            'visibility'       => $request->visibility ?? 'public',
            'genre'            => $request->genre ?? 'Other',
            'duration'         => $duration,
            'comments_enabled' => $request->boolean('comments_enabled', true),
            'downloadable'     => false,
            'status'           => 'published',
        ]);

        return (new ShortResource($video->load(['channel.user', 'shortLikes'])))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Video $video)
    {
        if ($video->platform !== 'shorts') {
            return response()->json(['message' => 'Not a short'], 404);
        }

        if ($request->user()->id !== $video->channel->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        \Storage::disk('public')->delete($video->video_path);
        if ($video->thumbnail) {
            \Storage::disk('public')->delete($video->thumbnail);
        }

        $video->shortLikes()->delete();
        $video->delete();

        return response()->json(['message' => 'Short deleted']);
    }

    public function genres()
    {
        return response()->json([
            'genres' => [
                'Music', 'Gaming', 'Comedy', 'Sports', 'Dance',
                'Food', 'Travel', 'Fashion', 'Education', 'News',
                'Entertainment', 'Lifestyle', 'Science', 'Other',
            ],
        ]);
    }

}
