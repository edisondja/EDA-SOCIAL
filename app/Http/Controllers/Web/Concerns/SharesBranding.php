<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Support\PlatformConfig;

trait SharesBranding
{
    protected function branding(): array
    {
        return [
            'menu_color' => PlatformConfig::get('menu_color', '#d83a7c'),
            'logo_url' => PlatformConfig::resolvedLogoUrl(),
            'site_name' => PlatformConfig::get('site_name', 'EDA_SOCIAL'),
            'site_description' => PlatformConfig::get('site_description', ''),
        ];
    }
}
