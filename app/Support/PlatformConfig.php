<?php

namespace App\Support;

use App\PlatformSetting;
use App\PlatformTextSetting;
use Illuminate\Support\Facades\Schema;

class PlatformConfig
{
    /**
     * URL del logo por defecto (mismo tamaño de diseño que .brand-logo: 230×50).
     */
    public static function defaultLogoAssetUrl(): string
    {
        return asset('images/default-logo.svg');
    }

    /**
     * Logo configurado en plataforma, o el recurso por defecto EDA-SOCIAL si no hay ninguno.
     */
    public static function resolvedLogoUrl(): string
    {
        $url = self::get('logo_url');

        return ($url !== null && $url !== '') ? $url : self::defaultLogoAssetUrl();
    }

    public static function get(string $key, $default = null)
    {
        try {
            if (!Schema::hasTable('platform_settings')) {
                return $default;
            }
        } catch (\Throwable $e) {
            return $default;
        }

        $row = PlatformSetting::query()->where('key', $key)->first();

        return $row && $row->value !== null && $row->value !== '' ? $row->value : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        PlatformSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Textos largos (HTML de anuncios, etc.) en platform_text_settings.
     */
    public static function getText(string $key, $default = null)
    {
        try {
            if (!Schema::hasTable('platform_text_settings')) {
                return $default;
            }
        } catch (\Throwable $e) {
            return $default;
        }

        $row = PlatformTextSetting::query()->where('key', $key)->first();
        if ($row && $row->body !== null && $row->body !== '') {
            return $row->body;
        }

        return $default;
    }

    public static function setText(string $key, ?string $body): void
    {
        PlatformTextSetting::updateOrCreate(
            ['key' => $key],
            ['body' => $body ?? '']
        );
    }
}
