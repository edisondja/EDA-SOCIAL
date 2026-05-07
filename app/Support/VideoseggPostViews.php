<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class VideoseggPostViews
{
    /** Nombres habituales de columna de vistas en `posts` (MySQL videoseggs). */
    public const CANDIDATE_COLUMNS = [
        'visitas',
        'vistas',
        'views',
        'num_visitas',
        'visualizaciones',
        'contador_visitas',
        'hits',
        'total_visitas',
        'view_count',
        'num_views',
    ];

    public static function resolveViewsColumn(string $connection = 'videosegg'): ?string
    {
        if (!Schema::connection($connection)->hasTable('posts')) {
            return null;
        }
        $cols = Schema::connection($connection)->getColumnListing('posts');
        foreach (self::CANDIDATE_COLUMNS as $name) {
            if (in_array($name, $cols, true)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  object  $row  Fila de la tabla posts (legado).
     */
    public static function viewsFromRow(object $row, ?string $column): int
    {
        if ($column === null || $column === '' || !property_exists($row, $column)) {
            return 0;
        }

        return max(0, (int) $row->{$column});
    }
}
