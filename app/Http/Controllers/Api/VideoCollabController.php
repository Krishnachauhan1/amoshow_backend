<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoCollaborator;
use Illuminate\Http\Request;

class VideoCollabController extends Controller
{
    public function incoming(Request $request)
    {
        if (! VideoCollaborator::isAvailable()) {
            return response()->json(['data' => []]);
        }

        $items = VideoCollaborator::with(['video.channel', 'owner'])
            ->where('collaborator_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (VideoCollaborator $c) => $this->formatRequest($c));

        return response()->json(['data' => $items]);
    }

    public function approve(Request $request, VideoCollaborator $collaboration)
    {
        if ($collaboration->collaborator_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$collaboration->isPending()) {
            return response()->json(['message' => 'Request already handled'], 422);
        }

        $collaboration->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Collaboration approved',
            'data'    => $this->formatRequest($collaboration->fresh(['video.channel', 'owner'])),
        ]);
    }

    public function reject(Request $request, VideoCollaborator $collaboration)
    {
        if ($collaboration->collaborator_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$collaboration->isPending()) {
            return response()->json(['message' => 'Request already handled'], 422);
        }

        $collaboration->update(['status' => 'rejected']);

        return response()->json(['message' => 'Collaboration declined']);
    }

    public function notifications(Request $request)
    {
        $items = $request->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->data['type'] ?? class_basename($n->type),
                'data'       => $n->data,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $items]);
    }

    public function markNotificationRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => 'Marked as read']);
    }

    private function formatRequest(VideoCollaborator $c): array
    {
        $video = $c->video;
        $owner = $c->owner;

        return [
            'id'                 => $c->id,
            'status'             => $c->status,
            'video_id'           => $video?->id,
            'video_title'        => $video?->title,
            'video_thumbnail'    => $video?->thumbnail
                ? \App\Support\YoutubeFormatter::storageUrl($video->thumbnail)
                : null,
            'owner_id'           => $owner?->id,
            'owner_name'         => $owner?->name,
            'owner_email'        => $owner?->email,
            'collaborator_email' => $c->collaborator_email,
            'created_at'         => $c->created_at?->toIso8601String(),
        ];
    }
}
