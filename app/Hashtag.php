<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Hashtag extends Model
{
    protected $fillable = [
        'name',
    ];

    public function videos()
    {
        return $this->belongsToMany(Video::class)->withTimestamps();
    }
}
