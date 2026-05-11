<?php

namespace App\Services;

use App\Comment;
use App\Notifications\NewCommentOnYourVideo;
use App\Notifications\ReplyOnYourVideoYouWereParent;
use App\Notifications\ReplyToYourComment;
use App\Notifications\YourCommentWasUpvoted;
use App\User;
use App\Video;

final class CommentNotificationDispatcher
{
    public static function afterCommentStored(Comment $comment): void
    {
        $comment->loadMissing(['video']);
        $video = $comment->video;
        if (!$video instanceof Video) {
            return;
        }

        $commenterId = (int) $comment->user_id;
        $ownerId = $video->author_id ? (int) $video->author_id : null;

        $parent = null;
        if ($comment->parent_id) {
            $parent = Comment::query()->find($comment->parent_id);
        }
        $parentUserId = $parent ? (int) $parent->user_id : null;

        $ownerGotCombinedReply = false;

        if ($parent && $parentUserId !== $commenterId) {
            if ($ownerId && $ownerId === $parentUserId && $ownerId !== $commenterId) {
                $owner = User::query()->find($ownerId);
                if ($owner) {
                    $owner->notify(new ReplyOnYourVideoYouWereParent($comment, $video));
                    $ownerGotCombinedReply = true;
                }
            } else {
                $parentUser = User::query()->find($parentUserId);
                if ($parentUser) {
                    $parentUser->notify(new ReplyToYourComment($comment, $parent, $video));
                }
            }
        }

        if ($ownerId && $ownerId !== $commenterId && !$ownerGotCombinedReply) {
            $owner = User::query()->find($ownerId);
            if ($owner) {
                $owner->notify(new NewCommentOnYourVideo($comment, $video));
            }
        }
    }

    public static function afterCommentUpvoted(Comment $comment, User $voter): void
    {
        if ((int) $comment->user_id === (int) $voter->id) {
            return;
        }

        $comment->loadMissing(['video']);
        $video = $comment->video;
        if (!$video instanceof Video) {
            return;
        }

        $recipient = User::query()->find((int) $comment->user_id);
        if (!$recipient) {
            return;
        }

        $recipient->notify(new YourCommentWasUpvoted(
            $comment,
            $video,
            (int) $voter->id,
            $voter->username ?? null,
        ));
    }
}
