<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/auth/register', 'Api\AuthController@register');
Route::post('/auth/login', 'Api\AuthController@login');

Route::get('/platform-settings', 'Api\PlatformSettingController@index');
Route::get('/categories', 'Api\CategoryController@index');
Route::get('/videos', 'Api\VideoController@index');
Route::get('/videos/{video}', 'Api\VideoController@show');
Route::get('/videos/{video}/comments', 'Api\CommentController@index');

Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me', 'Api\AuthController@me');
    Route::get('/notifications', 'Api\NotificationController@index')->middleware('throttle:120,1');
    Route::get('/notifications/unread-count', 'Api\NotificationController@unreadCount')->middleware('throttle:120,1');
    Route::post('/notifications/read-all', 'Api\NotificationController@markAllRead')->middleware('throttle:30,1');
    Route::post('/notifications/{id}/read', 'Api\NotificationController@markAsRead')->middleware('throttle:120,1');
    Route::post('/videos', 'Api\VideoController@store');
    Route::post('/uploads/media', 'Api\UploadController@media');
    Route::post('/videos/{video}/comments', 'Api\CommentController@store');
    Route::post('/comments/{comment}/vote', 'Api\CommentController@vote');

    Route::prefix('admin')->middleware('admin_or_mod')->group(function () {
        Route::get('/platform-settings', 'Api\PlatformSettingController@adminShow');
        Route::post('/platform-settings/menu-color', 'Api\PlatformSettingController@updateMenuColor');
        Route::post('/platform-settings/logo', 'Api\PlatformSettingController@uploadLogo');
        Route::post('/platform-settings/favicon', 'Api\PlatformSettingController@uploadFavicon');
        Route::post('/platform-settings/favicon/clear', 'Api\PlatformSettingController@clearFavicon');
        Route::post('/platform-settings/seo', 'Api\PlatformSettingController@updateSeo');
        Route::post('/platform-settings/integrations', 'Api\PlatformSettingController@updateIntegrations');
        Route::post('/platform-settings/video-ads', 'Api\PlatformSettingController@updateVideoAds');
        Route::post('/platform-settings/verification-txt', 'Api\PlatformSettingController@uploadVerificationTxt');
        Route::post('/platform-settings/sitemap-file', 'Api\PlatformSettingController@writeSitemapFile');
        Route::post('/reddit/import', 'Api\RedditImportController@import');
        Route::post('/categories', 'Api\CategoryController@store');
        Route::get('/dashboard', 'Api\AdminController@dashboard');
        Route::get('/users', 'Api\AdminController@users');
        Route::get('/videos/search', 'Api\AdminController@searchVideos');
        Route::post('/videos/{video}/block', 'Api\AdminController@blockVideo');
        Route::post('/users/{user}/ban', 'Api\AdminController@banUser');
        Route::post('/users/{user}/role', 'Api\AdminController@updateUserRole');
    });
});
