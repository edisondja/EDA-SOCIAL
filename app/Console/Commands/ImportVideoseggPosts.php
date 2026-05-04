<?php

namespace App\Console\Commands;

use App\Category;
use App\Hashtag;
use App\User;
use App\Video;
use App\VideoMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportVideoseggPosts extends Command
{
    protected $signature = 'videosegg:import-posts
                            {--videos-path= : Carpeta donde están los .mp4 (por defecto config videosegg.videos_path)}
                            {--imagenes-path= : Carpeta de miniaturas (opcional)}
                            {--previa-path= : Carpeta de archivos de previsualización (opcional)}
                            {--user-id= : ID de usuario autor (debe tener canal); por defecto primer usuario con canal}
                            {--limit=0 : Máximo de filas a importar (0 = sin límite)}
                            {--dry-run : Solo mostrar qué haría, sin copiar ni insertar}';

    protected $description = 'Importa la tabla posts de la BD videosegg (dump MySQL), copia medios al disco public y crea registros Video en EDA_SOCIAL.';

    /** @var int */
    protected $imported = 0;

    /** @var int */
    protected $dryWould = 0;

    /** @var int */
    protected $skipped = 0;

    /** @var int */
    protected $missingFile = 0;

    public function handle(): int
    {
        try {
            DB::connection('videosegg')->getPdo();
        } catch (\Throwable $e) {
            $this->error('No se puede conectar a la base "videosegg": ' . $e->getMessage());

            return 1;
        }

        if (!Schema::connection('videosegg')->hasTable('posts')) {
            $this->error('No existe la tabla posts en MySQL (conexión videosegg).');
            $this->line('El dump reciente suele estar en Documentos, por ejemplo:');
            $this->line('  ' . (string) config('videosegg.sql_dump_path'));
            $this->line('Cárgalo en MySQL con el comando del proyecto (requiere cliente mysql en PATH):');
            $this->line('  php artisan videosegg:load-sql');
            $this->line('O manualmente: mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS videosegg;" && mysql -u root -p videosegg < ruta/al/archivo.sql');

            return 1;
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
        $videosPath = $this->option('videos-path') ?: config('videosegg.videos_path');
        if ((!$videosPath || !is_dir($videosPath)) && $home) {
            $alt = $home . '/Documents/videosegg/videos';
            if (is_dir($alt)) {
                $videosPath = $alt;
                $this->warn('Usando carpeta de videos en Documentos: ' . $alt);
            }
        }
        $imagenesPath = $this->option('imagenes-path') ?: config('videosegg.imagenes_path');
        if ((!$imagenesPath || !is_dir($imagenesPath)) && $home) {
            $altImg = $home . '/Documents/videosegg/imagenes';
            if (is_dir($altImg)) {
                $imagenesPath = $altImg;
                $this->warn('Usando miniaturas en Documentos: ' . $altImg);
            }
        }
        $previaPath = $this->option('previa-path') ?: config('videosegg.previa_path');
        if ((!$previaPath || !is_dir($previaPath)) && $home) {
            $altPv = $home . '/Documents/videosegg/previa';
            if (is_dir($altPv)) {
                $previaPath = $altPv;
                $this->warn('Usando previas en Documentos: ' . $altPv);
            }
        }

        if (!$videosPath || !is_dir($videosPath)) {
            $this->error('Carpeta de videos no encontrada: ' . ($videosPath ?: '(vacío)'));
            $this->line('Define VIDEOSEGG_VIDEOS_PATH en .env o usa --videos-path=/ruta/a/videos');

            return 1;
        }

        $prefix = trim(config('videosegg.storage_prefix', 'videosegg'), '/');
        $author = $this->resolveAuthor();
        if (!$author || !$author->channel) {
            $this->error('No hay usuario autor con canal. Crea un usuario/canal o pasa --user-id=');

            return 1;
        }

        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $this->info('Origen videos: ' . $videosPath);
        if ($imagenesPath && is_dir($imagenesPath)) {
            $this->info('Origen miniaturas: ' . $imagenesPath);
        } else {
            $this->warn('Sin carpeta imagenes (miniaturas opcionales).');
        }
        if ($previaPath && is_dir($previaPath)) {
            $this->info('Origen previa: ' . $previaPath);
        } else {
            $this->warn('Sin carpeta previa (se usará el mismo video como vista previa si aplica).');
        }
        $this->info('Destino público: storage/app/public/' . $prefix . '/');
        $this->info('Autor: ' . $author->email . ' (canal #' . $author->channel->id . ')');
        if ($dry) {
            $this->warn('MODO DRY-RUN: no se escribirá en disco ni en la BD.');
        }

        $processed = 0;
        $query = DB::connection('videosegg')->table('posts')
            ->where(function ($q) {
                $q->where('tipo_post', 'video')
                    ->orWhereNull('tipo_post')
                    ->orWhere('tipo_post', '');
            })
            ->orderBy('id_post');

        $query->chunkById(150, function ($rows) use (
            $videosPath,
            $imagenesPath,
            $previaPath,
            $prefix,
            $author,
            $dry,
            $limit,
            &$processed
        ) {
            foreach ($rows as $row) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }
                $processed++;
                $this->importOneRow($row, $videosPath, $imagenesPath, $previaPath, $prefix, $author, $dry);
            }
        }, 'id_post');

        $this->newLine();
        if ($dry) {
            $this->info("Simulación: {$this->dryWould} filas · Omitidos (slug ya importado): {$this->skipped} · Sin archivo de video: {$this->missingFile}");
        } else {
            $this->info("Importados: {$this->imported} · Omitidos (ya existían): {$this->skipped} · Sin archivo de video en origen: {$this->missingFile}");
        }

        if (!$dry && $this->imported > 0) {
            $this->comment('Asegúrate de tener el enlace simbólico: php artisan storage:link');
        }

        return 0;
    }

    /**
     * @param  object  $row
     */
    protected function importOneRow(
        $row,
        string $videosPath,
        ?string $imagenesPath,
        ?string $previaPath,
        string $prefix,
        User $author,
        bool $dry
    ): void {
        $oldId = (int) $row->id_post;
        if (Video::query()->where('slug', 'like', '%-vsg' . $oldId)->exists()) {
            $this->skipped++;

            return;
        }

        $rutaVideo = isset($row->ruta_video) ? trim((string) $row->ruta_video) : '';
        if ($rutaVideo === '') {
            $this->missingFile++;

            return;
        }

        $videoBasename = basename(str_replace('\\', '/', $rutaVideo));
        $srcVideo = $videosPath . '/' . $videoBasename;
        if (!is_file($srcVideo)) {
            $srcVideo = $videosPath . '/' . ltrim(str_replace('\\', '/', $rutaVideo), '/');
        }
        if (!is_file($srcVideo)) {
            $this->missingFile++;
            $this->warn("Sin archivo: {$videoBasename} (id_post {$oldId})");

            return;
        }

        $destVideoRel = $prefix . '/videos/' . $videoBasename;
        $destVideoAbs = storage_path('app/public/' . $destVideoRel);

        $titulo = isset($row->titulo) ? trim((string) $row->titulo) : '';
        if ($titulo === '') {
            $titulo = 'Publicación #' . $oldId;
        }
        $title = Str::limit($titulo, 175, '');

        $slugBase = Str::slug(Str::limit($titulo, 100, ''));
        if ($slugBase === '') {
            $slugBase = 'video';
        }
        $slug = Str::limit($slugBase, 200, '') . '-vsg' . $oldId;
        if (strlen($slug) > 218) {
            $slug = 'vsg-' . $oldId . '-' . substr(sha1($slugBase), 0, 10);
        }

        $descripcion = isset($row->descripcion) ? trim(strip_tags((string) $row->descripcion)) : null;
        $publishedAt = isset($row->fecha_publicacion) && $row->fecha_publicacion
            ? $row->fecha_publicacion
            : now();

        $duration = $this->parseDuration(isset($row->duracion) ? (string) $row->duracion : '0');

        $thumbRel = null;
        $rutaImagen = isset($row->ruta_imagen) ? trim((string) $row->ruta_imagen) : '';
        if ($rutaImagen !== '' && $imagenesPath && is_dir($imagenesPath)) {
            $imgBase = basename(str_replace('\\', '/', $rutaImagen));
            $srcImg = $imagenesPath . '/' . $imgBase;
            if (!is_file($srcImg)) {
                $srcImg = $imagenesPath . '/' . ltrim(str_replace('\\', '/', $rutaImagen), '/');
            }
            if (is_file($srcImg)) {
                $thumbRel = $prefix . '/imagenes/' . $imgBase;
                if (!$dry) {
                    File::ensureDirectoryExists(dirname(storage_path('app/public/' . $thumbRel)));
                    if (!is_file(storage_path('app/public/' . $thumbRel))) {
                        File::copy($srcImg, storage_path('app/public/' . $thumbRel));
                    }
                }
            }
        }

        $previewRel = null;
        $previa = isset($row->previa) ? trim((string) $row->previa) : '';
        if ($previa !== '' && $previaPath && is_dir($previaPath)) {
            $pvBase = basename(str_replace('\\', '/', $previa));
            $srcPv = $previaPath . '/' . $pvBase;
            if (!is_file($srcPv)) {
                $srcPv = $previaPath . '/' . ltrim(str_replace('\\', '/', $previa), '/');
            }
            if (is_file($srcPv)) {
                $previewRel = $prefix . '/previa/' . $pvBase;
                if (!$dry) {
                    File::ensureDirectoryExists(dirname(storage_path('app/public/' . $previewRel)));
                    if (!is_file(storage_path('app/public/' . $previewRel))) {
                        File::copy($srcPv, storage_path('app/public/' . $previewRel));
                    }
                }
            }
        }

        if (!$dry) {
            File::ensureDirectoryExists(dirname($destVideoAbs));
            if (!is_file($destVideoAbs)) {
                File::copy($srcVideo, $destVideoAbs);
            }
        }

        $videoUrl = $this->publicUrl($destVideoRel);
        $thumbUrl = $thumbRel ? $this->publicUrl($thumbRel) : null;
        $previewUrl = $previewRel ? $this->publicUrl($previewRel) : $videoUrl;

        if ($dry) {
            $this->line("[dry-run] #{$oldId} → {$title} → {$videoUrl}");
            $this->dryWould++;

            return;
        }

        DB::transaction(function () use (
            $author,
            $slug,
            $title,
            $descripcion,
            $videoUrl,
            $previewUrl,
            $thumbUrl,
            $duration,
            $publishedAt,
            $row
        ) {
            $video = Video::create([
                'channel_id' => $author->channel->id,
                'author_id' => $author->id,
                'title' => $title,
                'slug' => $slug,
                'description' => $descripcion,
                'video_url' => $videoUrl,
                'preview_url' => $previewUrl,
                'thumbnail_url' => $thumbUrl,
                'duration_seconds' => $duration,
                'views_count' => 0,
                'likes_count' => 0,
                'dislikes_count' => 0,
                'is_published' => true,
                'published_at' => $publishedAt,
                'moderation_status' => 'active',
            ]);

            VideoMedia::create([
                'video_id' => $video->id,
                'type' => 'video',
                'url' => $videoUrl,
                'position' => 0,
            ]);

            $catIds = $this->syncCategoriesFromString(isset($row->categoria) ? (string) $row->categoria : '');
            if ($catIds !== []) {
                $video->categories()->sync($catIds);
            }

            $tags = $this->hashtagsFromDescription((string) ($row->descripcion ?? ''));
            if ($tags !== []) {
                $ids = collect($tags)->map(function ($name) {
                    return Hashtag::firstOrCreate(['name' => $name])->id;
                })->unique()->values()->all();
                $video->hashtags()->sync($ids);
            }
        });

        $this->imported++;
    }

    protected function publicUrl(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $segments = explode('/', $relativePath);

        return rtrim(config('app.url'), '/') . '/storage/' . implode('/', array_map('rawurlencode', $segments));
    }

    protected function parseDuration(string $dur): int
    {
        $dur = trim(strip_tags($dur));
        if ($dur === '') {
            return 0;
        }
        $parts = array_map('intval', explode(':', $dur));
        $n = count($parts);
        if ($n === 3) {
            return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
        }
        if ($n === 2) {
            return $parts[0] * 60 + $parts[1];
        }
        if ($n === 1) {
            return max(0, $parts[0]);
        }

        return 0;
    }

    /**
     * @return int[]
     */
    protected function syncCategoriesFromString(string $categoria): array
    {
        $parts = array_filter(array_map('trim', explode(',', $categoria)));
        $ids = [];
        foreach ($parts as $name) {
            if ($name === '') {
                continue;
            }
            $slug = Str::slug(Str::limit($name, 100, '')) ?: Str::lower(Str::random(8));
            $cat = Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::limit($name, 120, '')]
            );
            $ids[] = $cat->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return string[]
     */
    protected function hashtagsFromDescription(string $html): array
    {
        if (!preg_match_all('/#([\p{L}\p{N}_]+)/u', strip_tags($html), $m)) {
            return [];
        }

        return collect($m[1])
            ->map(function ($t) {
                return Str::lower(Str::limit($t, 80, ''));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveAuthor(): ?User
    {
        $uid = $this->option('user-id');
        if ($uid) {
            $u = User::query()->with('channel')->find((int) $uid);
            if (!$u) {
                return null;
            }
            if (!$u->channel) {
                $u->channel()->create([
                    'slug' => Str::slug(($u->username ?: 'canal') . '-' . $u->id),
                    'display_name' => $u->name,
                ]);
                $u->load('channel');
            }

            return $u;
        }

        return User::query()->whereHas('channel')->with('channel')->first();
    }
}
