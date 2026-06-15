<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\YoutubeCommentResource;
use App\Models\Video;
use App\Models\VideoComment;
use Illuminate\Http\Request;

class YoutubeCommentController extends Controller
{
    public function index(Request $request, Video $video)
    {
        if (!$this->isCommentable($video)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (!$this->canViewVideo($request, $video)) {
            return response()->json(['message' => 'Private video'], 403);
        }

        $comments = VideoComment::with('user')
            ->where('video_id', $video->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return YoutubeCommentResource::collection($comments);
    }

    public function store(Request $request, Video $video)
    {
        if (!$this->isCommentable($video)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (!$this->canViewVideo($request, $video)) {
            return response()->json(['message' => 'Private video'], 403);
        }

        if (!$video->comments_enabled) {
            return response()->json(['message' => 'Comments are disabled for this video'], 403);
        }

        $data = $request->validate([
            'body' => 'required|string|min:1|max:2000',
        ]);

        $comment = VideoComment::create([
            'video_id' => $video->id,
            'user_id'  => $request->user()->id,
            'body'     => trim($data['body']),
        ]);

        $video->increment('comments_count');

        return response()->json([
            'data'            => (new YoutubeCommentResource($comment->load('user')))->resolve($request),
            'comments_count'  => (int) $video->fresh()->comments_count,
        ], 201);
    }

    public function destroy(Request $request, Video $video, VideoComment $comment)
    {
        if (!$this->isCommentable($video) || $comment->video_id !== $video->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $request->user();
        $isOwner = $comment->user_id === $user->id;
        $isChannelOwner = $video->channel?->user_id === $user->id;

        if (!$isOwner && !$isChannelOwner) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comment->delete();
        $video->decrement('comments_count');

        return response()->json([
            'message'         => 'Comment deleted',
            'comments_count'  => (int) max(0, $video->fresh()->comments_count),
        ]);
    }

    private function isCommentable(Video $video): bool
    {
        return in_array($video->platform, ['youtube', 'shorts'], true);
    }

    private function canViewVideo(Request $request, Video $video): bool
    {
        if ($video->visibility !== 'private') {
            return true;
        }

        return $request->user()?->id === $video->channel?->user_id;
    }
}
