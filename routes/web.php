<?php

use App\Video;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/explorar');

Route::get('/explorar', 'Web\ExploreController@index')->name('explore.index');

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
});

Route::post('/logout', 'Web\AuthWebController@logout')->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/publicar', 'Web\PublishController@create')->name('publish.create');
    Route::post('/publicar', 'Web\PublishController@store')->name('publish.store');
    Route::get('/cuenta', 'Web\AccountController@show')->name('account.show');
    Route::post('/cuenta/avatar', 'Web\AccountController@updateAvatar')
        ->middleware('throttle:20,60')
        ->name('account.avatar');
});

Route::middleware(['auth', 'admin_or_mod_web'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.panel', ['section' => 'seo']);
    });
    Route::get('/{section}', 'Web\AdminPanelController@show')
        ->where('section', 'seo|aspecto|banners|integraciones|verificacion|usuarios|videos|reportes|reddit|metricas')
        ->name('admin.panel');

    Route::post('/seo', 'Web\AdminPanelController@updateSeo')->name('admin.seo');
    Route::post('/menu-color', 'Web\AdminPanelController@updateMenuColor')->name('admin.menu_color');
    Route::post('/logo', 'Web\AdminPanelController@uploadLogo')->name('admin.logo');
    Route::post('/categoria', 'Web\AdminPanelController@storeCategory')->name('admin.category');
    Route::post('/integraciones', 'Web\AdminPanelController@updateIntegrations')->name('admin.integrations');
    Route::post('/verificacion', 'Web\AdminPanelController@uploadVerification')->name('admin.verification');
    Route::post('/sitemap', 'Web\AdminPanelController@writeSitemap')->name('admin.sitemap');
    Route::get('/sitemap/status', 'Web\AdminPanelController@sitemapGenerationStatus')->name('admin.sitemap_status');
    Route::post('/reddit/importar', 'Web\AdminPanelController@importReddit')->name('admin.reddit');
    Route::post('/usuarios/{user}/rol', 'Web\AdminPanelController@updateUserRole')->name('admin.user_role');
    Route::post('/videos/{video}/bloquear', 'Web\AdminPanelController@blockVideo')->name('admin.video_block');
    Route::post('/videos/{video}/activar', 'Web\AdminPanelController@activateVideo')->name('admin.video_activate');
    Route::post('/videos/{video}/editar', 'Web\AdminPanelController@updateVideo')->name('admin.video_update');
    Route::post('/videos/{video}/hls', 'Web\AdminPanelController@generateVideoHls')->name('admin.video_hls_generate');
    Route::post('/videos/generar-previews', 'Web\AdminPanelController@generateVideoPreviews')->name('admin.video_previews_generate');

    Route::post('/reportes/{report}/estado', 'Web\AdminPanelController@updateReportStatus')->name('admin.report_status');

    Route::post('/banners/plantillas', 'Web\AdminPanelController@storeBannerTemplate')->name('admin.banner_template_store');
    Route::post('/banners/plantillas/{template}', 'Web\AdminPanelController@updateBannerTemplate')->where('template', '[a-fA-F0-9\-]{36}')->name('admin.banner_template_update');
    Route::post('/banners/plantillas/{template}/eliminar', 'Web\AdminPanelController@deleteBannerTemplate')->where('template', '[a-fA-F0-9\-]{36}')->name('admin.banner_template_delete');
    Route::post('/banners/slots', 'Web\AdminPanelController@updateBannerSlots')->name('admin.banner_slots');
    Route::post('/usuarios/{user}/ban', 'Web\AdminPanelController@banUser')->name('admin.user_ban');
});

Route::get('/sitemap.xml', 'SitemapController@show');
Route::get('/robots.txt', 'RobotsController@show');
