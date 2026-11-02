<?php

namespace App\Services;

use App\Models\Ref\TypeViolence;
use App\Services\Ia\ClientIa;

/**
 * Tri automatique de l'urgence d'un signalement.
 *
 * Les règles fonctionnent toujours (sans internet, sans IA). Si un modèle local est configuré,
 * il complète l'analyse du récit libre ; il ne peut jamais faire baisser une urgence décidée par les règles.
 */
class Triage
{
    /** Indices de danger immédiat dans un récit (français et wolof). À enrichir avec GESTES. */
    private const INDICES_DANGER = [
        'maintenant' => 'Les faits se déroulent maintenant',
        'en ce moment' => 'Les faits se déroulent maintenant',
        'leegi' => 'Les faits se déroulent maintenant (wolof)',
        'il est la' => 'L\'auteur est présent',
        'mi ngi fi' => 'L\'auteur est présent (wolof)',
        'couteau' => 'Arme mentionnée',
        'arme' => 'Arme mentionnée',
        'fusil' => 'Arme mentionnée',
        'paaka' => 'Arme mentionnée (wolof)',
        'tuer' => 'Menace de mort',
        'rey' => 'Menace de mort (wolof)',
        'sang' => 'Blessure visible',
        'deret' => 'Blessure visible (wolof)',
        'enferme' => 'Séquestration',
        'enfermee' => 'Séquestration',
        'enceinte' => 'Victime enceinte',
        'ebb' => 'Victime enceinte (wolof)',
        'aidez moi' => 'Appel à l\'aide',
        'au secours' => 'Appel à l\'aide',
        'dimbali leen ma' => 'Appel à l\'aide (wolof)',
    ];

    /** Mots qui indiquent la catégorie quand rien n'a été choisi (SMS libre). */
    private const MOTS_CATEGORIES = [
        1 => ['viol', 'violee', 'violer', 'attouchement', 'harcelement', 'inceste', 'tentative de viol', 'jëmm'],
        2 => ['battue', 'battu', 'frappe', 'frappee', 'coups', 'blessure', 'gifle', 'brulure', 'door', 'dóor', 'dafa ma door'],
        3 => ['menace', 'insulte', 'injure', 'chantage', 'humilie'],
        4 => ['abandon', 'depense', 'pension', 'nourriture'],
        5 => ['talibe', 'mendicite', 'enfant travaille', 'traite', 'vendu'],
        6 => ['excision', 'excisee', 'mgf', 'mutilation', 'xaraf'],
        7 => ['mariage', 'mariee de force', 'marier', 'sey', 'séy', 'seyal', 'seyal ci doole'],
        8 => ['prostitution', 'exploitation'],
    ];

    public function __construct(private ClientIa $ia) {}

