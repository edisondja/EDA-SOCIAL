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

        return $roots->sortByDesc('created_at')->values();
    }
}
