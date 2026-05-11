<?php

namespace App\Notifications;

use App\Comment;
use App\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class YourCommentWasUpvoted extends Notification
{
    use Queueable;

    public function __construct(
        public Comment $comment,
        public Video $video,
        public int $voterId,
        public ?string $voterUsername,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $who = $this->voterUsername ?? 'Alguien';

        return [
            'kind' => 'comment_upvote',
            'message' => $who . ' votó positivo tu comentario en «' . Str::limit($this->video->title, 60) . '».',
            'video_id' => $this->video->id,
            'video_slug' => $this->video->playSlug(),
            'video_title' => $this->video->title,
            'comment_id' => $this->comment->id,
            'points' => (int) $this->comment->points,
            'actor_id' => $this->voterId,
            'actor_username' => $this->voterUsername,
        ];
    }
}
