<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

class LienAuteur extends Model
{
    protected $table = 'liens_auteur';
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = [];
}
