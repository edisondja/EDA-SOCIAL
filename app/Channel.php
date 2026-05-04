<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'display_name',
        'bio',
        'avatar_url',
        'cover_url',
        'subscribers_count',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }
}
