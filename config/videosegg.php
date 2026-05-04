<?php

$home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';

return [

    /*
    |--------------------------------------------------------------------------
    | Dump SQL reciente (tabla posts) en Documentos
    |--------------------------------------------------------------------------
    |
    | Por defecto: ~/Documents/videosegg/dbvideosegg_2026_04_30.sql
    | Úsalo con: php artisan videosegg:load-sql
    |
    */
    'sql_dump_path' => env('VIDEOSEGG_SQL_PATH', $home ? $home . '/Documents/videosegg/dbvideosegg_2026_04_30.sql' : ''),

    /*
    |--------------------------------------------------------------------------
    | Carpeta de origen (p. ej. archivos copiados desde el servidor videosegg)
    |--------------------------------------------------------------------------
    |
    | Por defecto se asume la carpeta "videos" dentro de Descargas del usuario.
    | Las rutas en la BD antigua son relativas (videos/archivo.mp4); aquí se
    | busca por nombre de archivo (basename) dentro de videos-path.
    |
    */
    'videos_path' => env('VIDEOSEGG_VIDEOS_PATH', $home ? $home . '/Downloads/videos' : ''),

    'imagenes_path' => env('VIDEOSEGG_IMAGENES_PATH', $home ? $home . '/Downloads/imagenes' : ''),

    'previa_path' => env('VIDEOSEGG_PREVIA_PATH', $home ? $home . '/Downloads/previa' : ''),

    /*
    |--------------------------------------------------------------------------
    | Destino dentro del disco public de Laravel
    |--------------------------------------------------------------------------
    */
    'storage_prefix' => 'videosegg',

];
