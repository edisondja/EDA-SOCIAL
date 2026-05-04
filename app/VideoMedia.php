<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VideoMedia extends Model
{
    protected $table = 'video_media';

    protected $fillable = [
        'video_id',
        'type',
        'url',
        'position',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
