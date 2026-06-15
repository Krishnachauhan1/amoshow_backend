<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Video;
use App\Models\VideoCollaborator;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index()
{
    $channels = Channel::with('user')
        ->latest()
        ->paginate(10);

    return response()->json([
        'success' => true,
        'data' => $channels
    ]);
}
    public function store(Request $request)
    {
        if ($request->user()->channel) {
            return response()->json(['message' => 'Channel already exists'], 422);
        }

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'banner'      => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('banners', 'public');
        }

        $channel = $request->user()->channel()->create($data);

        return response()->json($channel, 201);
    }

    public function show(Channel $channel)
    {
        return response()->json(
            $channel->load(['videos', 'user'])
        );
    }

    public function update(Request $request, Channel $channel)
    {
        if ($request->user()->id !== $channel->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'banner'      => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('banners', 'public');
        }

        $channel->update($data);

        return response()->json($channel);
    }

    public function destroy(Request $request, Channel $channel)
    {
        if ($request->user()->id !== $channel->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $channel->delete();

        return response()->json(['message' => 'Channel deleted']);
    }

    public function myChannel(Request $request)
    {
        $channel = $request->user()->channel;

        if (!$channel) {
            return response()->json(['message' => 'No channel found'], 404);
        }

        $collabVideoIds = VideoCollaborator::approvedVideoIdsFor((int) $request->user()->id);

        $videos = Video::with('channel')
            ->youtube()
            ->where(function ($q) use ($channel, $collabVideoIds) {
                $q->where('channel_id', $channel->id);
                if ($collabVideoIds->isNotEmpty()) {
                    $q->orWhereIn('id', $collabVideoIds);
                }
            })
            ->latest()
            ->get();

        $payload = $channel->toArray();
        $payload['videos'] = $videos;

        return response()->json($payload);
    }
}