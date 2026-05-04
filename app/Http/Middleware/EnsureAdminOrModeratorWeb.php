<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class EnsureAdminOrModeratorWeb
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        $roleName = optional(optional($user)->role)->name;
        if (!in_array($roleName, ['admin', 'moderator'], true)) {
            abort(403, 'No autorizado.');
        }

        return $next($request);
    }
}
