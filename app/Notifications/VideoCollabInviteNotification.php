<?php

namespace App\Notifications;

use App\Models\VideoCollaborator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VideoCollabInviteNotification extends Notification
{
    use Queueable;

    public function __construct(public VideoCollaborator $collaboration) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $collaboration = $this->collaboration->loadMissing(['video', 'owner']);
        $owner = $collaboration->owner;
        $video = $collaboration->video;

        return [
            'type'              => 'video_collab_invite',
            'collaboration_id'  => $collaboration->id,
            'video_id'          => $video?->id,
            'video_title'       => $video?->title,
            'owner_id'          => $owner?->id,
            'owner_name'        => $owner?->name,
            'owner_email'       => $owner?->email,
            'message'           => ($owner?->name ?? 'A creator') . ' invited you to collaborate on "' . ($video?->title ?? 'a video') . '"',
        ];
    }
}
