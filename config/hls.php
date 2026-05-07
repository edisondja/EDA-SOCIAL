<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HLS on-demand
    |--------------------------------------------------------------------------
    | Convierte MP4 locales a HLS (m3u8 + ts) cuando se visita el video por
    | primera vez. El proceso corre en cola para no bloquear la respuesta.
    */
    'enabled' => env('HLS_ENABLED', true),
    'ffmpeg_binary' => env('HLS_FFMPEG_BINARY', env('FFMPEG_BINARY', 'ffmpeg')),
    'segment_time' => (int) env('HLS_SEGMENT_TIME', 6),
    'crf' => (int) env('HLS_CRF', 24),
    'preset' => env('HLS_PRESET', 'veryfast'),
    'delete_source_mp4' => env('HLS_DELETE_SOURCE_MP4', false),
];

