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
