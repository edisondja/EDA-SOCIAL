<?php

namespace App\Console\Commands;

use App\Services\VideoPreviewGenerationService;
use Illuminate\Console\Command;

class GenerateVideoPostersCommand extends Command
{
    protected $signature = 'videos:generate-posters
                            {--limit=40 : Máximo de vídeos a procesar por ejecución}
                            {--scope=missing : missing = solo sin portada válida; all = todos (sobrescribe)}
                            {--fixed-seek : Usar segundo fijo (FFMPEG_POSTER_SEEK) en vez de duración+ID}';

    protected $description = 'Genera portadas JPEG con ffmpeg (instante según duración por defecto, o fijo con --fixed-seek)';

    public function handle(VideoPreviewGenerationService $service): int
    {
        $limit = (int) $this->option('limit');
        $scope = strtolower((string) $this->option('scope')) === 'all' ? 'all' : 'missing';
        $durationAware = !$this->option('fixed-seek');

        $this->info(sprintf(
            'Portadas JPG · límite %d · ámbito %s · seek %s…',
            $limit,
            $scope === 'all' ? 'todas' : 'solo faltantes',
            $durationAware ? 'duración+ID' : 'fijo'
        ));

        $result = $service->processPosterBatchForAdmin($limit, $scope, $durationAware);

        $this->line('Creadas: '.$result['processed'].' · Omitidas: '.$result['skipped'].' · Fallidas: '.$result['failed']);

        foreach ($result['messages'] as $line) {
            $this->line($line);
        }

        return $result['failed'] > 0 && $result['processed'] === 0 ? 1 : 0;
    }
}
