<?php

namespace App\Notifications;

use App\Comment;
use App\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewCommentOnYourVideo extends Notification
{
    use Queueable;

    public function __construct(
        public Comment $comment,
        public Video $video,
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
        $this->comment->loadMissing('user:id,username,name');

        return [
            'kind' => 'video_comment',
            'message' => ($this->comment->user->username ?? 'Alguien') . ' comentó en «' . Str::limit($this->video->title, 80) . '».',
            'video_id' => $this->video->id,
            'video_slug' => $this->video->playSlug(),
            'video_title' => $this->video->title,
            'comment_id' => $this->comment->id,
            'actor_id' => $this->comment->user_id,
            'actor_username' => $this->comment->user->username ?? null,
            'body_preview' => Str::limit(strip_tags((string) $this->comment->body), 140),
        ];
    }
}
