<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['contenu' => 'encrypted', 'media_url' => 'encrypted'];
}
