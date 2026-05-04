<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ModerationAction extends Model
{
    protected $fillable = [
        'moderator_id',
        'target_type',
        'target_id',
        'action',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