    /**
     * @param  array{types?:array,categorie_id?:int|null,danger_immediat?:bool,victime_tranche_age?:string|null,periode_faits?:string|null,recit?:string|null,besoins?:array}  $d
     * @return array{urgence:string,score:int,raisons:array<int,string>,categorie_id:int|null}
     */
    public function evaluer(array $d): array
    {
        $score = 10;
        $raisons = [];
        $texte = Texte::normaliser($d['recit'] ?? '');

        if (! empty($d['danger_immediat'])) {
            $score += 60;
            $raisons[] = 'Danger immédiat signalé';
        }

        if (($d['periode_faits'] ?? null) === 'en_cours') {
            $score += 25;
            $raisons[] = 'Violence en cours';
        } elseif (($d['periode_faits'] ?? null) === 'aujourdhui') {
            $score += 10;
            $raisons[] = 'Faits survenus aujourd\'hui';
        }

        if (in_array($d['victime_tranche_age'] ?? null, ['0-11', '12-17'], true)) {
            $score += 20;
            $raisons[] = 'Victime mineure';
        }

        $types = TypeViolence::whereIn('id', $d['types'] ?? [])->get();
        $graviteMax = (int) $types->max('gravite');
        if ($graviteMax >= 3) {
            $score += 30;
            $raisons[] = 'Violence grave : '.$types->where('gravite', 3)->pluck('nom')->first();
        } elseif ($graviteMax === 2) {
            $score += 15;
        }

        $categorie = $d['categorie_id'] ?? $types->first()?->categorie_id ?? $this->devinerCategorie($texte);
        if (in_array($categorie, [1, 6, 8], true) && $graviteMax < 3) {
            $score += 20;
            $raisons[] = 'Violence sexuelle ou MGF';
        } elseif (in_array($categorie, [2, 5, 7], true) && $graviteMax < 2) {
            $score += 15;
            $raisons[] = $categorie === 2 ? 'Violence physique' : 'Enfant exposé (maltraitance ou mariage)';
        }

        foreach (self::INDICES_DANGER as $mot => $raison) {
            if ($texte !== '' && Texte::contientMot($texte, $mot) && ! in_array($raison, $raisons, true)) {
                $score += 15;
                $raisons[] = $raison;
            }
        }

        foreach (config('feggu.mots_sos', []) as $mot) {
            if ($texte !== '' && Texte::contientMot($texte, $mot)) {
                $score += 60;
                $raisons[] = 'Mot-code d\'urgence « '.strtoupper($mot).' »';
                break;
            }
        }

        if (in_array('hebergement', $d['besoins'] ?? [], true) || in_array('securite', $d['besoins'] ?? [], true)) {
            $score += 10;
            $raisons[] = 'Besoin de mise en sécurité ou d\'hébergement';
        }

        $score = min($score, 100);
        $urgence = match (true) {
            $score >= 70 => 'critique',
            $score >= 45 => 'haute',
            $score >= 25 => 'moyenne',
            default => 'basse',
        };

        return ['urgence' => $urgence, 'score' => $score, 'raisons' => array_values(array_unique($raisons)), 'categorie_id' => $categorie];
    }

    /**
     * Avis du modèle d'IA local sur un récit libre (français, wolof ou mélangé).
     * Appelé en tâche de fond, après la réponse à la victime : jamais pendant un écran USSD ou un SMS.
     *
     * @return array{danger_immediat:bool, categorie_id:int|null, raison:string}|null
     */
    public function avisIa(string $recit): ?array
    {
        if (trim($recit) === '' || ! $this->ia->actif()) {
            return null;
        }

        $avis = $this->ia->json(
            'Tu aides des travailleuses sociales à trier des signalements de violences basées sur le genre au Sénégal. '
            .'Le texte peut être en wolof, en français ou mélangé. Réponds uniquement en JSON : '
            .'{"danger_immediat": true|false, "categorie_id": 1 à 8 ou null, "raison": "une phrase courte en français"}. '
            .'danger_immediat = la personne est en danger maintenant (violence en cours, auteur présent, arme, menace de mort, séquestration, blessure grave). '
            .'Catégories : 1 violence sexuelle, 2 violence physique, 3 violence psychologique, 4 abandon familial ou refus de dépense, '
            .'5 maltraitance d\'enfant ou traite, 6 excision (MGF), 7 mariage d\'enfant ou forcé, 8 exploitation sexuelle.',
            $recit
        );
        if (! is_array($avis)) {
            return null;
        }

        $categorie = isset($avis['categorie_id']) && is_numeric($avis['categorie_id']) ? (int) $avis['categorie_id'] : null;

        return [
            'danger_immediat' => filter_var($avis['danger_immediat'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'categorie_id' => $categorie >= 1 && $categorie <= 8 ? $categorie : null,
            'raison' => mb_substr((string) ($avis['raison'] ?? ''), 0, 200),
        ];
    }

    /** Indices de danger immédiat présents dans un texte normalisé (règles, sans IA). */
    public function indicesDanger(string $texteNormalise): array
    {
        return array_values(array_unique(array_filter(self::INDICES_DANGER, fn ($raison, $mot) => $texteNormalise !== '' && Texte::contientMot($texteNormalise, $mot), ARRAY_FILTER_USE_BOTH)));
    }

    public function devinerCategorie(string $texteNormalise): ?int
    {
        if ($texteNormalise === '') {
            return null;
        }
        foreach (self::MOTS_CATEGORIES as $categorie => $mots) {
            foreach ($mots as $mot) {
                if (Texte::contientMot($texteNormalise, $mot)) {
                    return $categorie;
                }
            }
        }

        return null;
    }
}
