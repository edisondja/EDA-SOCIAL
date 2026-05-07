<?php

namespace App\Console\Commands;

use App\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class StripVsgSuffixFromVideoSlugs extends Command
{
    protected $signature = 'videos:strip-vsg-slugs {--dry-run : Solo listar cambios sin guardar}';

    protected $description = 'Quita el sufijo -vsg{N} de los slugs en BD y resuelve colisiones (único por fila)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = 0;

        foreach (Video::query()->orderBy('id')->cursor() as $video) {
            $raw = trim((string) $video->slug);
            if ($raw === '' || !preg_match('/-vsg\d+$/', $raw)) {
                continue;
            }

            $base = Video::stripLegacyVsgSlugSuffix($raw);
            if ($base === '') {
                $base = Str::slug(Str::limit($video->title, 100, '')) ?: 'video';
            }

            $candidate = Str::limit($base, 220, '');
            if (Video::query()->where('slug', $candidate)->where('id', '!=', $video->id)->exists()) {
                $suffix = '-' . $video->id;
                $candidate = Str::limit($base, 220 - strlen($suffix), '') . $suffix;
            }

            $safety = 0;
            while (Video::query()->where('slug', $candidate)->where('id', '!=', $video->id)->exists() && $safety < 20) {
                $safety++;
                $candidate = Str::limit($base, 200, '') . '-' . $video->id . '-' . Str::lower(Str::random(4));
            }

            if ($candidate === $raw) {
                continue;
            }

            $this->line("#{$video->id}: {$raw} → {$candidate}");
            $changed++;
            if (!$dry) {
                $video->slug = $candidate;
                $video->save();
            }
        }

        $this->info($dry ? "Simulación: {$changed} slug(s) a actualizar." : "Actualizados {$changed} slug(s).");

        return 0;
    }
}
