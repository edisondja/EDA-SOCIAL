<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\ModerationAction;
use App\Role;
use App\User;
use App\UserBan;
use App\Video;
use App\VideoBan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'users_total' => User::count(),
            'videos_total' => Video::count(),
            'videos_blocked' => Video::where('moderation_status', 'blocked')->count(),
            'users_banned' => User::where('status', 'banned')->count(),
        ]);
    }

    public function users(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);

        return response()->json(
            User::query()
                ->with('role:id,name')
                ->orderByDesc('id')
                ->paginate($perPage)
        );
    }

    public function searchVideos(Request $request)
    {
        $q = $request->input('q', '');
        return response()->json(
            Video::with(['author', 'channel'])
                ->when($q, function ($builder) use ($q) {
                    $builder->where('title', 'like', '%' . $q . '%')
                        ->orWhere('description', 'like', '%' . $q . '%')
                        ->orWhere('slug', 'like', '%' . $q . '%');
                })
                ->latest()
                ->paginate(20)
        );
    }

    public function blockVideo(Request $request, Video $video)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'ends_at' => 'nullable|date',
        ]);

        $video->update(['moderation_status' => 'blocked']);

        VideoBan::create([
            'video_id' => $video->id,
            'moderator_id' => $request->user()->id,
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'starts_at' => now(),
            'ends_at' => $data['ends_at'] ?? null,
            'active' => true,
        ]);

        ModerationAction::create([
            'moderator_id' => $request->user()->id,
            'target_type' => 'video',
            'target_id' => $video->id,
            'action' => 'block_video',
            'payload' => $data,
        ]);

        return response()->json(['ok' => true, 'video' => $video->fresh()]);
    }

    public function activateVideo(Request $request, Video $video)
    {
        $video->update(['moderation_status' => 'active']);

        VideoBan::query()
            ->where('video_id', $video->id)
            ->where('active', true)
            ->update(['active' => false]);

        ModerationAction::create([
            'moderator_id' => $request->user()->id,
            'target_type' => 'video',
            'target_id' => $video->id,
            'action' => 'activate_video',
            'payload' => [],
        ]);

        return response()->json(['ok' => true, 'video' => $video->fresh()]);
    }

    public function updateVideo(Request $request, Video $video)
    {
        $request->merge(['is_published' => $request->has('is_published')]);
        $pa = $request->input('published_at');
        if ($pa === '' || $pa === null) {
            $request->merge(['published_at' => null]);
        }

        $data = $request->validate([
            'title' => 'required|string|max:180',
            'slug' => ['required', 'string', 'max:220', Rule::unique('videos', 'slug')->ignore($video->id)],
            'description' => 'nullable|string|max:65535',
            'video_url' => 'required|string|max:255',
            'preview_url' => 'nullable|string|max:255',
            'thumbnail_url' => 'nullable|string|max:255',
            'thumbnail_file' => 'nullable|image|max:5120',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'moderation_status' => 'required|in:active,blocked,review',
        ]);

        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('video-thumbnails/' . $video->id, 'public');
            $data['thumbnail_url'] = Storage::disk('public')->url($path);
        }
        unset($data['thumbnail_file']);

        $video->update($data);

        ModerationAction::create([
            'moderator_id' => $request->user()->id,
            'target_type' => 'video',
            'target_id' => $video->id,
            'action' => 'update_video',
            'payload' => ['fields' => array_keys($data)],
        ]);

        return response()->json(['ok' => true, 'video' => $video->fresh()]);
    }

    public function banUser(Request $request, User $user)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'ends_at' => 'nullable|date',
        ]);

        $user->update([
            'status' => 'banned',
            'ban_reason' => $data['reason'],
            'banned_until' => $data['ends_at'] ?? null,
        ]);

        UserBan::create([
            'user_id' => $user->id,
            'moderator_id' => $request->user()->id,
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'starts_at' => now(),
            'ends_at' => $data['ends_at'] ?? null,
            'active' => true,
        ]);

        ModerationAction::create([
            'moderator_id' => $request->user()->id,
            'target_type' => 'user',
            'target_id' => $user->id,
            'action' => 'ban_user',
            'payload' => $data,
        ]);

        return response()->json(['ok' => true, 'user' => $user->fresh()]);
    }

    public function updateUserRole(Request $request, User $user)
    {
        $data = $request->validate(['role_id' => 'required|exists:roles,id']);
        $role = Role::findOrFail($data['role_id']);
        $user->update(['role_id' => $role->id]);

        ModerationAction::create([
            'moderator_id' => $request->user()->id,
            'target_type' => 'user',
            'target_id' => $user->id,
            'action' => 'change_role',
            'payload' => ['role' => $role->name],
        ]);

        return response()->json(['ok' => true, 'user' => $user->fresh('role')]);
    }
}
