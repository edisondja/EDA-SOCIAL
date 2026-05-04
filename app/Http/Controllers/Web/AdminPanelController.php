<?php

namespace App\Http\Controllers\Web;

use App\Category;
use App\Http\Controllers\Api\PlatformSettingController as ApiPlatform;
use App\Http\Controllers\Api\RedditImportController as ApiReddit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Role;
use App\Services\RedditVideoImportService;
use App\Support\PlatformConfig;
use App\User;
use App\Video;
use Illuminate\Http\Request;

class AdminPanelController extends Controller
{
    use SharesBranding;

    private const SECTIONS = ['seo', 'aspecto', 'integraciones', 'verificacion', 'usuarios', 'reddit'];

    public function show(Request $request, $section = 'seo')
    {
        if (!in_array($section, self::SECTIONS, true)) {
            abort(404);
        }

        $branding = $this->branding();
        $settings = [];
        try {
            $rows = \App\PlatformSetting::query()->orderBy('key')->get(['key', 'value']);
            foreach ($rows as $row) {
                $settings[$row->key] = $row->value;
            }
        } catch (\Throwable $e) {
            $settings = [];
        }

        $categories = Category::query()->orderBy('name')->get();
        $verificationFiles = $this->verificationFilesList();
        $integrationStatus = $this->integrationStatus();
        $dashboard = [
            'users_total' => User::count(),
            'videos_total' => Video::count(),
            'videos_blocked' => Video::where('moderation_status', 'blocked')->count(),
            'users_banned' => User::where('status', 'banned')->count(),
        ];

        $users = null;
        $roles = null;
        if ($section === 'usuarios') {
            $users = User::query()->with('role:id,name')->orderByDesc('id')->paginate(40)->withQueryString();
            $roles = Role::query()->orderBy('name')->get();
        }

        return view('web.admin.panel', compact(
            'section',
            'branding',
            'settings',
            'categories',
            'verificationFiles',
            'integrationStatus',
            'dashboard',
            'users',
            'roles'
        ));
    }

    public function updateSeo(Request $request)
    {
        $request->merge(['use_router_links' => $request->has('use_router_links')]);

        return $this->runApiForm($request, function () use ($request) {
            return app(ApiPlatform::class)->updateSeo($request);
        }, 'SEO actualizado.');
    }

    public function updateMenuColor(Request $request)
    {
        return $this->runApiForm($request, function () use ($request) {
            return app(ApiPlatform::class)->updateMenuColor($request);
        }, 'Color guardado.');
    }

    public function uploadLogo(Request $request)
    {
        return $this->runApiForm($request, function () use ($request) {
            return app(ApiPlatform::class)->uploadLogo($request);
        }, 'Logo actualizado.');
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        $baseSlug = \Illuminate\Support\Str::slug($data['name']);
        $slug = $baseSlug ?: \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
        $attempt = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $attempt;
            $attempt++;
        }
        Category::create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        $section = $request->input('_section', 'aspecto');

        return redirect()->route('admin.panel', ['section' => $section])->with('status', 'Categoría creada.');
    }

    public function updateIntegrations(Request $request)
    {
        $request->merge([
            'feature_redis_cache' => $request->has('feature_redis_cache'),
            'feature_rabbit_queue' => $request->has('feature_rabbit_queue'),
        ]);

        return $this->runApiForm($request, function () use ($request) {
            return app(ApiPlatform::class)->updateIntegrations($request);
        }, 'Integraciones guardadas.');
    }

    public function uploadVerification(Request $request)
    {
        return $this->runApiForm($request, function () use ($request) {
            return app(ApiPlatform::class)->uploadVerificationTxt($request);
        }, 'Archivo subido.');
    }

    public function writeSitemap(Request $request)
    {
        return $this->runApiForm($request, function () use ($request) {
            return app(ApiPlatform::class)->writeSitemapFile($request);
        }, 'sitemap.xml generado.');
    }

    public function importReddit(Request $request, RedditVideoImportService $reddit)
    {
        return $this->runApiForm($request, function () use ($request, $reddit) {
            return app(ApiReddit::class)->import($request, $reddit);
        }, 'Importación de Reddit completada.', true);
    }

    public function updateUserRole(Request $request, User $user)
    {
        return $this->runApiForm($request, function () use ($request, $user) {
            return app(\App\Http\Controllers\Api\AdminController::class)->updateUserRole($request, $user);
        }, 'Rol actualizado.');
    }

    public function blockVideo(Request $request, Video $video)
    {
        return $this->runApiForm($request, function () use ($request, $video) {
            return app(\App\Http\Controllers\Api\AdminController::class)->blockVideo($request, $video);
        }, 'Video bloqueado.');
    }

    public function banUser(Request $request, User $user)
    {
        return $this->runApiForm($request, function () use ($request, $user) {
            return app(\App\Http\Controllers\Api\AdminController::class)->banUser($request, $user);
        }, 'Usuario bloqueado.');
    }

    /**
     * Los controladores API usan $request->validate(), que lanza ValidationException (HTML/500)
     * en lugar de JsonResponse cuando se invocan desde las rutas web.
     */
    private function runApiForm(Request $request, callable $action, string $okMessage, bool $created = false)
    {
        try {
            $response = $action();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->adminSectionRedirect($request)->withErrors($e->errors())->withInput();
        }

        return $this->flashFromApi($request, $response, $okMessage, $created);
    }

    private function adminSectionRedirect(Request $request)
    {
        $section = $request->input('_section', 'seo');
        if (!in_array($section, self::SECTIONS, true)) {
            $section = 'seo';
        }

        return redirect()->route('admin.panel', ['section' => $section]);
    }

    private function flashFromApi(Request $request, $response, string $okMessage, bool $created = false)
    {
        $content = method_exists($response, 'getContent') ? $response->getContent() : '';
        $data = json_decode((string) $content, true) ?: [];
        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() >= 400) {
            $msg = is_array($data) && !empty($data['message']) ? $data['message'] : 'Error al guardar.';

            return $this->adminSectionRedirect($request)->withErrors(['admin' => $msg])->withInput();
        }

        $message = $okMessage;
        if ($created && is_array($data) && !empty($data['id'])) {
            $message .= ' (ID #' . $data['id'] . ')';
        }

        return $this->adminSectionRedirect($request)->with('status', $message);
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
        ];
    }
}
