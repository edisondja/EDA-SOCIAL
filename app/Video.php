<?php

namespace App;

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
}
