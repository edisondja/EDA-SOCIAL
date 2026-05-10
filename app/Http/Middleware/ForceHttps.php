<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->shouldForceHttps()) {
            return $next($request);
        }

        if (!$request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }

    /**
     * Redirección HTTP→HTTPS: solo en producción (por APP_URL https) o si FORCE_HTTPS está definido.
     */
    private function shouldForceHttps(): bool
    {
        $raw = env('FORCE_HTTPS');
        if ($raw !== null && $raw !== '') {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        if (!app()->environment('production')) {
            return false;
        }

        return str_starts_with((string) config('app.url'), 'https://');
    }
}
