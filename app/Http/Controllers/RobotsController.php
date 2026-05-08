<?php

namespace App\Http\Controllers;

use App\Support\PlatformConfig;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function show(): Response
    {
        $base = rtrim((string) (PlatformConfig::get('public_site_url') ?: config('app.url')), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            // Bloquea áreas privadas y de administración
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /logout',
            'Disallow: /cuenta',
            'Disallow: /publicar',
            'Disallow: /api',
            '',
            // Evita rastreo de filtros con params repetitivos
            'Disallow: /*?*search=',
            'Disallow: /*?*hashtag=',
            'Disallow: /*?*categoria=',
            '',
            'Sitemap: ' . $base . '/sitemap.xml',
        ];

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
