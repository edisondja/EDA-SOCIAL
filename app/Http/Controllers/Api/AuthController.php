<?php

namespace App\Http\Controllers\Api;

use App\Channel;
use App\Http\Controllers\Controller;
use App\Role;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => 'required|string|max:80|unique:users,username',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $role = Role::where('name', 'user')->first();

        $user = User::create([
            'name' => $data['name'],
            'username' => Str::slug($data['username']),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $role ? $role->id : null,
            'status' => 'active',
        ]);

        Channel::create([
            'user_id' => $user->id,
            'slug' => Str::slug($user->username . '-' . $user->id),
            'display_name' => $user->name,
        ]);

        return response()->json([
            'token' => $user->api_token,
            'user' => $user->fresh('role', 'channel'),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if ($user->status === 'banned') {
            return response()->json([
                'message' => 'Tu cuenta está bloqueada.',
                'ban_reason' => $user->ban_reason,
                'banned_until' => optional($user->banned_until)->toIso8601String(),
            ], 403);
        }

        if (!$user->api_token) {
            $user->api_token = Str::random(60);
            $user->save();
        }

        return response()->json([
            'token' => $user->api_token,
            'user' => $user->load('role', 'channel'),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('role', 'channel'));
    }
}
