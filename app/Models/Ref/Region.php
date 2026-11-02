<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = [];

    public function departements()
    {
        return $this->hasMany(Departement::class);
    }
}
