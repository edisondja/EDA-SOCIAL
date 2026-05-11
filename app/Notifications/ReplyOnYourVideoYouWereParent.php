<?php

namespace App\Notifications;

use App\Comment;
use App\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Un solo aviso cuando el dueño del vídeo es quien recibe la respuesta (evita duplicar con dueño + padre).
 */
class ReplyOnYourVideoYouWereParent extends Notification
{
    use Queueable;

    public function __construct(
        public Comment $reply,
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
            'kind' => 'comment_reply_on_your_video',
            'message' => ($this->reply->user->username ?? 'Alguien') . ' respondió a tu comentario en tu publicación «' . Str::limit($this->video->title, 60) . '».',
            'video_id' => $this->video->id,
            'video_slug' => $this->video->playSlug(),
            'video_title' => $this->video->title,
            'comment_id' => $this->reply->id,
            'parent_comment_id' => $this->reply->parent_id,
            'actor_id' => $this->reply->user_id,
            'actor_username' => $this->reply->user->username ?? null,
            'body_preview' => Str::limit(strip_tags((string) $this->reply->body), 140),
        ];
    }
}
