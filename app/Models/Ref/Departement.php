<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = [];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function communes()
    {
        return $this->hasMany(Commune::class);
    }
}
