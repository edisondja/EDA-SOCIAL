<?php

namespace App\Console\Commands;

use App\Services\VideoPreviewGenerationService;
use Illuminate\Console\Command;

class GenerateVideoPostersCommand extends Command
{
    protected $signature = 'videos:generate-posters {--limit=40 : Máximo de portadas JPEG a generar por ejecución}';

    protected $description = 'Genera solo portadas JPEG (ffmpeg) para vídeos sin miniatura o con miniatura que es un archivo de vídeo';

    public function handle(VideoPreviewGenerationService $service): int
    {
        $limit = (int) $this->option('limit');
        $this->info('Generando hasta '.$limit.' portadas (solo JPG)…');

        $result = $service->processMissingPostersBatch($limit);

        $this->line('Creadas: '.$result['processed'].' · Omitidas: '.$result['skipped'].' · Fallidas: '.$result['failed']);

        foreach ($result['messages'] as $line) {
            $this->line($line);
        }

        return $result['failed'] > 0 && $result['processed'] === 0 ? 1 : 0;
    }
}
