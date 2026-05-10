<?php

use App\Video;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/explorar');

Route::get('/explorar', 'Web\ExploreController@index')->name('explore.index');
Route::post('/explorar/videos/{video}/cola-vista-tarjeta', 'Web\ExploreController@enqueueHoverCardMedia')
    ->middleware('throttle:60,1')
    ->name('explore.hover_card_media');

Route::get('/playvideo/{video}/{slug}', 'Web\PostController@show')
    ->where(['video' => '[0-9]+', 'slug' => '[^/]+'])
    ->name('posts.show');
Route::post('/playvideo/{video}/{slug}/comentarios', 'Web\PostController@storeComment')
    ->middleware('auth')
    ->where(['video' => '[0-9]+', 'slug' => '[^/]+'])
    ->name('posts.comments.store');
Route::post('/playvideo/{video}/{slug}/reportar', 'Web\PostController@storeReport')
    ->middleware(['auth', 'throttle:20,60'])
    ->where(['video' => '[0-9]+', 'slug' => '[^/]+'])
    ->name('posts.report');
Route::post('/playvideo/{video}/{slug}/valoracion', 'Web\PostController@storeRating')
    ->middleware(['auth', 'throttle:60,1'])
    ->where(['video' => '[0-9]+', 'slug' => '[^/]+'])
    ->name('posts.rating.store');

Route::get('/p/{video}', function (Video $video) {
    return redirect()->route('posts.show', ['video' => $video->id, 'slug' => $video->playSlug()], 301);
})->where(['video' => '[0-9]+']);
Route::post('/comentarios/{comment}/votar', 'Web\PostController@voteComment')->middleware('auth')->name('posts.comments.vote');

Route::middleware('guest')->group(function () {
    Route::get('/login', 'Web\AuthWebController@showLogin')->name('login');
    Route::post('/login', 'Web\AuthWebController@login');
    Route::get('/register', 'Web\AuthWebController@showRegister')->name('register');
    Route::post('/register', 'Web\AuthWebController@register')->name('register.store');
});

Route::post('/logout', 'Web\AuthWebController@logout')->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/publicar', 'Web\PublishController@create')->name('publish.create');
    Route::post('/publicar', 'Web\PublishController@store')->name('publish.store');
    Route::get('/cuenta', 'Web\AccountController@show')->name('account.show');
    Route::post('/cuenta/avatar', 'Web\AccountController@updateAvatar')
        ->middleware('throttle:20,60')
        ->name('account.avatar');
    Route::get('/cuenta/mis-videos', 'Web\MyVideosController@index')->name('account.videos.index');
    Route::get('/cuenta/mis-videos/{video}/editar', 'Web\MyVideosController@edit')
        ->whereNumber('video')
        ->name('account.videos.edit');
    Route::put('/cuenta/mis-videos/{video}', 'Web\MyVideosController@update')
        ->whereNumber('video')
        ->middleware('throttle:30,1')
        ->name('account.videos.update');
});

Route::middleware(['auth', 'admin_or_mod_web'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.panel', ['section' => 'seo']);
    });
    Route::get('/{section}', 'Web\AdminPanelController@show')
        ->where('section', 'seo|aspecto|banners|integraciones|verificacion|monitoreo|usuarios|videos|reportes|reddit|metricas')
        ->name('admin.panel');

    Route::post('/seo', 'Web\AdminPanelController@updateSeo')->name('admin.seo');
    Route::post('/menu-color', 'Web\AdminPanelController@updateMenuColor')->name('admin.menu_color');
    Route::post('/logo', 'Web\AdminPanelController@uploadLogo')->name('admin.logo');
    Route::post('/categoria', 'Web\AdminPanelController@storeCategory')->name('admin.category');
    Route::post('/integraciones', 'Web\AdminPanelController@updateIntegrations')->name('admin.integrations');
    Route::post('/verificacion', 'Web\AdminPanelController@uploadVerification')->name('admin.verification');
    Route::post('/sitemap', 'Web\AdminPanelController@writeSitemap')->name('admin.sitemap');
    Route::get('/sitemap/status', 'Web\AdminPanelController@sitemapGenerationStatus')->name('admin.sitemap_status');
    Route::get('/colas/estado', 'Web\AdminPanelController@queueMonitorStatus')
        ->middleware('throttle:60,1')
        ->name('admin.queue_status');
    Route::get('/workers/media/estado', 'Web\AdminPanelController@workerMediaStatus')->name('admin.worker_media_status');
    Route::post('/workers/media/encender', 'Web\AdminPanelController@startWorkerMedia')->name('admin.worker_media_start');
    Route::post('/reddit/importar', 'Web\AdminPanelController@importReddit')->name('admin.reddit');
    Route::get('/tendencias/google', 'Web\AdminPanelController@googleTrendsFetch')
        ->middleware('throttle:30,1')
        ->name('admin.google_trends');
    Route::post('/tendencias/google/geo', 'Web\AdminPanelController@saveGoogleTrendsGeo')
        ->name('admin.google_trends_geo');
    Route::post('/usuarios/{user}/rol', 'Web\AdminPanelController@updateUserRole')->name('admin.user_role');
    Route::post('/videos/{video}/bloquear', 'Web\AdminPanelController@blockVideo')->name('admin.video_block');
    Route::post('/videos/{video}/activar', 'Web\AdminPanelController@activateVideo')->name('admin.video_activate');
    Route::post('/videos/{video}/editar', 'Web\AdminPanelController@updateVideo')->name('admin.video_update');
    Route::post('/videos/{video}/hls', 'Web\AdminPanelController@generateVideoHls')->name('admin.video_hls_generate');
    Route::post('/videos/generar-previews', 'Web\AdminPanelController@generateVideoPreviews')->name('admin.video_previews_generate');
    Route::post('/videos/generar-portadas-lote', 'Web\AdminPanelController@generateVideoPostersBatch')->name('admin.video_posters_batch');
    Route::post('/videos/portadas/encolar-faltantes', 'Web\AdminPanelController@enqueueMissingPostersBatch')->name('admin.video_posters_enqueue');
    Route::get('/videos/portadas/estado', 'Web\AdminPanelController@missingPostersBatchStatus')->name('admin.video_posters_status');

    Route::post('/reportes/{report}/estado', 'Web\AdminPanelController@updateReportStatus')->name('admin.report_status');

    Route::post('/banners/plantillas', 'Web\AdminPanelController@storeBannerTemplate')->name('admin.banner_template_store');
    Route::post('/banners/plantillas/{template}', 'Web\AdminPanelController@updateBannerTemplate')->where('template', '[a-fA-F0-9\-]{36}')->name('admin.banner_template_update');
    Route::post('/banners/plantillas/{template}/eliminar', 'Web\AdminPanelController@deleteBannerTemplate')->where('template', '[a-fA-F0-9\-]{36}')->name('admin.banner_template_delete');
    Route::post('/banners/slots', 'Web\AdminPanelController@updateBannerSlots')->name('admin.banner_slots');
    Route::post('/usuarios/{user}/ban', 'Web\AdminPanelController@banUser')->name('admin.user_ban');
});

Route::get('/sitemap.xml', 'SitemapController@showIndex')->name('sitemap.index');
Route::get('/sitemap-posts-{page}.xml', 'SitemapController@showPostsChunk')
    ->whereNumber('page')
    ->name('sitemap.posts');
Route::get('/robots.txt', 'RobotsController@show');
