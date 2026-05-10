<?php

namespace App;

use App\Support\MediaSrc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Video extends Model
{
    protected $fillable = [
        'channel_id',
        'author_id',
        'title',
        'slug',
        'description',
        'video_url',
        'preview_url',
        'thumbnail_url',
        'duration_seconds',
        'views_count',
        'likes_count',
        'dislikes_count',
        'is_published',
        'published_at',
        'moderation_status',
        'videosegg_post_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $hidden = [
        'videosegg_post_id',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function media()
    {
        return $this->hasMany(VideoMedia::class)->orderBy('position');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function reports()
    {
        return $this->hasMany(VideoReport::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class)->withTimestamps();
    }

    public function dailyViews()
    {
        return $this->hasMany(VideoDailyView::class);
    }

    public function ratings()
    {
        return $this->hasMany(VideoRating::class);
    }

    public function ratingStoreUrl(): string
    {
        return route('posts.rating.store', ['video' => $this->id, 'slug' => $this->playSlug()]);
    }

    /**
     * Quita el sufijo legacy de import Videosegg (-vsg + número) para URLs públicas.
     */
    public static function stripLegacyVsgSlugSuffix(?string $slug): string
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return '';
        }

        $stripped = preg_replace('/-vsg\d+$/', '', $slug);

        return ($stripped !== null && $stripped !== '') ? $stripped : $slug;
    }

    /**
     * Segmento de URL tras el ID (/playvideo/{id}/{slug}), sin dominio (slug legible).
     */
    public function playPath(): string
    {
        return '/playvideo/' . $this->id . '/' . $this->playSlug();
    }

    /**
     * Segmento de URL tras el ID (/playvideo/{id}/{slug}).
     */
    public function playSlug(): string
    {
        $s = trim((string) $this->slug);
        if ($s !== '') {
            $clean = self::stripLegacyVsgSlugSuffix($s);

            return $clean !== '' ? $clean : $s;
        }

        $fromTitle = Str::slug(Str::limit((string) $this->title, 100, ''));

        return $fromTitle !== '' ? $fromTitle : ('video-' . $this->id);
    }

    public function playUrl(): string
    {
        return route('posts.show', ['video' => $this->id, 'slug' => $this->playSlug()]);
    }

    /**
     * Ruta relativa canónica (mismo patrón que legado tipo /playvideo/{id}/{slug}).
     * Expuesto en JSON API como atributo `play_path` cuando se hace append.
     */
    public function getPlayPathAttribute(): string
    {
        return $this->playPath();
    }

    /**
     * URL absoluta de la ficha pública (integraciones / apps: no usar solo /api/videos/{id} como enlace).
     */
    public function getPlayUrlAttribute(): string
    {
        return $this->playUrl();
    }

    public function commentsStoreUrl(): string
    {
        return route('posts.comments.store', ['video' => $this->id, 'slug' => $this->playSlug()]);
    }

    /**
     * @param  string  $slug  Valor capturado de la ruta (puede venir URL-encoded).
     */
    public function slugMatchesPlayRoute(string $slug): bool
    {
        $given = rawurldecode($slug);
        $canonical = $this->playSlug();
        $stored = trim((string) $this->slug);

        if ($given === $canonical || $slug === $canonical) {
            return true;
        }

        if ($stored !== '' && ($given === $stored || $slug === $stored)) {
            return true;
        }

        $givenClean = self::stripLegacyVsgSlugSuffix($given);

        return $givenClean !== '' && $givenClean === $canonical;
    }

    /**
     * True si hace falta generar una portada JPG (sin miniatura o la URL apunta a un archivo de vídeo).
     */
    public function needsPosterImageGeneration(): bool
    {
        $t = trim((string) $this->thumbnail_url);

        return $t === '' || self::storedUrlLooksLikeVideo($t);
    }

    /**
     * True si conviene generar con ffmpeg portada y/o clip de hover para la tarjeta del feed.
     */
    public function needsGeneratedCardPreview(): bool
    {
        if ($this->needsPosterImageGeneration()) {
            return true;
        }

        return trim((string) $this->preview_url) === '';
    }

    /**
     * @internal Reutilizado por servicios de ffmpeg / consultas de cola.
     */
    public static function storedUrlLooksLikeVideo(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'mkv', 'ts', 'm3u8', 'ogv', 'avi'], true);
    }

    /**
     * URL absoluta de imagen para Open Graph / Twitter (miniatura JPG o primera imagen en medios).
     */
    public function openGraphImageAbsolute(): string
    {
        $thumb = trim((string) $this->thumbnail_url);
        if ($thumb !== '' && self::storedUrlLooksLikeVideo($thumb)) {
            $thumb = '';
        }
        if ($thumb === '') {
            $this->loadMissing('media');
            $firstImage = $this->media->sortBy('position')->first(function ($m) {
                return ($m->type ?? '') === 'image' && trim((string) ($m->url ?? '')) !== '';
            });
            $thumb = $firstImage ? trim((string) $firstImage->url) : '';
        }
        if ($thumb === '') {
            return '';
        }

        $web = MediaSrc::web($thumb);
        if ($web === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $web)) {
            return $web;
        }

        return url($web);
    }
}
