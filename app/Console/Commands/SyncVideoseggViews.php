<?php

namespace App\Console\Commands;

use App\Support\VideoseggPostViews;
use App\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncVideoseggViews extends Command
{
    protected $signature = 'videosegg:sync-views
                            {--dry-run : Solo listar coincidencias y cambios, sin guardar}
                            {--additive : Mantener vistas actuales y sumar las del legado (útil una sola vez tras el go-live)}
                            {--limit=0 : Máximo de filas posts a procesar (0 = todas)}';

    protected $description = 'Copia el contador de vistas desde la BD MySQL videosegg (tabla posts) hacia videos.views_count en EDA_SOCIAL.';

    public function handle(): int
    {
        try {
            DB::connection('videosegg')->getPdo();
        } catch (\Throwable $e) {
            $this->error('No hay conexión a videosegg (VIDEOSEGG_* en .env): ' . $e->getMessage());

            return 1;
        }

        if (!Schema::connection('videosegg')->hasTable('posts')) {
            $this->error('No existe la tabla posts en la conexión videosegg.');

            return 1;
        }

        $viewsCol = VideoseggPostViews::resolveViewsColumn();
        if ($viewsCol === null) {
            $this->error('En `posts` no aparece ninguna columna conocida de vistas (' . implode(', ', VideoseggPostViews::CANDIDATE_COLUMNS) . ').');
            $this->line('Revisá el esquema del dump y, si el nombre es otro, ampliá CANDIDATE_COLUMNS en App\\Support\\VideoseggPostViews.');

            return 1;
        }

        $this->info("Columna de vistas en legado: <fg=cyan>{$viewsCol}</>");

        $dry = (bool) $this->option('dry-run');
        $additive = (bool) $this->option('additive');
        $limit = max(0, (int) $this->option('limit'));

        if (!Schema::hasColumn('videos', 'videosegg_post_id')) {
            $this->warn('Falta la columna videos.videosegg_post_id. Ejecutá: php artisan migrate');
        }

        $updated = 0;
        $skippedNoVideo = 0;
        $processed = 0;

        $query = DB::connection('videosegg')->table('posts')
            ->where(function ($q) {
                $q->where('tipo_post', 'video')
                    ->orWhereNull('tipo_post')
                    ->orWhere('tipo_post', '');
            })
            ->orderBy('id_post');

        $query->chunkById(200, function ($rows) use (
            $viewsCol,
            $dry,
            $additive,
            $limit,
            &$updated,
            &$skippedNoVideo,
            &$processed
        ) {
            foreach ($rows as $row) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }
                $processed++;

                $legacyId = (int) $row->id_post;
                $legacyViews = VideoseggPostViews::viewsFromRow($row, $viewsCol);

                $video = Video::query()->where('videosegg_post_id', $legacyId)->first();
                if (!$video) {
                    $video = Video::query()
                        ->whereRaw('slug REGEXP ?', ['-vsg' . $legacyId . '$'])
                        ->first();
                }

                if (!$video) {
                    $skippedNoVideo++;

                    continue;
                }

                $target = $additive
                    ? ((int) $video->views_count + $legacyViews)
                    : $legacyViews;

                if ($dry) {
                    $this->line("id_post {$legacyId} → video #{$video->id}: {$video->views_count} → {$target} vistas");
                    $updated++;

                    continue;
                }

                $video->views_count = $target;
                if ($video->videosegg_post_id === null && Schema::hasColumn('videos', 'videosegg_post_id')) {
                    $video->videosegg_post_id = $legacyId;
                }
                $video->save();
                $updated++;
            }
        }, 'id_post');

        $this->line('');
        if ($dry) {
            $this->info("Simulación: {$updated} filas con video en EDA · Sin match: {$skippedNoVideo}");
        } else {
            $this->info("Actualizados: {$updated} · Posts sin video local: {$skippedNoVideo}");
        }
        if ($additive) {
            $this->comment('Modo --additive: vistas finales = vistas actuales + legado (no reemplaza).');
        }

        return 0;
    }
}
