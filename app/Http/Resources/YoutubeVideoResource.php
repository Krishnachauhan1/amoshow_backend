<?php

namespace App\Http\Resources;

use App\Models\Video;
use App\Models\VideoCollaborator;
use App\Models\User;
use App\Support\YoutubeFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Video */
class YoutubeVideoResource extends JsonResource
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

        $isSubscribed = false;
        if ($user && $channel) {
            $isSubscribed = $channel->subscribers()
                ->where('user_id', $user->id)
                ->exists();
        }

        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'description'    => $this->description,
            'channel'        => $channel?->name ?? 'Unknown',
            'channel_id'     => $this->channel_id,
            'channel_avatar' => YoutubeFormatter::storageUrl($channel?->avatar ?? $channel?->user?->avatar),
            'channel_subscribers' => YoutubeFormatter::subscribers((int) ($channel?->subscriber_count ?? 0)),
            'subscribers'    => YoutubeFormatter::subscribers((int) ($channel?->subscriber_count ?? 0)),
            'username'       => $channel ? '@' . explode('-', $channel->slug ?? 'channel')[0] : '@unknown',
            'views'          => YoutubeFormatter::views((int) $this->views_count),
            'views_count'    => (int) $this->views_count,
            'likes_count'    => (int) $this->likes_count,
            'like_count'     => (int) $this->likes_count,
            'is_liked'       => $isLiked,
            'is_subscribed'  => $isSubscribed,
            'time'           => YoutubeFormatter::timeAgo($this->created_at),
            'duration'       => YoutubeFormatter::duration($this->duration),
            'duration_seconds' => $this->duration,
            'thumb'          => YoutubeFormatter::storageUrl($this->thumbnail),
            'thumbnail'      => YoutubeFormatter::storageUrl($this->thumbnail),
            'video_url'      => YoutubeFormatter::storageUrl($this->video_path),
            'visibility'     => $this->visibility ?? 'public',
            'genre'          => $this->genre,
            'isPaid'         => (bool) $this->is_premium,
            'is_premium'     => (bool) $this->is_premium,
            'comments'       => (bool) $this->comments_enabled,
            'comments_enabled' => (bool) $this->comments_enabled,
            'comments_count' => (int) $this->comments_count,
            'downloadable'   => (bool) $this->downloadable,
            'hasPromotion'   => (bool) $this->has_paid_promotion,
            'has_promotion'  => (bool) $this->has_paid_promotion,
            'earnings'       => YoutubeFormatter::earnings((int) $this->earnings_paise),
            'earnings_paise' => (int) $this->earnings_paise,
            'is_collab'      => (bool) $this->is_collab,
            'is_collaborator_video' => (bool) (
                $user instanceof User
                && VideoCollaborator::userIsCollaborator($user->id, (int) $this->id)
            ),
            'ad_placement'   => $this->ad_placement ?? 'none',
            'status'         => $this->status,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
