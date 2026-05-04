<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\PlatformSetting;
use App\PlatformTextSetting;
use App\Support\PlatformConfig;
use App\Support\VideoAdPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PlatformSettingController extends Controller
{
    public function index(Request $request)
    {
        $menuColor = PlatformConfig::get('menu_color', '#d83a7c');
        $logoUrl = PlatformConfig::get('logo_url');

        return response()->json([
            'menu_color' => $menuColor,
            'logo_url' => $logoUrl,
            'site_name' => PlatformConfig::get('site_name', 'EDA_SOCIAL'),
            'site_description' => PlatformConfig::get('site_description', ''),
            'site_keywords' => PlatformConfig::get('site_keywords', ''),
            'public_site_url' => PlatformConfig::get('public_site_url', ''),
            'use_router_links' => PlatformConfig::get('use_router_links', '1') === '1',
            'sitemap_url' => $request->getSchemeAndHttpHost() . '/sitemap.xml',
        ]);
    }

    /**
     * Panel admin: todos los valores (sin secretos).
     */
    public function adminShow()
    {
        $rows = PlatformSetting::query()->orderBy('key')->get(['key', 'value']);
        $map = [];
        foreach ($rows as $row) {
            $map[$row->key] = $row->value;
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('platform_text_settings')) {
                foreach (PlatformTextSetting::query()->orderBy('key')->get(['key', 'body']) as $row) {
                    $map[$row->key] = $row->body;
                }
            }
        } catch (\Throwable $e) {
        }

        return response()->json([
            'settings' => $map,
            'verification_files' => $this->verificationFilesList(),
            'integration_status' => $this->integrationStatus(),
        ]);
    }

    public function updateMenuColor(Request $request)
    {
        $data = $request->validate([
            'menu_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        PlatformConfig::set('menu_color', $data['menu_color']);

        return response()->json([
            'menu_color' => $data['menu_color'],
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $data = $request->validate([
            'logo' => 'required|image|max:5120',
        ]);

        $path = $data['logo']->store('brand', 'public');
        $url = Storage::disk('public')->url($path);

        PlatformConfig::set('logo_url', $url);

        return response()->json([
            'logo_url' => $url,
        ]);
    }

    public function updateSeo(Request $request)
    {
        $data = $request->validate([
            'site_name' => 'nullable|string|max:120',
            'site_description' => 'nullable|string|max:2000',
            'site_keywords' => 'nullable|string|max:500',
            'public_site_url' => 'nullable|string|max:255',
            'use_router_links' => 'nullable|boolean',
        ]);

        if (array_key_exists('site_name', $data)) {
            PlatformConfig::set('site_name', $data['site_name'] ?? '');
        }
        if (array_key_exists('site_description', $data)) {
            PlatformConfig::set('site_description', $data['site_description'] ?? '');
        }
        if (array_key_exists('site_keywords', $data)) {
            PlatformConfig::set('site_keywords', $data['site_keywords'] ?? '');
        }
        if (array_key_exists('public_site_url', $data)) {
            PlatformConfig::set('public_site_url', rtrim((string) ($data['public_site_url'] ?? ''), '/'));
        }
        if (array_key_exists('use_router_links', $data)) {
            PlatformConfig::set('use_router_links', !empty($data['use_router_links']) ? '1' : '0');
        }

        return response()->json(['ok' => true]);
    }

    public function updateVideoAds(Request $request)
    {
        $data = $request->validate([
            'video_ad_banner_top_enabled' => 'nullable|boolean',
            'video_ad_banner_bottom_enabled' => 'nullable|boolean',
            'video_ad_banner_top_template' => 'nullable|string|in:none,strip,cta,badge,custom',
            'video_ad_banner_bottom_template' => 'nullable|string|in:none,strip,cta,badge,custom',
            'video_ad_banner_top_custom_html' => 'nullable|string|max:12000',
            'video_ad_banner_bottom_custom_html' => 'nullable|string|max:12000',
            'video_ad_pop_enabled' => 'nullable|boolean',
            'video_ad_pop_template' => 'nullable|string|in:none,simple,custom',
            'video_ad_pop_custom_html' => 'nullable|string|max:12000',
            'video_ad_pop_delay_ms' => 'nullable|integer|min:0|max:120000',
            'video_ad_pop_title' => 'nullable|string|max:120',
        ]);

        if (array_key_exists('video_ad_banner_top_enabled', $data)) {
            PlatformConfig::set('video_ad_banner_top_enabled', !empty($data['video_ad_banner_top_enabled']) ? '1' : '0');
        }
        if (array_key_exists('video_ad_banner_bottom_enabled', $data)) {
            PlatformConfig::set('video_ad_banner_bottom_enabled', !empty($data['video_ad_banner_bottom_enabled']) ? '1' : '0');
        }
        if (array_key_exists('video_ad_pop_enabled', $data)) {
            PlatformConfig::set('video_ad_pop_enabled', !empty($data['video_ad_pop_enabled']) ? '1' : '0');
        }

        foreach ([
            'video_ad_banner_top_template',
            'video_ad_banner_bottom_template',
            'video_ad_pop_template',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                PlatformConfig::set($key, (string) $data[$key]);
            }
        }

        if (array_key_exists('video_ad_pop_delay_ms', $data) && $data['video_ad_pop_delay_ms'] !== null) {
            PlatformConfig::set('video_ad_pop_delay_ms', (string) (int) $data['video_ad_pop_delay_ms']);
        }
        if (array_key_exists('video_ad_pop_title', $data)) {
            PlatformConfig::set('video_ad_pop_title', (string) ($data['video_ad_pop_title'] ?? 'Información'));
        }

        if (array_key_exists('video_ad_banner_top_custom_html', $data)) {
            PlatformConfig::setText('video_ad_banner_top_custom_html', (string) ($data['video_ad_banner_top_custom_html'] ?? ''));
        }
        if (array_key_exists('video_ad_banner_bottom_custom_html', $data)) {
            PlatformConfig::setText('video_ad_banner_bottom_custom_html', (string) ($data['video_ad_banner_bottom_custom_html'] ?? ''));
        }
        if (array_key_exists('video_ad_pop_custom_html', $data)) {
            PlatformConfig::setText('video_ad_pop_custom_html', (string) ($data['video_ad_pop_custom_html'] ?? ''));
        }

        return response()->json([
            'ok' => true,
            'video_ads' => VideoAdPresentation::resolved(),
        ]);
    }

    public function updateIntegrations(Request $request)
    {
        $data = $request->validate([
            'feature_redis_cache' => 'nullable|boolean',
            'feature_rabbit_queue' => 'nullable|boolean',
        ]);

        if (array_key_exists('feature_redis_cache', $data)) {
            PlatformConfig::set('feature_redis_cache', !empty($data['feature_redis_cache']) ? '1' : '0');
        }
        if (array_key_exists('feature_rabbit_queue', $data)) {
            PlatformConfig::set('feature_rabbit_queue', !empty($data['feature_rabbit_queue']) ? '1' : '0');
        }

        return response()->json([
            'ok' => true,
            'integration_status' => $this->integrationStatus(),
        ]);
    }

    public function uploadVerificationTxt(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:128',
        ]);

        $original = $data['file']->getClientOriginalName();
        $safe = basename($original);
        if (strtolower(pathinfo($safe, PATHINFO_EXTENSION)) !== 'txt') {
            return response()->json(['message' => 'Solo archivos .txt.'], 422);
        }
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*\.txt$/', $safe)) {
            return response()->json([
                'message' => 'Nombre de archivo inválido. Usa solo letras, números, punto, guion y debe terminar en .txt',
            ], 422);
        }

        $target = public_path($safe);
        $bytes = file_get_contents($data['file']->getRealPath());
        File::put($target, $bytes);

        $list = $this->verificationFilesList();
        if (!in_array($safe, $list, true)) {
            $list[] = $safe;
        }
        PlatformConfig::set('verification_files_json', json_encode(array_values(array_unique($list))));

        $url = $request->getSchemeAndHttpHost() . '/' . rawurlencode($safe);

        return response()->json([
            'ok' => true,
            'filename' => $safe,
            'public_url' => $url,
            'verification_files' => $this->verificationFilesList(),
        ]);
    }

    public function writeSitemapFile(Request $request)
    {
        $response = app(\App\Http\Controllers\SitemapController::class)->show();
        $path = public_path('sitemap.xml');
        File::put($path, $response->getContent());

        $url = $request->getSchemeAndHttpHost() . '/sitemap.xml';

        return response()->json([
            'ok' => true,
            'path' => 'public/sitemap.xml',
            'public_url' => $url,
        ]);
    }

    private function verificationFilesList(): array
    {
        $raw = PlatformConfig::get('verification_files_json', '[]');
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function integrationStatus(): array
    {
        $queue = config('queue.default');
        $cache = config('cache.default');

        $redisExt = extension_loaded('redis') || extension_loaded('Redis');
        $redisHost = (bool) env('REDIS_HOST');
        $rabbitHost = (bool) env('RABBITMQ_HOST');

        $rabbitPackage = class_exists(\VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector::class);

        return [
            'queue_connection' => $queue,
            'cache_driver' => $cache,
            'redis_extension' => $redisExt,
            'redis_host_configured' => $redisHost,
            'rabbitmq_host_configured' => $rabbitHost,
            'rabbitmq_laravel_package_installed' => $rabbitPackage,
            'hint' => 'Para colas en segundo plano: QUEUE_CONNECTION=database (y php artisan queue:work). RabbitMQ requiere paquete y variables RABBITMQ_* en .env.',
        ];
    }
}
