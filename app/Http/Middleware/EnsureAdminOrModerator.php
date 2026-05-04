<?php

namespace App\Http\Middleware;

use Closure;

class EnsureAdminOrModerator
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        $roleName = optional(optional($user)->role)->name;
        if (!in_array($roleName, ['admin', 'moderator'], true)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return $next($request);
    }
}
