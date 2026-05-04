<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VideoBan extends Model
{
    protected $fillable = [
        'video_id',
        'moderator_id',
        'reason',
        'notes',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];
}
