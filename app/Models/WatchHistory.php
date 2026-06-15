<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WatchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'video_id', 'watched_seconds',
        'total_seconds', 'completed', 'last_watched_at'
    ];

    protected $casts = [
        'completed'       => 'boolean',
        'last_watched_at' => 'datetime',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressAttribute(): int
    {
        if (!$this->total_seconds) return 0;
        return (int) (($this->watched_seconds / $this->total_seconds) * 100);
    }
}