<?php

namespace App\Notifications;

use App\Comment;
use App\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ReplyToYourComment extends Notification
{
    use Queueable;

    public function __construct(
        public Comment $reply,
        public Comment $parent,
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
        $this->reply->loadMissing('user:id,username,name');

        return [
            'kind' => 'comment_reply',
            'message' => ($this->reply->user->username ?? 'Alguien') . ' respondió a tu comentario en «' . Str::limit($this->video->title, 60) . '».',
            'video_id' => $this->video->id,
            'video_slug' => $this->video->playSlug(),
            'video_title' => $this->video->title,
            'comment_id' => $this->reply->id,
            'parent_comment_id' => $this->parent->id,
            'actor_id' => $this->reply->user_id,
            'actor_username' => $this->reply->user->username ?? null,
            'body_preview' => Str::limit(strip_tags((string) $this->reply->body), 140),
        ];
    }
}
