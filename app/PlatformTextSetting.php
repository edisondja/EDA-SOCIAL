<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlatformTextSetting extends Model
{
    protected $fillable = [
        'key',
        'body',
    ];
}
