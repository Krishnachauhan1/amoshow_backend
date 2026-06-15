<?php

namespace App\Http\Resources;

use App\Models\Video;
use App\Support\YoutubeFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Video */
class ShortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $channel = $this->channel;
        $user    = $request->user();

        $isLiked = false;
        if ($user && $this->relationLoaded('shortLikes')) {
            $isLiked = $this->shortLikes->contains('user_id', $user->id);
        } elseif ($user) {
            $isLiked = $this->shortLikes()->where('user_id', $user->id)->exists();
        }

        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'description'      => $this->description,
            'video_url'        => YoutubeFormatter::storageUrl($this->video_path),
            'thumbnail'        => YoutubeFormatter::storageUrl($this->thumbnail),
            'thumb'            => YoutubeFormatter::storageUrl($this->thumbnail),
            'duration'         => YoutubeFormatter::duration($this->duration),
            'duration_seconds' => $this->duration,
            'views'            => YoutubeFormatter::views((int) $this->views_count),
            'views_count'      => (int) $this->views_count,
            'likes'            => YoutubeFormatter::subscribers((int) $this->likes_count),
            'likes_count'      => (int) $this->likes_count,
            'is_liked'         => $isLiked,
            'time'             => YoutubeFormatter::timeAgo($this->created_at),
            'genre'            => $this->genre,
            'visibility'       => $this->visibility ?? 'public',
            'comments_enabled' => (bool) $this->comments_enabled,
            'comments_count'   => (int) $this->comments_count,
            'channel'          => [
                'id'       => $this->channel_id,
                'name'     => $channel?->name ?? 'Unknown',
                'username' => $channel ? '@' . explode('-', $channel->slug)[0] : '@unknown',
                'avatar'   => YoutubeFormatter::storageUrl($channel?->avatar ?? $channel?->user?->avatar),
                'subs'     => YoutubeFormatter::subscribers((int) ($channel?->subscriber_count ?? 0)),
            ],
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
