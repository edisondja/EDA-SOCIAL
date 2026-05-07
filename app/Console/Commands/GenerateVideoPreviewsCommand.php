<?php

namespace App\Console\Commands;

use App\Services\VideoPreviewGenerationService;
use Illuminate\Console\Command;

class GenerateVideoPreviewsCommand extends Command
{
    protected $signature = 'videos:generate-previews {--limit=30 : Cuántos videos procesar como máximo}';

    protected $description = 'Genera poster (JPEG) y clip de vista previa (MP4) con ffmpeg para videos sin miniatura o sin preview';

    public function handle(VideoPreviewGenerationService $service): int
    {
        $limit = (int) $this->option('limit');
        $this->info('Procesando hasta '.$limit.' videos sin poster y/o vista previa…');

        $result = $service->processBatchMissing($limit);

        $this->line('Listos: '.$result['processed'].' · Omitidos: '.$result['skipped'].' · Fallidos: '.$result['failed']);

        foreach ($result['messages'] as $line) {
            $this->line($line);
        }

        return $result['failed'] > 0 && $result['processed'] === 0 ? 1 : 0;
    }
}
