<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VideoDailyView extends Model
{
    protected $table = 'video_daily_views';

    protected $fillable = [
        'video_id',
        'stat_date',
        'views',
    ];

    protected $casts = [
        'stat_date' => 'date',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
