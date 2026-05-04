<?php

namespace App\Http\Controllers\Api;

use App\Comment;
use App\Http\Controllers\Controller;
use App\Video;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Video $video)
    {
        $flat = Comment::query()
            ->where('video_id', $video->id)
            ->with('user:id,name,username,avatar_url')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(Comment::nestForDisplay($flat));
    }

    public function store(Request $request, Video $video)
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId === 0) {
            $parentId = null;
        }
        $parentError = Comment::replyParentError($video, $parentId);
        if ($parentError !== null) {
            return response()->json(['message' => $parentError], 422);
        }

        $comment = Comment::create([
            'video_id' => $video->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId ?: null,
            'body' => $data['body'],
            'points' => 0,
        ]);

        return response()->json($comment->load('user:id,name,username,avatar_url'), 201);
    }

    public function vote(Request $request, Comment $comment)
    {
        $data = $request->validate([
            'value' => 'required|integer|in:-1,1',
        ]);

        $comment->increment('points', $data['value']);
        $comment->refresh();

        return response()->json($comment->load('user:id,name,username,avatar_url'));
    }
}
