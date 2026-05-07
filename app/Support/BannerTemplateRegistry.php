<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Plantillas HTML reutilizables para banners del single (superior/inferior al video).
 */
class BannerTemplateRegistry
{
    private const KEY = 'video_banner_templates_json';

    /**
     * @return array<int, array{id:string,name:string,html:string,enabled:bool}>
     */
    public static function all(): array
    {
        $raw = PlatformConfig::getText(self::KEY, '[]');
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $out[] = [
                'id' => (string) $row['id'],
                'name' => (string) ($row['name'] ?? 'Sin nombre'),
                'html' => (string) ($row['html'] ?? ''),
                'enabled' => !empty($row['enabled']),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{id:string,name:string,html:string,enabled:bool}>  $templates
     */
    public static function saveAll(array $templates): void
    {
        PlatformConfig::setText(self::KEY, json_encode(array_values($templates), JSON_UNESCAPED_UNICODE));
    }

    public static function findById(string $id): ?array
    {
        foreach (self::all() as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }

        return null;
    }

    public static function exists(string $id): bool
    {
        return self::findById($id) !== null;
    }

    /**
     * @return array{id:string,name:string,html:string,enabled:bool}
     */
    public static function create(string $name, string $html): array
    {
        $templates = self::all();
        $row = [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'html' => $html,
            'enabled' => true,
        ];
        $templates[] = $row;
        self::saveAll($templates);

        return $row;
    }

    public static function update(string $id, string $name, string $html, bool $enabled): bool
    {
        $templates = self::all();
        foreach ($templates as $i => $t) {
            if ($t['id'] === $id) {
                $templates[$i]['name'] = $name;
                $templates[$i]['html'] = $html;
                $templates[$i]['enabled'] = $enabled;
                self::saveAll($templates);

                return true;
            }
        }

        return false;
    }

    public static function delete(string $id): void
    {
        self::saveAll(array_values(array_filter(self::all(), function ($t) use ($id) {
            return $t['id'] !== $id;
        })));

        foreach ([
            'video_ad_banner_top_library_id',
            'video_ad_banner_bottom_library_id',
        ] as $cfgKey) {
            if ((string) PlatformConfig::get($cfgKey, '') === $id) {
                PlatformConfig::set($cfgKey, '');
            }
        }
    }
}
