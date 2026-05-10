<?php

namespace App\Http\Controllers\Web;

use App\Category;
use App\Http\Controllers\Api\PlatformSettingController as ApiPlatform;
use App\Http\Controllers\Api\RedditImportController as ApiReddit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\SharesBranding;
use App\Jobs\GenerateVideoPosterProgressJob;
use App\Jobs\GenerateVideoHlsJob;
use App\Role;
use App\Services\IntegrationConnectivityService;
use App\Services\QueueMonitorService;
use App\Services\RedditVideoImportService;
use App\Services\VideoPreviewGenerationService;
use App\Support\BannerTemplateRegistry;
use App\Support\PlatformConfig;
use App\Support\VideoAdPresentation;
use App\User;
use App\Video;
use App\VideoDailyView;
use App\VideoReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminPanelController extends Controller
{
    use SharesBranding;

    private const SECTIONS = ['seo', 'aspecto', 'banners', 'integraciones', 'verificacion', 'monitoreo', 'usuarios', 'videos', 'reportes', 'reddit', 'metricas'];

    public function show(Request $request, $section = 'seo')
    {
        if (!in_array($section, self::SECTIONS, true)) {
            abort(404);
        }

        if ($section === 'metricas') {
            $roleName = optional(optional($request->user())->role)->name;
            if ($roleName !== 'admin') {
                abort(403, 'Solo administradores pueden ver métricas.');
            }
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
        $pendingReports = 0;
        try {
            if (Schema::hasTable('video_reports')) {
                $pendingReports = (int) VideoReport::query()->where('status', VideoReport::STATUS_PENDING)->count();
            }
        } catch (\Throwable $e) {
            $pendingReports = 0;
        }

        $dashboard = [
            'users_total' => User::count(),
            'videos_total' => Video::count(),
            'videos_blocked' => Video::where('moderation_status', 'blocked')->count(),
            'users_banned' => User::where('status', 'banned')->count(),
            'pending_reports' => $pendingReports,
            'views_total' => (int) Video::query()->sum('views_count'),
        ];

        $adminTopVideos = null;

        $users = null;
        $roles = null;
        $metricsVideos = null;
        $metricsSummary = null;
        $adminVideos = null;
        $videoFilters = [];
        if ($section === 'usuarios') {
            $users = User::query()->with('role:id,name')->orderByDesc('id')->paginate(40)->withQueryString();
            $roles = Role::query()->orderBy('name')->get();
        }

        if ($section === 'videos') {
            $q = trim((string) $request->query('q', ''));
            $mod = $request->query('moderation', '');
            $pub = $request->query('published', '');
            $videoFilters = compact('q', 'mod', 'pub');

            $builder = Video::query()
                ->with(['author:id,name,email', 'channel:id,display_name'])
                ->orderByDesc('id');

            if ($q !== '') {
                $pat = '%' . addcslashes($q, '%_\\') . '%';
                $builder->where(function ($w) use ($q, $pat) {
                    $w->where('title', 'like', $pat)
                        ->orWhere('slug', 'like', $pat)
                        ->orWhere('description', 'like', $pat);
                    if (ctype_digit($q)) {
                        $w->orWhere('id', (int) $q);
                    }
                });
            }

            if (in_array($mod, ['active', 'blocked', 'review'], true)) {
                $builder->where('moderation_status', $mod);
            }

            if ($pub === '1') {
                $builder->where('is_published', true);
            } elseif ($pub === '0') {
                $builder->where('is_published', false);
            }

            $adminVideos = $builder->paginate(25)->withQueryString();
        }

        if ($section === 'metricas') {
            $viewsToday = null;
            try {
                if (Schema::hasTable('video_daily_views')) {
                    $viewsToday = (int) VideoDailyView::query()
                        ->whereDate('stat_date', now()->toDateString())
                        ->sum('views');
                }
            } catch (\Throwable $e) {
                $viewsToday = null;
            }

            $metricsSummary = [
                'views_total' => (int) Video::query()->sum('views_count'),
                'videos_total' => Video::count(),
                'views_today' => $viewsToday,
            ];
            $metricsVideos = Video::query()
                ->orderByDesc('views_count')
                ->orderByDesc('id')
                ->paginate(35)
                ->withQueryString();

            $adminTopVideos = Video::query()
                ->orderByDesc('views_count')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'title', 'slug', 'views_count']);
        }

        $videoReports = null;
        if ($section === 'reportes') {
            $videoReports = VideoReport::query()
                ->with(['video:id,title,slug', 'user:id,name,email,username'])
                ->orderByDesc('id')
                ->paginate(35)
                ->withQueryString();
        }

        $serverMonitor = null;
        if ($section === 'monitoreo') {
            $serverMonitor = $this->serverMonitorSnapshot();
        }

        $seoSitemapUrl = '';
        $seoSitemapLinksCount = 0;
        if ($section === 'seo') {
            $base = rtrim((string) (PlatformConfig::get('public_site_url') ?: config('app.url')), '/');
            if ($base === '') {
                $base = rtrim((string) config('app.url'), '/');
            }
            $seoSitemapUrl = $base . '/sitemap.xml';

            $publishedVideos = (int) Video::query()
                ->where('is_published', true)
                ->where('moderation_status', 'active')
                ->count();
            $includeAllPosts = PlatformConfig::get('sitemap_include_all_posts', '1') === '1';
            if ($includeAllPosts) {
                $explorePages = (int) ceil($publishedVideos / 20);
                $explorePages = max(1, min($explorePages, 5000));
                $seoSitemapLinksCount = $explorePages + $publishedVideos;
            } else {
                $seoSitemapLinksCount = 1;
            }
        }

        $bannerTemplates = [];
        $bannerSlotConfig = [];
        if ($section === 'banners') {
            $bannerTemplates = BannerTemplateRegistry::all();
            $bannerSlotConfig = [
                'top_enabled' => PlatformConfig::get('video_ad_banner_top_enabled', '0') === '1',
                'bottom_enabled' => PlatformConfig::get('video_ad_banner_bottom_enabled', '0') === '1',
                'top_mode' => PlatformConfig::get('video_ad_banner_top_mode', 'legacy'),
                'bottom_mode' => PlatformConfig::get('video_ad_banner_bottom_mode', 'legacy'),
                'top_library_id' => (string) PlatformConfig::get('video_ad_banner_top_library_id', ''),
                'bottom_library_id' => (string) PlatformConfig::get('video_ad_banner_bottom_library_id', ''),
                'top_template' => PlatformConfig::get('video_ad_banner_top_template', 'none'),
                'bottom_template' => PlatformConfig::get('video_ad_banner_bottom_template', 'none'),
                'top_custom_html' => (string) PlatformConfig::getText('video_ad_banner_top_custom_html', ''),
                'bottom_custom_html' => (string) PlatformConfig::getText('video_ad_banner_bottom_custom_html', ''),
                'top_custom_script' => (string) PlatformConfig::getText('video_ad_banner_top_custom_script', ''),
                'bottom_custom_script' => (string) PlatformConfig::getText('video_ad_banner_bottom_custom_script', ''),
                'pop_enabled' => PlatformConfig::get('video_ad_pop_enabled', '0') === '1',
                'pop_template' => PlatformConfig::get('video_ad_pop_template', 'none'),
                'pop_delay_ms' => max(0, min(120000, (int) PlatformConfig::get('video_ad_pop_delay_ms', '3500'))),
                'pop_title' => PlatformConfig::get('video_ad_pop_title', 'Información'),
                'pop_custom_html' => (string) PlatformConfig::getText('video_ad_pop_custom_html', ''),
                'vast_enabled' => PlatformConfig::get('video_ad_vast_enabled', '0') === '1',
                'vast_tag_url' => (string) PlatformConfig::getText('video_ad_vast_tag_url', ''),
                'vast_skip_seconds' => max(0, min(60, (int) PlatformConfig::get('video_ad_vast_skip_seconds', '5'))),
            ];
        }

        return view('web.admin.panel', compact(
            'section',
            'branding',
            'settings',
            'categories',
            'verificationFiles',
            'integrationStatus',
            'dashboard',
            'adminTopVideos',
            'users',
            'roles',
            'metricsVideos',
            'metricsSummary',
            'adminVideos',
            'videoFilters',
            'videoReports',
            'serverMonitor',
            'bannerTemplates',
            'bannerSlotConfig',
            'seoSitemapUrl',
            'seoSitemapLinksCount'
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
        $data = $request->validate([
            'include_all_posts' => 'nullable|in:0,1',
        ]);

        if (array_key_exists('include_all_posts', $data)) {
            PlatformConfig::set('sitemap_include_all_posts', (string) $data['include_all_posts'] === '1' ? '1' : '0');
        }

        $progressKey = $this->sitemapProgressKey($request);
        $this->putSitemapProgress($progressKey, 5);

        try {
            $this->putSitemapProgress($progressKey, 25);
            $response = app(ApiPlatform::class)->writeSitemapFile($request);
            $content = method_exists($response, 'getContent') ? $response->getContent() : '';
            $json = json_decode((string) $content, true) ?: [];

            if (!is_array($json) || empty($json['ok'])) {
                $msg = is_array($json) ? (string) ($json['message'] ?? 'No se pudo generar sitemap.xml.') : 'No se pudo generar sitemap.xml.';
                $code = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 500;
                $code = $code >= 400 && $code < 600 ? $code : 500;
                $this->forgetSitemapProgress($progressKey);
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'ok' => false,
                        'message' => $msg,
                    ], $code);
                }

                return $this->adminSectionRedirect($request)->withErrors(['admin' => $msg])->withInput();
            }

            $this->putSitemapProgress($progressKey, 90);

            $sitemapUrl = (string) ($json['public_url'] ?? url('/sitemap.xml'));

            $this->putSitemapProgress($progressKey, 100);

            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'sitemap.xml generado.',
                    'public_url' => $sitemapUrl,
                ]);
            }

            return $this->adminSectionRedirect($request)->with('status', 'sitemap.xml generado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->forgetSitemapProgress($progressKey);
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Error de validación.',
                    'errors' => $e->errors(),
                ], 422);
            }

            return $this->adminSectionRedirect($request)->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            $this->forgetSitemapProgress($progressKey);
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => config('app.debug') ? $e->getMessage() : 'No se pudo generar sitemap.xml.',
                ], 500);
            }

            return $this->adminSectionRedirect($request)->withErrors(['admin' => 'No se pudo generar sitemap.xml.'])->withInput();
        }
    }

    public function sitemapGenerationStatus(Request $request)
    {
        $progress = (int) Cache::get($this->sitemapProgressKey($request), 0);

        return response()->json([
            'progress' => max(0, min(100, $progress)),
            'done' => $progress >= 100,
        ]);
    }

    public function queueMonitorStatus(Request $request, QueueMonitorService $queueMonitorService)
    {
        return response()->json($queueMonitorService->snapshot());
    }

    public function workerMediaStatus()
    {
        $pid = (int) Cache::get($this->workerMediaPidKey(), 0);
        $running = $pid > 0 ? $this->isProcessRunning($pid) : false;

        if ($pid > 0 && !$running) {
            Cache::forget($this->workerMediaPidKey());
            $pid = 0;
        }

        return response()->json([
            'ok' => true,
            'running' => $running,
            'pid' => $pid > 0 ? $pid : null,
            'queue' => 'media',
        ]);
    }

    public function startWorkerMedia(Request $request)
    {
        $existingPid = (int) Cache::get($this->workerMediaPidKey(), 0);
        if ($existingPid > 0 && $this->isProcessRunning($existingPid)) {
            return response()->json([
                'ok' => true,
                'started' => false,
                'running' => true,
                'pid' => $existingPid,
                'message' => 'El worker media ya estaba activo.',
            ]);
        }

        try {
            $php = trim((string) env('PHP_CLI_BINARY', 'php'));
            $artisan = base_path('artisan');
            $logPath = storage_path('logs/worker-media.log');

            $cmd = sprintf(
                'cd %s && nohup %s %s queue:work rabbitmq --queue=media,default --tries=3 --timeout=1200 >> %s 2>&1 & echo $!',
                escapeshellarg(base_path()),
                escapeshellarg($php),
                escapeshellarg($artisan),
                escapeshellarg($logPath)
            );

            $pidRaw = shell_exec($cmd);
            $pid = (int) trim((string) $pidRaw);

            if ($pid <= 0 || !$this->isProcessRunning($pid)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo iniciar el worker. Revisa PHP_CLI_BINARY, permisos y logs de worker.',
                ], 500);
            }

            Cache::put($this->workerMediaPidKey(), $pid, now()->addDays(2));

            return response()->json([
                'ok' => true,
                'started' => true,
                'running' => true,
                'pid' => $pid,
                'message' => 'Worker media iniciado correctamente.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'No se pudo iniciar el worker media.',
            ], 500);
        }
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

    public function activateVideo(Request $request, Video $video)
    {
        return $this->runApiForm($request, function () use ($request, $video) {
            return app(\App\Http\Controllers\Api\AdminController::class)->activateVideo($request, $video);
        }, 'Video activado.');
    }

    public function updateVideo(Request $request, Video $video)
    {
        return $this->runApiForm($request, function () use ($request, $video) {
            return app(\App\Http\Controllers\Api\AdminController::class)->updateVideo($request, $video);
        }, 'Video actualizado.');
    }

    public function generateVideoHls(Request $request, Video $video)
    {
        if (!config('hls.enabled', false)) {
            return $this->adminSectionRedirect($request)
                ->withErrors(['admin' => 'HLS está desactivado en la configuración (.env).'])
                ->withInput();
        }

        GenerateVideoHlsJob::dispatch($video->id);

        return $this->adminSectionRedirect($request)
            ->with('status', 'Conversión HLS encolada para el video #' . $video->id . '.');
    }

    public function updateReportStatus(Request $request, VideoReport $report)
    {
        $data = $request->validate([
            'status' => 'required|string|in:reviewed,dismissed,pending',
        ]);
        $report->update(['status' => $data['status']]);

        return redirect()->route('admin.panel', ['section' => 'reportes'])->with('status', 'Estado del reporte actualizado.');
    }

    public function generateVideoPreviews(Request $request, VideoPreviewGenerationService $previewService)
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:40',
        ]);
        $limit = isset($data['limit']) ? (int) $data['limit'] : 15;

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $result = $previewService->processBatchMissing($limit);

        $summary = sprintf(
            'ffmpeg: %d procesados correctamente · %d omitidos · %d con error.',
            $result['processed'],
            $result['skipped'],
            $result['failed']
        );

        $tail = implode(' ', array_slice($result['messages'], 0, 12));
        if (strlen($tail) > 1200) {
            $tail = substr($tail, 0, 1197).'…';
        }

        $flash = trim($summary.(strlen($tail) ? ' '.$tail : ''));

        return redirect()->route('admin.panel', ['section' => 'videos'])
            ->with('status', $flash);
    }

    public function generateVideoPostersBatch(Request $request, VideoPreviewGenerationService $previewService)
    {
        $data = $request->validate([
            'poster_limit' => 'nullable|integer|min:1|max:200',
            'poster_scope' => 'nullable|string|in:missing,all',
            'poster_duration_aware' => 'nullable|in:0,1',
        ]);
        $limit = isset($data['poster_limit']) ? (int) $data['poster_limit'] : 40;
        $scope = $data['poster_scope'] ?? 'missing';
        $durationAware = ($data['poster_duration_aware'] ?? '1') === '1';

        if (function_exists('set_time_limit')) {
            @set_time_limit(900);
        }

        $result = $previewService->processPosterBatchForAdmin($limit, $scope, $durationAware);

        $summary = sprintf(
            'Portadas JPG: %d generadas · %d omitidos · %d error · modo %s · seek %s.',
            $result['processed'],
            $result['skipped'],
            $result['failed'],
            $scope === 'all' ? 'todas (sobrescribe)' : 'solo faltantes',
            $durationAware ? 'según duración' : 'fijo (.env)'
        );

        $tail = implode(' ', array_slice($result['messages'], 0, 18));
        if (strlen($tail) > 1600) {
            $tail = substr($tail, 0, 1597).'…';
        }

        $flash = trim($summary.(strlen($tail) ? ' '.$tail : ''));

        return redirect()->route('admin.panel', ['section' => 'videos'])
            ->with('status', $flash);
    }

    public function enqueueMissingPostersBatch(Request $request)
    {
        try {
            $data = $request->validate([
                'limit' => 'nullable|integer|min:1|max:300',
                'scope' => 'nullable|string|in:missing,all',
                'duration_aware' => 'nullable|in:0,1',
            ]);

            $limit = isset($data['limit']) ? (int) $data['limit'] : 120;
            $scope = ($data['scope'] ?? 'missing') === 'all' ? 'all' : 'missing';
            $durationAware = ($data['duration_aware'] ?? '1') === '1';

            $query = Video::query()->orderBy('id');
            if ($scope === 'missing') {
                $query->where(function ($w) {
                    $w->where(function ($a) {
                        $a->whereNull('thumbnail_url')->orWhere('thumbnail_url', '');
                    })->orWhere(function ($a) {
                        foreach (['%.mp4%', '%.webm%', '%.mov%', '%.m4v%', '%.mkv%', '%.ts%'] as $like) {
                            $a->orWhere('thumbnail_url', 'like', $like);
                        }
                    });
                });
            }

            $videoIds = $query->limit($limit)->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();

            if (count($videoIds) === 0) {
                return response()->json([
                    'ok' => true,
                    'batch_id' => null,
                    'total' => 0,
                    'message' => 'No hay videos para procesar con ese filtro.',
                ]);
            }

            $batchId = (string) \Illuminate\Support\Str::uuid();
            $state = [
                'batch_id' => $batchId,
                'total' => count($videoIds),
                'done' => 0,
                'ok' => 0,
                'failed' => 0,
                'scope' => $scope,
                'duration_aware' => $durationAware,
                'status' => 'running',
                'recent' => [],
                'videos' => $videoIds,
                'completed' => [],
                'started_at' => now()->toDateTimeString(),
                'finished_at' => null,
            ];

            $this->putPosterBatchState($batchId, $state);
            $this->rememberPosterBatchId($request, $batchId);

            foreach ($videoIds as $videoId) {
                GenerateVideoPosterProgressJob::dispatch($videoId, $batchId, $scope === 'all', $durationAware);
            }

            return response()->json([
                'ok' => true,
                'batch_id' => $batchId,
                'total' => count($videoIds),
                'message' => 'Proceso encolado. Revisa la barra de progreso.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Parámetros inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'No se pudo encolar la generación de portadas. Revisa conexión de cola/cache y worker.',
            ], 500);
        }
    }

    public function missingPostersBatchStatus(Request $request)
    {
        $batchId = trim((string) $request->query('batch_id', ''));
        if ($batchId === '') {
            $batchId = $this->lastPosterBatchId($request);
        }
        if ($batchId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Sin lote activo.',
            ], 404);
        }

        $state = $this->posterBatchState($batchId);
        if (!$state) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró ese lote.',
            ], 404);
        }

        $total = max(0, (int) ($state['total'] ?? 0));
        $done = max(0, (int) ($state['done'] ?? 0));
        $progress = $total > 0 ? (int) floor(min(100, ($done * 100) / $total)) : 100;
        $doneFlag = ($state['status'] ?? '') === 'done' || ($total > 0 && $done >= $total);

        return response()->json([
            'ok' => true,
            'batch_id' => $batchId,
            'progress' => $progress,
            'done' => $doneFlag,
            'counts' => [
                'total' => $total,
                'done' => $done,
                'ok' => max(0, (int) ($state['ok'] ?? 0)),
                'failed' => max(0, (int) ($state['failed'] ?? 0)),
            ],
            'recent' => array_slice((array) ($state['recent'] ?? []), -12),
            'started_at' => $state['started_at'] ?? null,
            'finished_at' => $state['finished_at'] ?? null,
        ]);
    }

    public function banUser(Request $request, User $user)
    {
        return $this->runApiForm($request, function () use ($request, $user) {
            return app(\App\Http\Controllers\Api\AdminController::class)->banUser($request, $user);
        }, 'Usuario bloqueado.');
    }

    public function storeBannerTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'html' => 'required|string|max:12000',
        ]);
        $clean = VideoAdPresentation::sanitize($data['html']);
        BannerTemplateRegistry::create(trim($data['name']), $clean);

        return redirect()->route('admin.panel', ['section' => 'banners'])->with('status', 'Plantilla de banner creada.');
    }

    public function updateBannerTemplate(Request $request, string $template)
    {
        $request->merge(['tpl_enabled' => $request->has('tpl_enabled')]);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'html' => 'required|string|max:12000',
            'tpl_enabled' => 'boolean',
        ]);
        $clean = VideoAdPresentation::sanitize($data['html']);
        if (!BannerTemplateRegistry::update($template, trim($data['name']), $clean, !empty($data['tpl_enabled']))) {
            return redirect()->route('admin.panel', ['section' => 'banners'])->withErrors(['admin' => 'Plantilla no encontrada.']);
        }

        return redirect()->route('admin.panel', ['section' => 'banners'])->with('status', 'Plantilla actualizada.');
    }

    public function deleteBannerTemplate(Request $request, string $template)
    {
        BannerTemplateRegistry::delete($template);

        return redirect()->route('admin.panel', ['section' => 'banners'])->with('status', 'Plantilla eliminada.');
    }

    public function updateBannerSlots(Request $request)
    {
        $request->merge([
            'video_ad_banner_top_enabled' => $request->has('video_ad_banner_top_enabled'),
            'video_ad_banner_bottom_enabled' => $request->has('video_ad_banner_bottom_enabled'),
            'video_ad_pop_enabled' => $request->has('video_ad_pop_enabled'),
            'video_ad_vast_enabled' => $request->has('video_ad_vast_enabled'),
        ]);

        $data = $request->validate([
            'video_ad_banner_top_enabled' => 'boolean',
            'video_ad_banner_bottom_enabled' => 'boolean',
            'video_ad_banner_top_mode' => 'required|in:legacy,library',
            'video_ad_banner_bottom_mode' => 'required|in:legacy,library',
            'video_ad_banner_top_library_id' => 'nullable|string|max:64',
            'video_ad_banner_bottom_library_id' => 'nullable|string|max:64',
            'video_ad_banner_top_template' => 'nullable|string|in:none,strip,cta,badge,custom',
            'video_ad_banner_bottom_template' => 'nullable|string|in:none,strip,cta,badge,custom',
            'video_ad_banner_top_custom_html' => 'nullable|string|max:12000',
            'video_ad_banner_bottom_custom_html' => 'nullable|string|max:12000',
            'video_ad_banner_top_custom_script' => 'nullable|string|max:12000',
            'video_ad_banner_bottom_custom_script' => 'nullable|string|max:12000',
            'video_ad_pop_enabled' => 'boolean',
            'video_ad_pop_template' => 'nullable|string|in:none,simple,custom',
            'video_ad_pop_custom_html' => 'nullable|string|max:12000',
            'video_ad_pop_delay_ms' => 'nullable|integer|min:0|max:120000',
            'video_ad_pop_title' => 'nullable|string|max:120',
            'video_ad_vast_enabled' => 'boolean',
            'video_ad_vast_tag_url' => 'nullable|string|max:12000',
            'video_ad_vast_skip_seconds' => 'nullable|integer|min:0|max:60',
        ]);

        if (!empty($data['video_ad_banner_top_enabled']) && $data['video_ad_banner_top_mode'] === 'library') {
            $tid = trim((string) ($data['video_ad_banner_top_library_id'] ?? ''));
            $t = $tid !== '' ? BannerTemplateRegistry::findById($tid) : null;
            if (!$t || empty($t['enabled'])) {
                return redirect()->route('admin.panel', ['section' => 'banners'])
                    ->withErrors(['video_ad_banner_top_library_id' => 'Seleccioná una plantilla guardada activa para el banner superior.'])
                    ->withInput();
            }
        }

        if (!empty($data['video_ad_banner_bottom_enabled']) && $data['video_ad_banner_bottom_mode'] === 'library') {
            $tid = trim((string) ($data['video_ad_banner_bottom_library_id'] ?? ''));
            $t = $tid !== '' ? BannerTemplateRegistry::findById($tid) : null;
            if (!$t || empty($t['enabled'])) {
                return redirect()->route('admin.panel', ['section' => 'banners'])
                    ->withErrors(['video_ad_banner_bottom_library_id' => 'Seleccioná una plantilla guardada activa para el banner inferior.'])
                    ->withInput();
            }
        }

        PlatformConfig::set('video_ad_banner_top_enabled', !empty($data['video_ad_banner_top_enabled']) ? '1' : '0');
        PlatformConfig::set('video_ad_banner_bottom_enabled', !empty($data['video_ad_banner_bottom_enabled']) ? '1' : '0');
        PlatformConfig::set('video_ad_banner_top_mode', $data['video_ad_banner_top_mode']);
        PlatformConfig::set('video_ad_banner_bottom_mode', $data['video_ad_banner_bottom_mode']);
        PlatformConfig::set('video_ad_banner_top_library_id', trim((string) ($data['video_ad_banner_top_library_id'] ?? '')));
        PlatformConfig::set('video_ad_banner_bottom_library_id', trim((string) ($data['video_ad_banner_bottom_library_id'] ?? '')));

        PlatformConfig::set('video_ad_banner_top_template', (string) ($data['video_ad_banner_top_template'] ?? 'none'));
        PlatformConfig::set('video_ad_banner_bottom_template', (string) ($data['video_ad_banner_bottom_template'] ?? 'none'));
        PlatformConfig::setText('video_ad_banner_top_custom_html', VideoAdPresentation::sanitize((string) ($data['video_ad_banner_top_custom_html'] ?? '')));
        PlatformConfig::setText('video_ad_banner_bottom_custom_html', VideoAdPresentation::sanitize((string) ($data['video_ad_banner_bottom_custom_html'] ?? '')));
        PlatformConfig::setText('video_ad_banner_top_custom_script', (string) ($data['video_ad_banner_top_custom_script'] ?? ''));
        PlatformConfig::setText('video_ad_banner_bottom_custom_script', (string) ($data['video_ad_banner_bottom_custom_script'] ?? ''));

        PlatformConfig::set('video_ad_pop_enabled', !empty($data['video_ad_pop_enabled']) ? '1' : '0');
        PlatformConfig::set('video_ad_pop_template', (string) ($data['video_ad_pop_template'] ?? 'none'));
        PlatformConfig::set('video_ad_pop_delay_ms', (string) max(0, min(120000, (int) ($data['video_ad_pop_delay_ms'] ?? 3500))));
        PlatformConfig::set('video_ad_pop_title', (string) ($data['video_ad_pop_title'] ?? 'Información'));
        PlatformConfig::setText('video_ad_pop_custom_html', VideoAdPresentation::sanitize((string) ($data['video_ad_pop_custom_html'] ?? '')));
        PlatformConfig::set('video_ad_vast_enabled', !empty($data['video_ad_vast_enabled']) ? '1' : '0');
        PlatformConfig::setText('video_ad_vast_tag_url', (string) ($data['video_ad_vast_tag_url'] ?? ''));
        PlatformConfig::set('video_ad_vast_skip_seconds', (string) max(0, min(60, (int) ($data['video_ad_vast_skip_seconds'] ?? 5))));

        return redirect()->route('admin.panel', ['section' => 'banners'])->with('status', 'Banners y ventana emergente guardados.');
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

        $params = ['section' => $section];
        if ($section === 'videos') {
            $map = [
                'q' => '_filter_q',
                'moderation' => '_filter_moderation',
                'published' => '_filter_published',
            ];
            foreach ($map as $queryKey => $postKey) {
                $val = $request->input($postKey);
                if ($val !== null && $val !== '') {
                    $params[$queryKey] = $val;
                }
            }
        }

        return redirect()->route('admin.panel', $params);
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
        $rabbitMgmt = (bool) env('RABBITMQ_MANAGEMENT_URL') || (bool) env('RABBITMQ_HOST');

        $snapshots = [];
        try {
            $snapshots = app(IntegrationConnectivityService::class)->snapshots();
        } catch (\Throwable $e) {
            $snapshots = [
                'redis' => [
                    'uses_redis_for_cache' => $cache === 'redis',
                    'configured' => $redisHost,
                    'reachable' => null,
                    'label' => 'Error',
                    'detail' => $e->getMessage(),
                ],
                'rabbitmq' => [
                    'host_configured' => $rabbitHost,
                    'amqp_reachable' => null,
                    'management_ok' => null,
                    'label' => 'Error',
                    'detail' => $e->getMessage(),
                ],
            ];
        }

        return [
            'queue_connection' => $queue,
            'cache_driver' => $cache,
            'redis_extension' => $redisExt,
            'redis_host_configured' => $redisHost,
            'rabbitmq_host_configured' => $rabbitHost,
            'rabbitmq_management_reachable' => $rabbitMgmt,
            'redis' => $snapshots['redis'] ?? [],
            'rabbitmq' => $snapshots['rabbitmq'] ?? [],
        ];
    }

    private function serverMonitorSnapshot(): array
    {
        $memory = $this->memorySnapshot();
        $cpu = $this->cpuSnapshot();
        $disk = $this->diskSnapshot();
        $topCpu = $this->topProcesses('cpu');
        $topMem = $this->topProcesses('mem');

        return [
            'captured_at' => now()->toDateTimeString(),
            'memory' => $memory,
            'cpu' => $cpu,
            'disk' => $disk,
            'top_cpu' => $topCpu,
            'top_mem' => $topMem,
            'note' => 'Datos estimados en el momento del refresco.',
        ];
    }

    private function memorySnapshot(): array
    {
        $totalMb = 0;
        $availMb = 0;
        try {
            $raw = @file_get_contents('/proc/meminfo');
            if (is_string($raw) && $raw !== '') {
                if (preg_match('/^MemTotal:\s+(\d+)\s+kB/m', $raw, $m)) {
                    $totalMb = (int) round(((int) $m[1]) / 1024);
                }
                if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/m', $raw, $m2)) {
                    $availMb = (int) round(((int) $m2[1]) / 1024);
                } elseif (preg_match('/^MemFree:\s+(\d+)\s+kB/m', $raw, $m3)) {
                    $availMb = (int) round(((int) $m3[1]) / 1024);
                }
            }
        } catch (\Throwable $e) {
            $totalMb = 0;
            $availMb = 0;
        }

        $usedMb = max(0, $totalMb - $availMb);
        $usedPct = $totalMb > 0 ? (float) round(($usedMb / $totalMb) * 100, 1) : null;

        return [
            'total_mb' => $totalMb,
            'used_mb' => $usedMb,
            'free_mb' => $availMb,
            'used_pct' => $usedPct,
        ];
    }

    private function cpuSnapshot(): array
    {
        $load1 = null;
        $load5 = null;
        $cores = 1;
        try {
            $loads = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
            if (is_array($loads) && isset($loads[0], $loads[1])) {
                $load1 = (float) $loads[0];
                $load5 = (float) $loads[1];
            }
            $coresGuess = (int) trim((string) @shell_exec('nproc 2>/dev/null'));
            if ($coresGuess > 0) {
                $cores = $coresGuess;
            }
        } catch (\Throwable $e) {
            $cores = 1;
        }

        $usagePct = null;
        if ($load1 !== null && $cores > 0) {
            $usagePct = (float) round(min(100, max(0, ($load1 / $cores) * 100)), 1);
        }

        return [
            'load_1m' => $load1,
            'load_5m' => $load5,
            'cores' => $cores,
            'usage_pct' => $usagePct,
        ];
    }

    private function diskSnapshot(): array
    {
        $path = base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        $used = ($total !== false && $free !== false) ? ($total - $free) : false;

        $usedPct = null;
        if ($total && $used !== false && $total > 0) {
            $usedPct = (float) round(($used / $total) * 100, 1);
        }

        return [
            'path' => $path,
            'total_gb' => $total !== false ? round($total / 1073741824, 2) : null,
            'used_gb' => $used !== false ? round($used / 1073741824, 2) : null,
            'free_gb' => $free !== false ? round($free / 1073741824, 2) : null,
            'used_pct' => $usedPct,
        ];
    }

    private function topProcesses(string $sortBy = 'cpu'): array
    {
        $sort = $sortBy === 'mem' ? '-%mem' : '-%cpu';
        $cmd = 'ps -eo pid,comm,%cpu,%mem --sort=' . $sort . ' | head -n 8';
        $raw = (string) @shell_exec($cmd . ' 2>/dev/null');
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw ?: ''))));
        if (count($lines) <= 1) {
            return [];
        }

        $out = [];
        foreach (array_slice($lines, 1) as $line) {
            $parts = preg_split('/\s+/', $line, 4);
            if (!is_array($parts) || count($parts) < 4) {
                continue;
            }
            $out[] = [
                'pid' => (int) $parts[0],
                'name' => (string) $parts[1],
                'cpu' => (float) $parts[2],
                'mem' => (float) $parts[3],
            ];
        }

        return $out;
    }

    private function putSitemapProgress(string $key, int $value): void
    {
        try {
            Cache::put($key, $value, now()->addMinutes(10));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function forgetSitemapProgress(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function sitemapProgressKey(Request $request): string
    {
        $userId = optional($request->user())->id;
        if ($userId) {
            return 'admin:sitemap:progress:user:' . $userId;
        }

        return 'admin:sitemap:progress:session:' . $request->session()->getId();
    }

    private function posterBatchState(string $batchId): ?array
    {
        $raw = Cache::get('admin:poster-batch:' . $batchId);

        return is_array($raw) ? $raw : null;
    }

    private function putPosterBatchState(string $batchId, array $state): void
    {
        try {
            Cache::put('admin:poster-batch:' . $batchId, $state, now()->addHours(6));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function rememberPosterBatchId(Request $request, string $batchId): void
    {
        $userId = optional($request->user())->id;
        $key = $userId
            ? ('admin:poster-batch:last:user:' . $userId)
            : ('admin:poster-batch:last:session:' . $request->session()->getId());
        try {
            Cache::put($key, $batchId, now()->addHours(12));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function lastPosterBatchId(Request $request): string
    {
        $userId = optional($request->user())->id;
        $key = $userId
            ? ('admin:poster-batch:last:user:' . $userId)
            : ('admin:poster-batch:last:session:' . $request->session()->getId());

        return (string) Cache::get($key, '');
    }

    private function workerMediaPidKey(): string
    {
        return 'admin:worker:media:pid';
    }

    private function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            try {
                return @posix_kill($pid, 0);
            } catch (\Throwable $e) {
            }
        }

        $out = @shell_exec('ps -p ' . (int) $pid . ' -o pid=');

        return is_string($out) && trim($out) !== '';
    }
}
