<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'avatar', 'role'])]  // avatar, role add kiya
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;  // HasApiTokens add kiya

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // Relationships
    public function channel()
    {
        return $this->hasOne(Channel::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function watchHistory()
    {
        return $this->hasMany(WatchHistory::class)->latest('last_watched_at');
    }

    public function channelSubscriptions()
    {
        return $this->hasMany(ChannelSubscription::class);
    }

    public function adCampaigns()
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function videoComments()
    {
        return $this->hasMany(VideoComment::class);
    }

    // Helper method
    public function hasActivePlan(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
    }
}