<?php

namespace App\Services\Canaux;

use App\Services\Ia\ClientIa;

/**
 * Compréhension des messages libres par l'IA locale (français, wolof, pulaar, sérère, anglais).
 *
 * L'IA ne fait qu'une chose : lire le message et en extraire des informations (intention, danger, type de violence,
 * lieu, âge...). Les réponses envoyées à la personne sont des textes validés (français et wolof relus),
 * choisis par le moteur de conversation. Raisons :
 *  - un petit modèle local écrit mal le wolof et peut inventer (numéros, promesses) : une victime doit recevoir
 *    des réponses sûres et toujours identiques ;
 *  - l'extraction est la tâche où un petit modèle est le plus fiable, et la plus rapide (un seul message à lire).
 * Avec un modèle plus grand, FEGGU_ASSISTANT_REDACTION=true ajoute une phrase d'écoute rédigée par l'IA.
 */
class AssistantIa
{
    private const CATEGORIES = [1, 2, 3, 4, 5, 6, 7, 8];

    public function __construct(private ClientIa $ia) {}

    /** L'assistante répond-elle sur ce canal ? (jamais en USSD : un écran USSD n'attend pas une IA.) */
    public function disponiblePour(string $canal): bool
    {
        return config('feggu.assistant.actif')
            && $this->ia->actif()
            && $canal !== 'ussd'
            && in_array($canal, config('feggu.assistant.canaux', []), true);
    }

    /**
     * @return array{langue:?string, intention:string, sujet:?string, danger_immediat:?bool, declarant:?string,
     *               categorie_id:?int, lieu:?string, age:?int, ecoute:?string}|null
     */
    public function comprendre(string $message, ?string $questionPosee = null): ?array
    {
        $timeout = (int) config('feggu.assistant.timeout', 45);
        @set_time_limit($timeout + 30);

        $r = $this->ia->discuter([
            ['role' => 'system', 'content' => $this->consignes($questionPosee)],
            ['role' => 'user', 'content' => mb_substr($message, 0, 1500)],
        ], $timeout);
        if (! is_array($r)) {
            return null;
        }

        $bool = fn ($v) => $v === null || $v === '' ? null : filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $lieu = is_string($r['lieu'] ?? null) ? trim($r['lieu']) : null;
        $ecoute = is_string($r['ecoute'] ?? null) ? trim($r['ecoute']) : null;

        return [
            'langue' => in_array($r['langue'] ?? null, ['fr', 'wo', 'ff', 'sr', 'en'], true) ? $r['langue'] : null,
            'intention' => in_array($r['intention'] ?? null, ['signaler', 'information', 'salutation', 'fin', 'autre'], true) ? $r['intention'] : 'autre',
            'sujet' => in_array($r['sujet'] ?? null, ['droits', 'urgences', 'fonctionnement'], true) ? $r['sujet'] : null,
            'danger_immediat' => $bool($r['danger_immediat'] ?? null),
            'declarant' => in_array($r['declarant'] ?? null, ['victime', 'temoin'], true) ? $r['declarant'] : null,
            'categorie_id' => is_numeric($r['categorie_id'] ?? null) && in_array((int) $r['categorie_id'], self::CATEGORIES, true) ? (int) $r['categorie_id'] : null,
            // Un lieu « non précisé » n'en est pas un.
            'lieu' => $lieu && ! preg_match('/non|inconnu|pr[ée]cis|sp[ée]cifi|null/i', $lieu) ? mb_substr($lieu, 0, 80) : null,
            'age' => is_numeric($r['age_victime'] ?? null) && $r['age_victime'] > 0 && $r['age_victime'] < 110 ? (int) $r['age_victime'] : null,
            'ecoute' => config('feggu.assistant.redaction') && $ecoute ? $this->nettoyer($ecoute) : null,
        ];
    }

    /** Phrase rédigée par l'IA (mode rédaction) : une seule phrase, sans chiffres ni promesses chiffrées. */
    private function nettoyer(string $texte): ?string
    {
        $texte = preg_split('/(?<=[.!?])\s/u', $texte)[0] ?? '';
        if (preg_match('/\d/', $texte) || mb_strlen($texte) > 200) {
            return null;
        }

        return $texte;
    }

    private function consignes(?string $questionPosee): string
    {
        $redaction = config('feggu.assistant.redaction')
            ? "\n \"ecoute\": une phrase courte et bienveillante dans la langue du message, qui montre que tu as compris (sans conseil, sans promesse, sans numéro),"
            : '';
        $contexte = $questionPosee ? "\nLe message répond à cette question posée juste avant : « $questionPosee »." : '';

        return <<<TXT
Tu analyses un message reçu par FEGGU, service sénégalais de signalement des violences basées sur le genre. Le message peut être en wolof, français, pulaar, sérère ou anglais.$contexte
Réponds UNIQUEMENT en JSON avec ces clés (null si l'information n'est pas dans le message, ne devine pas) :
{"langue": "wo|fr|ff|sr|en",
 "intention": "signaler" (décrit une violence) | "information" (pose une question) | "salutation" | "fin" (merci, au revoir) | "autre",
 "danger_immediat": true si danger maintenant (violence en cours, auteur présent, arme, menace de mort), false si la personne dit ne pas être en danger, sinon null,
 "declarant": "victime" si cela arrive à la personne qui écrit, "temoin" si cela arrive à quelqu'un d'autre (voisine, fille, sœur, amie...),
 "categorie_id": 1 violence sexuelle, 2 coups et blessures, 3 menaces ou insultes, 4 abandon ou refus de dépense, 5 maltraitance d'enfant, 6 excision, 7 mariage forcé ou d'enfant, 8 exploitation sexuelle,
 "lieu": "ville, commune ou quartier cité dans le message",
 "age_victime": nombre,$redaction
 "sujet": "droits|urgences|fonctionnement" si c'est une question}
TXT;
    }
}
