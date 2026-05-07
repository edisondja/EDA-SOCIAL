<?php

return [
    /*
    | Ruta al binario ffmpeg. Por defecto se busca en PATH (ffmpeg).
    | Ejemplo: /usr/local/bin/ffmpeg
    */
    'binary' => env('FFMPEG_BINARY', 'ffmpeg'),

    /*
    | Segundo del vídeo donde capturar el poster (miniatura).
    */
    'poster_seek_seconds' => (float) env('FFMPEG_POSTER_SEEK', 1),

    /*
    | Duración máxima del clip de vista previa (silenciado, MP4 H.264).
    */
    'preview_duration_seconds' => (int) env('FFMPEG_PREVIEW_DURATION', 12),

    /*
    | Ancho máximo del preview (altura proporcional).
    */
    'preview_max_width' => (int) env('FFMPEG_PREVIEW_MAX_WIDTH', 720),
];
