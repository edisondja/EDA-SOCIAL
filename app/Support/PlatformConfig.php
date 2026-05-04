<?php

namespace App\Support;

use App\PlatformSetting;
use App\PlatformTextSetting;
use Illuminate\Support\Facades\Schema;

class PlatformConfig
{
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
