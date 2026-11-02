<?php

namespace App\Services\Canaux;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi des SMS sortants. Les identifiants de l'agrégateur sont dans .env, jamais dans le code.
 * Les messages restent neutres : ils ne mentionnent jamais la violence (le téléphone peut être lu par l'auteur).
 */
class EnvoiSms
{
    public function envoyer(string $numero, string $texte): bool
    {
        $numero = self::formater($numero);
        if (! $numero) {
            return false;
        }

        if (config('feggu.sms.driver') !== 'lam') {
            Log::channel('single')->info('[SMS simulé] vers '.substr($numero, 0, 6).'*** : '.$texte);

            return true;
        }

        $lam = config('feggu.sms.lam');
        $reponse = Http::timeout(15)
            ->withBasicAuth((string) $lam['utilisateur'], (string) $lam['mot_de_passe'])
            ->acceptJson()
            ->post($lam['url'], ['from' => config('feggu.sms.expediteur'), 'to' => $numero, 'text' => $texte]);

        if ($reponse->failed()) {
            Log::error('Échec envoi SMS', ['statut' => $reponse->status()]);
        }

        return $reponse->successful();
    }

    /** Format international sénégalais : 77xxxxxxx → 22177xxxxxxx. */
    public static function formater(?string $numero): ?string
    {
        $n = preg_replace('/\D+/', '', (string) $numero);

        return match (true) {
            strlen($n) === 9 => '221'.$n,
            strlen($n) === 12 && str_starts_with($n, '221') => $n,
            strlen($n) >= 10 => $n,
            default => null,
        };
    }
}
