<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id', 'title', 'description', 'video_path',
        'thumbnail', 'type', 'platform', 'visibility', 'genre',
        'duration', 'is_premium', 'price', 'comments_enabled', 'downloadable',
        'is_collab', 'ad_placement', 'has_paid_promotion',
        'views_count', 'likes_count', 'comments_count', 'earnings_paise',
        'status', 'show_on_home', 'series_id', 'episode_number',
    ];

    protected $casts = [
        'is_premium'          => 'boolean',
        'price'               => 'decimal:2',
        'comments_enabled'    => 'boolean',
        'downloadable'        => 'boolean',
        'is_collab'           => 'boolean',
        'has_paid_promotion'  => 'boolean',
        'show_on_home'        => 'boolean',
    ];

    public static function ensureShowOnHomeColumn(): void
    {
        if (! Schema::hasTable('videos') || Schema::hasColumn('videos', 'show_on_home')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('show_on_home')->default(false)->after('status');
        });
    }

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

    public function deleteWithFiles(): void
    {
        if ($this->video_path) {
            Storage::disk('public')->delete($this->video_path);
        }
        if ($this->thumbnail) {
            Storage::disk('public')->delete($this->thumbnail);
        }

        try {
            $this->shortLikes()->delete();
        } catch (\Throwable) {
        }
        try {
            $this->comments()->delete();
        } catch (\Throwable) {
        }
        try {
            $this->watchHistories()->delete();
        } catch (\Throwable) {
        }
        try {
            $this->collaborators()->delete();
        } catch (\Throwable) {
        }
        try {
            $this->purchases()->delete();
        } catch (\Throwable) {
        }
        try {
            $this->categories()->detach();
        } catch (\Throwable) {
        }
        if (Schema::hasTable('home_section_video')) {
            try {
                \DB::table('home_section_video')->where('video_id', $this->id)->delete();
            } catch (\Throwable) {
            }
        }

        $this->delete();
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