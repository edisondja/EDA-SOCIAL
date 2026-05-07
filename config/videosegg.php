<?php

$home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';

$defaultSql = '';
if ($home) {
    $candidates = [
        $home . '/Documents/videosegg/dbvideosegg_2026_04_30_fixed.sql',
        $home . '/Documents/videosegg/dbvideosegg_2026_04_30.sql',
        $home . '/Documents/Videosegg/dbvideosegg_2026_04_30_fixed.sql',
        $home . '/Documents/Videosegg/dbvideosegg_2026_04_30.sql',
        $home . '/Documents/videosegg_2026.sql',
    ];
    foreach ($candidates as $c) {
        if (is_readable($c)) {
            $defaultSql = $c;
            break;
        }
    }
    if ($defaultSql === '') {
        $defaultSql = $home . '/Documents/videosegg/dbvideosegg_2026_04_30.sql';
    }
}

$defaultVideos = '';
$laravelVideos = dirname(__DIR__) . '/storage/app/public/videosegg/videos';
if (is_dir($laravelVideos)) {
    foreach (glob($laravelVideos . '/*.{mp4,webm,mov,mkv,m4v,avi}', GLOB_BRACE) ?: [] as $_) {
        $defaultVideos = $laravelVideos;
        break;
    }
}
if ($defaultVideos === '' && $home) {
    $defaultVideos = $home . '/Downloads/videos';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Dump SQL reciente (tabla posts) en Documentos
    |--------------------------------------------------------------------------
    |
    | Por defecto: ~/Documents/videosegg/ (dump corregido *_fixed.sql si existe, si no el .sql estándar).
    | Úsalo con: php artisan videosegg:load-sql
    |
    */
    'sql_dump_path' => env('VIDEOSEGG_SQL_PATH', $defaultSql),

    /*
    |--------------------------------------------------------------------------
    | Carpeta de origen (p. ej. archivos copiados desde el servidor videosegg)
    |--------------------------------------------------------------------------
    |
    | Si ya hay vídeos en storage/app/public/videosegg/videos, se usa esa ruta;
    | si no, ~/Downloads/videos. Las rutas en la BD antigua suelen ser relativas;
    | el importador busca por basename dentro de videos-path.
    |
    */
    'videos_path' => env('VIDEOSEGG_VIDEOS_PATH', $defaultVideos),

    'imagenes_path' => env('VIDEOSEGG_IMAGENES_PATH', $home ? $home . '/Documents/videosegg/imagenes' : ''),

    'previa_path' => env('VIDEOSEGG_PREVIA_PATH', $home ? $home . '/Documents/videosegg/previa' : ''),

    /*
    |--------------------------------------------------------------------------
    | Destino dentro del disco public de Laravel
    |--------------------------------------------------------------------------
    */
    'storage_prefix' => 'videosegg',

];
