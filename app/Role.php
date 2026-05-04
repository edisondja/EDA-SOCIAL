<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'description'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
