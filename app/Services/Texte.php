<?php

namespace App\Services;

use Illuminate\Support\Str;

class Texte
{
    /** Minuscules, sans accents ni ponctuation : pour comparer des saisies SMS libres. */
    public static function normaliser(?string $texte): string
    {
        $t = Str::ascii(mb_strtolower((string) $texte));
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    public static function contientMot(string $texteNormalise, string $mot): bool
    {
        $mot = self::normaliser($mot);

        return $mot !== '' && preg_match('/(^| )'.preg_quote($mot, '/').'( |$)/', $texteNormalise) === 1;
    }
}
