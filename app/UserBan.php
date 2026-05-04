<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserBan extends Model
{
    protected $fillable = [
        'user_id',
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
