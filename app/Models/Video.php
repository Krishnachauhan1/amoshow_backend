<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id', 'title', 'description', 'video_path',
        'thumbnail', 'type', 'platform', 'visibility', 'genre',
        'duration', 'is_premium', 'price', 'comments_enabled', 'downloadable',
        'is_collab', 'ad_placement', 'has_paid_promotion',
        'views_count', 'likes_count', 'comments_count', 'earnings_paise',
        'status', 'series_id', 'episode_number',
    ];

    protected $casts = [
        'is_premium'          => 'boolean',
        'price'               => 'decimal:2',
        'comments_enabled'    => 'boolean',
        'downloadable'        => 'boolean',
        'is_collab'           => 'boolean',
        'has_paid_promotion'  => 'boolean',
    ];

    public function scopeYoutube($query)
    {
        return $query->where('platform', 'youtube');
    }

    public function scopeShorts($query)
    {
        return $query->where('platform', 'shorts');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeOtt($query)
    {
        return $query->where('platform', 'ott');
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function episodes()
    {
        return $this->hasMany(Video::class, 'series_id');
    }

    public function watchHistories()
    {
        return $this->hasMany(WatchHistory::class);
    }

    public function shortLikes()
    {
        return $this->hasMany(ShortLike::class);
    }

    public function comments()
    {
        return $this->hasMany(VideoComment::class);
    }

    public function collaborators()
    {
        return $this->hasMany(VideoCollaborator::class);
    }

    public function purchases()
    {
        return $this->hasMany(VideoPurchase::class);
    }

    public function isPaidContent(): bool
    {
        return (bool) $this->is_premium && (float) $this->price > 0;
    }

    public function userCanWatch(?User $user): bool
    {
        if (! $this->isPaidContent()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($this->relationLoaded('channel') && $this->channel?->user_id === $user->id) {
            return true;
        }

        if ($this->channel && $this->channel->user_id === $user->id) {
            return true;
        }

        return VideoPurchase::where('user_id', $user->id)
            ->where('video_id', $this->id)
            ->where('status', 'success')
            ->exists();
    }

    public function userHasPurchased(?User $user): bool
    {
        if (! $user || ! $this->isPaidContent()) {
            return false;
        }

        return VideoPurchase::where('user_id', $user->id)
            ->where('video_id', $this->id)
            ->where('status', 'success')
            ->exists();
    }
}