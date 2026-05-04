<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
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
}
