<?php

namespace App\Console\Commands;

use App\Services\VideoPreviewGenerationService;
use Illuminate\Console\Command;

class FfmpegSmokeTestCommand extends Command
{
    protected $signature = 'ffmpeg:smoke-test {--keep : Conservar el JPG temporal en storage/app}';

    protected $description = 'Prueba ffmpeg: genera un JPG desde un vídeo sintético (lavfi), sin tocar la base de datos';

    public function handle(VideoPreviewGenerationService $previews): int
    {
        $ffmpeg = $previews->resolveFfmpegBinary();
        if ($ffmpeg === null) {
            $this->error('No se encontró ffmpeg. Instalalo en el sistema o definí FFMPEG_BINARY en .env.');

            return self::FAILURE;
        }

        $this->line('Binario: '.$ffmpeg);

        $name = 'ffmpeg-smoke-test-'.uniqid('', true).'.jpg';
        $path = storage_path('app/'.$name);

        $cmd = sprintf(
            '%s -y -hide_banner -loglevel error -f lavfi -i %s -frames:v 1 -q:v 5 %s',
            escapeshellarg($ffmpeg),
            escapeshellarg('color=c=gray:s=160x120:d=0.1'),
            escapeshellarg($path)
        );

        $lines = [];
        $code = 0;
        @exec($cmd.' 2>&1', $lines, $code);

        if ($code !== 0 || !is_readable($path) || filesize($path) < 32) {
            $this->error('ffmpeg terminó con error (código '.$code.').');
            if ($lines !== []) {
                $this->line(implode("\n", $lines));
            }
            @unlink($path);

            return self::FAILURE;
        }

        $bytes = (int) filesize($path);
        $this->info('Listo: JPG de prueba · '.$bytes.' bytes · '.$path);

        if (!$this->option('keep')) {
            @unlink($path);
            $this->line('Archivo temporal eliminado.');
        }

        return self::SUCCESS;
    }
}
