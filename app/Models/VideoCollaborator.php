<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class VideoCollaborator extends Model
{
    /** @var array<int, Collection<int, int>> */
    private static array $approvedVideoIdsByUser = [];

    public static function isAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            $available = Schema::hasTable('video_collaborators');
        }

        return $available;
    }

    public static function approvedVideoIdsFor(int $userId): Collection
    {
        if (! static::isAvailable()) {
            return collect();
        }

        if (! isset(static::$approvedVideoIdsByUser[$userId])) {
            static::$approvedVideoIdsByUser[$userId] = static::query()
                ->where('collaborator_user_id', $userId)
                ->where('status', 'approved')
                ->pluck('video_id');
        }

        return static::$approvedVideoIdsByUser[$userId];
    }

    public static function userIsCollaborator(int $userId, int $videoId): bool
    {
        return static::approvedVideoIdsFor($userId)->contains($videoId);
    }
    protected $fillable = [
        'video_id',
        'owner_user_id',
        'collaborator_user_id',
        'collaborator_email',
        'status',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collaborator_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
