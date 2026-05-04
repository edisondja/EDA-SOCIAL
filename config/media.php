<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compresión de video con FFmpeg (opcional)
    |--------------------------------------------------------------------------
    | Si FFMPEG_ENABLED=false o el binario no existe, el job omite la compresión.
    */
    'ffmpeg' => [
        'enabled' => env('FFMPEG_ENABLED', false),
        'binary' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),
        'crf' => (int) env('FFMPEG_CRF', 28),
        'preset' => env('FFMPEG_PRESET', 'medium'),
        'max_width' => (int) env('FFMPEG_MAX_WIDTH', 1280),
        'audio_bitrate' => env('FFMPEG_AUDIO_BITRATE', '128k'),
        'timeout' => (int) env('FFMPEG_TIMEOUT', 900),
        'min_bytes_to_process' => (int) env('FFMPEG_MIN_BYTES', 200000),
        'max_bytes_to_process' => (int) env('FFMPEG_MAX_BYTES', 500 * 1024 * 1024),
    ],

];
