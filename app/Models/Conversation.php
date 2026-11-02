<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'contact' => 'encrypted',
        'donnees' => 'array',
        'dernier_message_le' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function signalement()
    {
        return $this->belongsTo(Signalement::class);
    }

    /** Empreinte non réversible d'un numéro, pour retrouver une conversation sans stocker le numéro en clair. */
    public static function empreinte(string $contact): string
    {
        $normalise = preg_replace('/\D+/', '', $contact) ?: $contact;

        return hash_hmac('sha256', $normalise, config('app.key'));
    }
}
