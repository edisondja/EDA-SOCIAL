<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/explorar');

Route::get('/explorar', 'Web\ExploreController@index')->name('explore.index');

Route::get('/p/{video}', 'Web\PostController@show')->name('posts.show');
Route::post('/p/{video}/comentarios', 'Web\PostController@storeComment')->middleware('auth')->name('posts.comments.store');
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
});

Route::middleware(['auth', 'admin_or_mod_web'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.panel', ['section' => 'seo']);
    });
    Route::get('/{section}', 'Web\AdminPanelController@show')
        ->where('section', 'seo|aspecto|integraciones|verificacion|usuarios|reddit')
        ->name('admin.panel');

    Route::post('/seo', 'Web\AdminPanelController@updateSeo')->name('admin.seo');
    Route::post('/menu-color', 'Web\AdminPanelController@updateMenuColor')->name('admin.menu_color');
    Route::post('/logo', 'Web\AdminPanelController@uploadLogo')->name('admin.logo');
    Route::post('/categoria', 'Web\AdminPanelController@storeCategory')->name('admin.category');
    Route::post('/integraciones', 'Web\AdminPanelController@updateIntegrations')->name('admin.integrations');
    Route::post('/verificacion', 'Web\AdminPanelController@uploadVerification')->name('admin.verification');
    Route::post('/sitemap', 'Web\AdminPanelController@writeSitemap')->name('admin.sitemap');
    Route::post('/reddit/importar', 'Web\AdminPanelController@importReddit')->name('admin.reddit');
    Route::post('/usuarios/{user}/rol', 'Web\AdminPanelController@updateUserRole')->name('admin.user_role');
    Route::post('/videos/{video}/bloquear', 'Web\AdminPanelController@blockVideo')->name('admin.video_block');
    Route::post('/usuarios/{user}/ban', 'Web\AdminPanelController@banUser')->name('admin.user_ban');
});

Route::get('/sitemap.xml', 'SitemapController@show');
