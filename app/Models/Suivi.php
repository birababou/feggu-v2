<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suivi extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'contenu' => 'encrypted',
        'meta' => 'array',
        'visible_declarant' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signalement()
    {
        return $this->belongsTo(Signalement::class);
    }
}
