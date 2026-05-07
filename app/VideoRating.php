<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VideoRating extends Model
{
    protected $fillable = [
        'video_id',
        'user_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
