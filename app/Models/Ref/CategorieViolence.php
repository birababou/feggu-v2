<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

class CategorieViolence extends Model
{
    protected $table = 'categories_violence';
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = [];

    public function types()
    {
        return $this->hasMany(TypeViolence::class, 'categorie_id');
    }
}
