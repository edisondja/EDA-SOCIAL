<?php

namespace App\Console\Commands;

use App\Services\VideoPublisher;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportVideosFromFolder extends Command
{
    protected $signature = 'videos:import-from-folder
                            {path? : Carpeta con archivos de vídeo (por defecto: VIDEO_IMPORT_FOLDER o ~/Downloads/videos)}
                            {--user-id= : ID de usuario autor (debe tener canal)}
                            {--limit=0 : Máximo de archivos a importar (0 = sin límite)}
                            {--dry-run : No copia archivos ni inserta en la base de datos}
                            {--prefix=local-imports : Subcarpeta dentro de storage/app/public}';

    protected $description = 'Importa vídeos desde una carpeta local: copia a disco public y crea publicaciones (VideoPublisher).';

    /** @var array<int, string> */
    protected static $extensions = ['mp4', 'mov', 'webm', 'mkv', 'm4v', 'avi', 'ogv'];

    public function handle(): int
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
        $defaultPath = env('VIDEO_IMPORT_FOLDER', $home ? $home . '/Downloads/videos' : '');
        $path = $this->argument('path') ?: $defaultPath;
        $path = $path ? realpath($path) : false;

        if (!$path || !is_dir($path)) {
            $this->error('Carpeta no encontrada o no legible: ' . ($this->argument('path') ?: $defaultPath));

            return 1;
        }

        $author = $this->resolveAuthor();
        if (!$author || !$author->channel) {
            $this->error('No hay usuario con canal. Crea uno o usa --user-id=');

            return 1;
        }

        $limit = max(0, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');
        $prefix = trim((string) $this->option('prefix'), '/');
        if ($prefix === '') {
            $prefix = 'local-imports';
        }

        $files = $this->collectVideoFiles($path);
        if ($files->isEmpty()) {
            $this->warn('No se encontraron archivos de vídeo en: ' . $path);

            return 0;
        }

        $this->info('Carpeta: ' . $path);
        $this->info('Archivos candidatos: ' . $files->count() . ($limit > 0 ? ' (límite ' . $limit . ')' : ''));
        $this->info('Autor: ' . $author->email . ' (canal #' . $author->channel->id . ')');
        if ($dry) {
            $this->warn('MODO DRY-RUN: no se escribirá disco ni base de datos.');
        }

        $imported = 0;
        foreach ($files as $absPath) {
            if ($limit > 0 && $imported >= $limit) {
                break;
            }
            $basename = basename($absPath);
            $title = $this->titleFromFilename($basename);
            if ($dry) {
                $this->line(' · [dry-run] ' . $basename . ' → «' . $title . '»');
                $imported++;

                continue;
            }

            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION) ?: 'mp4');
            $destName = Str::uuid()->toString() . '.' . $ext;
            $destRel = $prefix . '/' . $destName;
            $destFull = Storage::disk('public')->path($destRel);
            File::ensureDirectoryExists(dirname($destFull));
            if (!@copy($absPath, $destFull)) {
                $this->error('No se pudo copiar: ' . $basename);

                continue;
            }
            $url = Storage::disk('public')->url($destRel);

            VideoPublisher::createFromValidated($author, [
                'title' => $title,
                'description' => 'Importado desde carpeta local.',
                'video_url' => $url,
                'preview_url' => null,
                'thumbnail_url' => null,
                'media_items' => [
                    ['type' => 'video', 'url' => $url],
                ],
                'category_ids' => [],
                'hashtag_names' => [],
                'is_published' => true,
            ]);

            $this->info(' ✓ ' . $basename);
            $imported++;
        }

        $this->newLine();
        $this->info($dry ? 'Simulados: ' . $imported : 'Importados: ' . $imported);
        if (!$dry && $imported > 0) {
            $this->comment('Asegúrate de tener: php artisan storage:link');
        }

        return 0;
    }

    protected function resolveAuthor(): ?User
    {
        $id = $this->option('user-id');
        if ($id) {
            return User::query()->with('channel')->find((int) $id);
        }

        return User::query()->whereHas('channel')->with('channel')->orderBy('id')->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, string> rutas absolutas
     */
    protected function collectVideoFiles(string $dir)
    {
        $out = collect();
        foreach (File::files($dir) as $fileInfo) {
            $path = $fileInfo->getRealPath();
            if (!$path || !is_file($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, self::$extensions, true)) {
                continue;
            }
            $out->push($path);
        }

        return $out->sort()->values();
    }

    protected function titleFromFilename(string $basename): string
    {
        $name = pathinfo($basename, PATHINFO_FILENAME);
        $name = str_replace(['_', '-'], ' ', $name);
        $name = preg_replace('/^[0-9]+/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if ($name === '') {
            $name = 'Vídeo importado';
        }

        return Str::limit($name, 180, '');
    }
}
