<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'banner',
        'is_private', 'avatar', 'subscriber_count',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($channel) {
            $channel->slug = Str::slug($channel->name) . '-' . uniqid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function subscribers()
    {
        return $this->hasMany(ChannelSubscription::class);
    }
}