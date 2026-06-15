<?php

namespace App\Http\Resources;

use App\Models\VideoComment;
use App\Support\YoutubeFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VideoComment */
class YoutubeCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id'          => $this->id,
            'body'        => $this->body,
            'user_id'     => $this->user_id,
            'user_name'   => $user?->name ?? 'User',
            'user_avatar' => YoutubeFormatter::storageUrl($user?->avatar),
            'time'        => YoutubeFormatter::timeAgo($this->created_at),
            'created_at'  => $this->created_at?->toIso8601String(),
            'is_own'      => $request->user()?->id === $this->user_id,
        ];
    }
}
