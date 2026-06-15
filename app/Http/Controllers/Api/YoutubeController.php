<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\YoutubeVideoResource;
use App\Models\AdCampaign;
use App\Models\Channel;
use App\Models\ChannelSubscription;
use App\Models\ExploreSection;
use App\Models\Plan;
use App\Models\ShortLike;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoCollaborator;
use App\Notifications\VideoCollabInviteNotification;
use App\Models\YoutubeCategory;
use App\Support\VideoProbe;
use App\Support\YoutubeFormatter;
use Illuminate\Http\Request;

class YoutubeController extends Controller
{
    public function feed(Request $request)
    {
        $request->validate([
            'category' => 'nullable|string|max:50',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);

        $category = $request->category;
        if ($category === 'All' || $category === '') {
            $category = null;
        } else {
            $category = match ($category) {
                'Tech' => 'Technology',
                default => $category,
            };
        }

        $user = $request->user();

        $videos = Video::with(['channel', 'shortLikes'])
            ->youtube()
            ->published()
            ->when($category, fn ($q) => $q->where('genre', $category))
            ->when($user, function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('visibility', 'public')
                        ->orWhereHas('channel', fn ($c) => $c->where('user_id', $user->id));
                });
            }, fn ($q) => $q->where('visibility', 'public'))
            ->whereHas('channel', function ($q) use ($user) {
                $q->where('is_private', false);
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->latest()
            ->paginate($request->per_page ?? 20);

        return YoutubeVideoResource::collection($videos);
    }

    public function explore()
    {
        $sections = ExploreSection::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($s) => [
                'emoji' => $s->emoji,
                'title' => $s->title,
                'slug'  => $s->slug,
            ]);

        $trending = Video::with('channel')
            ->youtube()
            ->published()
            ->where('visibility', 'public')
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        return response()->json([
            'sections' => $sections,
            'trending' => YoutubeVideoResource::collection($trending),
        ]);
    }

    public function exploreSection(string $slug)
    {
        $videos = Video::with(['channel', 'shortLikes'])
            ->youtube()
            ->published()
            ->where('visibility', 'public')
            ->when($slug !== 'trending', fn ($q) => $q->where('genre', ucfirst($slug)))
            ->when($slug === 'trending', fn ($q) => $q->orderByDesc('views_count'))
            ->when($slug !== 'trending', fn ($q) => $q->latest())
            ->paginate(20);

        return YoutubeVideoResource::collection($videos);
    }

    public function categories()
    {
        $categories = YoutubeCategory::orderBy('sort_order')->pluck('name');

        return response()->json([
            'categories' => $categories->isEmpty()
                ? ['All', 'Music', 'Gaming', 'News', 'Live', 'Comedy', 'Tech', 'Sports', 'Movies']
                : $categories->prepend('All')->unique()->values(),
        ]);
    }

    public function genres()
    {
        return response()->json([
            'genres' => [
                'Technology', 'Music', 'Gaming', 'Sports', 'Comedy',
                'Travel', 'Food', 'Health', 'Education', 'News',
                'Entertainment', 'Finance', 'Lifestyle', 'Science', 'Other',
            ],
        ]);
    }

    public function channels(Request $request)
    {
        $channels = Channel::with('user')
            ->where('is_private', false)
            ->orderByDesc('subscriber_count')
            ->paginate($request->per_page ?? 20);

        $data = $channels->through(fn ($ch) => [
            'id'       => $ch->id,
            'name'     => $ch->name,
            'username' => '@' . explode('-', $ch->slug)[0],
            'initial'  => strtoupper(substr($ch->name, 0, 1)),
            'subs'     => YoutubeFormatter::subscribers((int) $ch->subscriber_count),
            'avatar'   => YoutubeFormatter::storageUrl($ch->avatar ?? $ch->user?->avatar),
            'banner'   => YoutubeFormatter::storageUrl($ch->banner),
        ]);

        return response()->json($data);
    }

    public function subscriptions(Request $request)
    {
        $channelIds = ChannelSubscription::where('user_id', $request->user()->id)
            ->pluck('channel_id');

        $videos = Video::with(['channel', 'shortLikes'])
            ->youtube()
            ->published()
            ->whereIn('channel_id', $channelIds)
            ->where('visibility', 'public')
            ->latest()
            ->paginate(20);

        $channels = Channel::whereIn('id', $channelIds)
            ->get()
            ->map(fn ($ch) => [
                'id'       => $ch->id,
                'name'     => $ch->name,
                'username' => '@' . explode('-', $ch->slug)[0],
                'initial'  => strtoupper(substr($ch->name, 0, 1)),
                'subs'     => YoutubeFormatter::subscribers((int) $ch->subscriber_count),
            ]);

        return response()->json([
            'channels' => $channels,
            'videos'   => YoutubeVideoResource::collection($videos),
        ]);
    }

    public function subscribe(Request $request, Channel $channel)
    {
        if ($channel->user_id === $request->user()->id) {
            return response()->json(['message' => 'Cannot subscribe to your own channel'], 422);
        }

        $sub = ChannelSubscription::firstOrCreate([
            'user_id'    => $request->user()->id,
            'channel_id' => $channel->id,
        ]);

        if ($sub->wasRecentlyCreated) {
            $channel->increment('subscriber_count');
        }

        $channel->refresh();

        return response()->json([
            'message'          => 'Subscribed',
            'subscribed'       => true,
            'subscriber_count' => (int) $channel->subscriber_count,
            'subscribers'      => YoutubeFormatter::subscribers((int) $channel->subscriber_count),
        ]);
    }

    public function unsubscribe(Request $request, Channel $channel)
    {
        $deleted = ChannelSubscription::where('user_id', $request->user()->id)
            ->where('channel_id', $channel->id)
            ->delete();

        if ($deleted) {
            $channel->decrement('subscriber_count');
        }

        $channel->refresh();

        return response()->json([
            'message'          => 'Unsubscribed',
            'subscribed'       => false,
            'subscriber_count' => (int) $channel->subscriber_count,
            'subscribers'      => YoutubeFormatter::subscribers((int) $channel->subscriber_count),
        ]);
    }

    public function profileStats(Request $request)
    {
        $user    = $request->user();
        $channel = $user->channel;

        if (!$channel) {
            return response()->json([
                'videos'      => 0,
                'subscribers' => '0',
                'views'       => '0',
                'has_channel' => false,
            ]);
        }

        $videoCount = $channel->videos()->youtube()->count();
        $totalViews = $channel->videos()->youtube()->sum('views_count');

        return response()->json([
            'videos'      => $videoCount,
            'subscribers' => YoutubeFormatter::subscribers((int) $channel->subscriber_count),
            'views'       => YoutubeFormatter::subscribers((int) $totalViews),
            'has_channel' => true,
            'channel'     => [
                'id'         => $channel->id,
                'name'       => $channel->name,
                'username'   => '@' . explode('-', $channel->slug)[0],
                'is_private' => (bool) $channel->is_private,
            ],
            'user' => [
                'name'   => $user->name,
                'email'  => $user->email,
                'avatar' => YoutubeFormatter::storageUrl($user->avatar),
            ],
        ]);
    }

    public function profileEarnings(Request $request)
    {
        $channel = $request->user()->channel;

        if (!$channel) {
            return response()->json(['total' => '₹0', 'breakdown' => []]);
        }

        $videos = $channel->videos()->youtube()->get();

        $totalPaise = $videos->sum('earnings_paise');

        $breakdown = $videos->map(fn ($v) => [
            'title'    => $v->title,
            'earnings' => YoutubeFormatter::earnings((int) $v->earnings_paise),
            'views'    => YoutubeFormatter::views((int) $v->views_count),
        ]);

        return response()->json([
            'total'     => YoutubeFormatter::earnings((int) $totalPaise),
            'breakdown' => $breakdown,
        ]);
    }

    public function toggleChannelPrivacy(Request $request)
    {
        $channel = $request->user()->channel;

        if (!$channel) {
            return response()->json(['message' => 'Create a channel first'], 404);
        }

        $channel->update(['is_private' => !$channel->is_private]);

        return response()->json([
            'is_private' => (bool) $channel->is_private,
            'message'    => $channel->is_private ? 'Channel is now private' : 'Channel is now public',
        ]);
    }

    public function myVideos(Request $request)
    {
        $channel = $request->user()->channel;

        if (!$channel) {
            return response()->json(['data' => []]);
        }

        $videos = $channel->videos()
            ->youtube()
            ->latest()
            ->paginate(20);

        return YoutubeVideoResource::collection($videos);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'video'               => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/3gpp,video/3gpp2,video/webm|max:512000',
            'thumbnail'           => 'nullable|image|max:4096',
            'visibility'          => 'nullable|in:public,private,unlisted',
            'genre'               => 'nullable|string|max:50',
            'is_premium'          => 'nullable|boolean',
            'comments_enabled'    => 'nullable|boolean',
            'downloadable'        => 'nullable|boolean',
            'is_collab'           => 'nullable|boolean',
            'collab_email'        => 'nullable|email|max:255',
            'ad_placement'        => 'nullable|in:none,pre-roll,mid-roll,banner',
            'has_paid_promotion'  => 'nullable|boolean',
            'duration_seconds'    => 'nullable|integer|min:1',
        ]);

        $channel = $request->user()->channel;

        if (!$channel) {
            $channel = $request->user()->channel()->create([
                'name'        => trim($request->user()->name) !== ''
                    ? $request->user()->name . "'s Channel"
                    : 'My Channel',
                'description' => '',
            ]);
        }

        $videoPath = $request->file('video')->store('videos', 'public');
        $thumbPath = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store('thumbnails', 'public')
            : null;

        $duration = $request->integer('duration_seconds')
            ?: VideoProbe::durationSeconds(storage_path('app/public/' . $videoPath));

        $video = $channel->videos()->create([
            'title'               => $request->title,
            'description'         => $request->description,
            'video_path'          => $videoPath,
            'thumbnail'           => $thumbPath,
            'type'                => 'movie',
            'platform'            => 'youtube',
            'visibility'          => $request->visibility ?? 'public',
            'genre'               => $request->genre ?? 'Other',
            'duration'            => $duration,
            'is_premium'          => $request->boolean('is_premium'),
            'comments_enabled'    => $request->boolean('comments_enabled', true),
            'downloadable'        => $request->boolean('downloadable', true),
            'is_collab'           => $request->boolean('is_collab'),
            'ad_placement'        => $request->ad_placement ?? 'none',
            'has_paid_promotion'  => $request->boolean('has_paid_promotion'),
            'status'              => 'published',
        ]);

        if ($request->boolean('is_collab')) {
            $collabEmail = strtolower(trim((string) $request->collab_email));
            if ($collabEmail === '') {
                return response()->json(['message' => 'Collaborator email is required'], 422);
            }

            $invitee = User::whereRaw('LOWER(email) = ?', [$collabEmail])->first();
            if (!$invitee) {
                return response()->json(['message' => 'No user found with that email'], 422);
            }
            if ($invitee->id === $request->user()->id) {
                return response()->json(['message' => 'You cannot collaborate with yourself'], 422);
            }

            $collaboration = VideoCollaborator::create([
                'video_id'             => $video->id,
                'owner_user_id'        => $request->user()->id,
                'collaborator_user_id' => $invitee->id,
                'collaborator_email'   => $invitee->email,
                'status'               => 'pending',
            ]);

            $invitee->notify(new VideoCollabInviteNotification($collaboration));
        }

        return (new YoutubeVideoResource($video->load('channel')))
            ->response()
            ->setStatusCode(201);
    }

    public function recordView(Request $request, Video $video)
    {
        if ($video->platform !== 'youtube') {
            return response()->json(['message' => 'Not a YouTube video'], 404);
        }

        if ($video->visibility === 'private' && $request->user()?->id !== $video->channel->user_id) {
            return response()->json(['message' => 'Private video'], 403);
        }

        $video->increment('views_count');

        $views = (int) $video->views_count;
        $earningsPaise = (int) round($views * 0.35);
        $video->update(['earnings_paise' => $earningsPaise]);

        return response()->json([
            'views_count' => $views,
            'views'       => YoutubeFormatter::views($views),
            'earnings'    => YoutubeFormatter::earnings($earningsPaise),
        ]);
    }

    public function show(Video $video)
    {
        if ($video->platform !== 'youtube') {
            return response()->json(['message' => 'Not found'], 404);
        }

        return new YoutubeVideoResource($video->load(['channel', 'shortLikes']));
    }

    public function showChannel(Request $request, Channel $channel)
    {
        if ($channel->is_private && $request->user()?->id !== $channel->user_id) {
            return response()->json(['message' => 'Private channel'], 403);
        }

        $user = $request->user();
        $isSubscribed = $user
            ? ChannelSubscription::where('user_id', $user->id)
                ->where('channel_id', $channel->id)
                ->exists()
            : false;

        $channel->load('user');

        $videoCount = $channel->videos()->youtube()->published()->count();
        $shortsCount = $channel->videos()->shorts()->published()->count();
        $totalViews = (int) $channel->videos()->sum('views_count');

        $videos = $this->channelVideosQuery($channel)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => [
                'id'                => $channel->id,
                'name'              => $channel->name,
                'username'          => '@' . explode('-', $channel->slug)[0],
                'description'       => $channel->description,
                'avatar'            => YoutubeFormatter::storageUrl($channel->avatar ?? $channel->user?->avatar),
                'banner'            => YoutubeFormatter::storageUrl($channel->banner),
                'subscribers'       => YoutubeFormatter::subscribers((int) $channel->subscriber_count),
                'subscriber_count'  => (int) $channel->subscriber_count,
                'video_count'       => $videoCount,
                'shorts_count'      => $shortsCount,
                'total_views'       => YoutubeFormatter::views($totalViews),
                'total_views_count' => $totalViews,
                'page_status'       => $channel->is_private ? 'private' : 'public',
                'is_private'        => (bool) $channel->is_private,
                'is_subscribed'     => $isSubscribed,
                'joined'            => YoutubeFormatter::timeAgo($channel->created_at),
                'created_at'        => $channel->created_at?->toIso8601String(),
            ],
            'videos' => YoutubeVideoResource::collection($videos),
        ]);
    }

    public function likeVideo(Request $request, Video $video)
    {
        if ($video->platform !== 'youtube') {
            return response()->json(['message' => 'Not found'], 404);
        }

        $existing = ShortLike::where('user_id', $request->user()->id)
            ->where('video_id', $video->id)
            ->first();

        if (!$existing) {
            ShortLike::create([
                'user_id'  => $request->user()->id,
                'video_id' => $video->id,
            ]);
            $video->increment('likes_count');
        }

        return response()->json([
            'data' => [
                'is_liked'   => true,
                'like_count' => (int) $video->fresh()->likes_count,
            ],
        ]);
    }

    public function unlikeVideo(Request $request, Video $video)
    {
        if ($video->platform !== 'youtube') {
            return response()->json(['message' => 'Not found'], 404);
        }

        $deleted = ShortLike::where('user_id', $request->user()->id)
            ->where('video_id', $video->id)
            ->delete();

        if ($deleted) {
            $video->decrement('likes_count');
        }

        return response()->json([
            'data' => [
                'is_liked'   => false,
                'like_count' => (int) max(0, $video->fresh()->likes_count),
            ],
        ]);
    }

    public function adHistory(Request $request)
    {
        $ads = AdCampaign::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn ($ad) => [
                'id'    => $ad->id,
                'title' => $ad->title,
                'type'  => ucfirst(str_replace('-', '-', $ad->type)),
                'spent' => YoutubeFormatter::earnings((int) $ad->spent_paise),
                'views' => YoutubeFormatter::subscribers((int) $ad->impressions) . '',
                'date'  => $ad->created_at?->format('M j'),
            ]);

        return response()->json(['data' => $ads]);
    }

    public function createAd(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:pre-roll,mid-roll,banner',
            'budget'       => 'required|numeric|min:100',
            'video_id'     => 'nullable|exists:videos,id',
            'duration_days'=> 'nullable|integer|min:1|max:90',
        ]);

        $budgetPaise = (int) ($data['budget'] * 100);

        $ad = AdCampaign::create([
            'user_id'      => $request->user()->id,
            'video_id'     => $data['video_id'] ?? null,
            'title'        => $data['title'],
            'type'         => $data['type'],
            'budget_paise' => $budgetPaise,
            'spent_paise'  => 0,
            'impressions'  => 0,
            'status'       => 'active',
            'starts_at'    => now(),
            'ends_at'      => now()->addDays($data['duration_days'] ?? 7),
        ]);

        if ($ad->video_id) {
            Video::where('id', $ad->video_id)->update(['has_paid_promotion' => true]);
        }

        return response()->json([
            'message' => 'Ad campaign created',
            'ad'      => [
                'id'    => $ad->id,
                'title' => $ad->title,
                'type'  => ucfirst($ad->type),
                'spent' => '₹0',
                'views' => '0',
                'date'  => $ad->created_at->format('M j'),
            ],
        ], 201);
    }

    public function membershipPlans()
    {
        $plans = Plan::orderBy('price')->get();

        if ($plans->isEmpty()) {
            return response()->json([
                'plans' => [
                    ['name' => 'Basic', 'price' => '₹99/mo', 'desc' => 'Ad-free viewing + 720p downloads', 'icon' => '⭐'],
                    ['name' => 'Pro', 'price' => '₹299/mo', 'desc' => 'All Basic + 4K + Priority support', 'icon' => '🚀'],
                    ['name' => 'Pro + OTT', 'price' => '₹499/mo', 'desc' => 'Pro + Full OTT library access', 'icon' => '👑'],
                ],
            ]);
        }

        $icons = ['⭐', '🚀', '👑', '💎'];

        return response()->json([
            'plans' => $plans->values()->map(fn ($p, $i) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'price' => '₹' . number_format($p->price) . '/mo',
                'desc'  => is_array($p->features) ? implode(' + ', $p->features) : ($p->features ?? ''),
                'icon'  => $icons[$i % count($icons)],
            ]),
        ]);
    }

    public function search(Request $request)
    {
        $request->validate([
            'q'      => 'nullable|string|max:100',
            'filter' => 'nullable|in:All,Videos,Channels,Songs,Live,Playlists',
            'page'   => 'nullable|integer|min:1',
        ]);

        $q = $request->q;

        if ($request->filter === 'Channels') {
            $channels = Channel::where('is_private', false)
                ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->limit(20)
                ->get()
                ->map(fn ($ch) => [
                    'type'     => 'channel',
                    'id'       => $ch->id,
                    'name'     => $ch->name,
                    'username' => '@' . explode('-', $ch->slug)[0],
                    'subs'     => YoutubeFormatter::subscribers((int) $ch->subscriber_count),
                ]);

            return response()->json(['channels' => $channels, 'videos' => []]);
        }

        $videos = Video::with(['channel', 'shortLikes'])
            ->youtube()
            ->published()
            ->where('visibility', 'public')
            ->when($q, fn ($query) => $query->where(fn ($sub) =>
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
            ))
            ->latest()
            ->paginate(20);

        return response()->json([
            'videos'   => YoutubeVideoResource::collection($videos),
            'channels' => [],
        ]);
    }

    private function channelVideosQuery(Channel $channel)
    {
        $collabVideoIds = VideoCollaborator::approvedVideoIdsFor((int) $channel->user_id);

        return Video::with(['channel', 'shortLikes'])
            ->youtube()
            ->published()
            ->where('visibility', 'public')
            ->where(function ($q) use ($channel, $collabVideoIds) {
                $q->where('channel_id', $channel->id);
                if ($collabVideoIds->isNotEmpty()) {
                    $q->orWhereIn('id', $collabVideoIds);
                }
            });
    }
}
