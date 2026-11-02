<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = [];

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }
}
