<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    protected $guarded = [];

    protected $casts = ['services' => 'array'];

    public function structures()
    {
        return $this->hasMany(Structure::class);
    }
}
