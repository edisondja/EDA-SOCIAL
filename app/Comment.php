<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Comment extends Model
{
    protected $fillable = [
        'video_id',
        'user_id',
        'parent_id',
        'body',
        'points',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    /**
     * Valida que un comentario padre exista en el mismo video y no supere la profundidad de hilos.
     *
     * @return string|null Mensaje de error en español, o null si es válido.
     */
    public static function replyParentError(Video $video, ?int $parentId): ?string
    {
        if (!$parentId) {
            return null;
        }

        $parent = static::query()->where('video_id', $video->id)->whereKey($parentId)->first();
        if (!$parent) {
            return 'El comentario padre no pertenece a esta publicación.';
        }

        $depth = 0;
        $walker = $parent;
        while ($walker && $walker->parent_id) {
            $depth += 1;
            if ($depth > 24) {
                return 'Demasiados niveles de respuesta en este hilo.';
            }
            $walker = $walker->parent()->first();
        }

        return null;
    }

    /**
     * @param Collection|array<int, Comment> $flat
     * @return Collection<int, Comment> raíces con relación replies anidada (solo en memoria, para JSON/vistas)
     */
    public static function nestForDisplay($flat): Collection
    {
        $list = $flat instanceof Collection ? $flat : collect($flat);
        $byId = $list->keyBy('id');
        foreach ($byId as $c) {
            $c->setRelation('replies', collect());
        }
        $roots = collect();
        foreach ($list->sortBy('created_at') as $c) {
            $pid = $c->parent_id;
            if ($pid && $byId->has($pid)) {
                $byId[$pid]->replies->push($c);
            } else {
                $roots->push($c);
            }
        }

        $sortAndRank = function (Collection $nodes) use (&$sortAndRank): Collection {
            $sorted = $nodes->sort(function (Comment $a, Comment $b) {
                $pa = (int) $a->points;
                $pb = (int) $b->points;
                if ($pa !== $pb) {
                    return $pb <=> $pa;
                }
                $ta = $a->created_at ? $a->created_at->getTimestamp() : 0;
                $tb = $b->created_at ? $b->created_at->getTimestamp() : 0;
                if ($ta !== $tb) {
                    return $tb <=> $ta;
                }

                return ($b->id ?? 0) <=> ($a->id ?? 0);
            })->values();

            $rank = 1;
            foreach ($sorted as $c) {
                $c->setAttribute('vote_rank', $rank++);
                $replies = $c->relationLoaded('replies') ? $c->replies : collect();
                if ($replies->isNotEmpty()) {
                    $c->setRelation('replies', $sortAndRank($replies));
                }
            }

            return $sorted;
        };

        return $sortAndRank($roots);
    }
}
