<?php

namespace App\Services;

use App\Models\Ref\Commune;
use App\Models\Ref\Departement;
use Illuminate\Support\Facades\Cache;

/**
 * Retrouve un département ou une commune cités dans un texte libre (SMS, WhatsApp, transcription).
 * Ex. « FEGGU MBOUR femme battue » → département de Mbour.
 */
class Localisateur
{
    /** Variantes d'écriture courantes. */
    private const ALIAS = [
        'tamba' => 'tambacounda',
        'st louis' => 'saint louis',
        'ziguinchor' => 'ziguinchor',
        'zig' => 'ziguinchor',
        'kaolak' => 'kaolack',
        'thiaroye' => 'pikine',
        'keur massar' => 'keur massar',
        'mbacke' => 'mbacke',
        'touba' => 'mbacke',
        // Noms wolof des villes
        'mbuur' => 'mbour',
        'ndakaaru' => 'dakar',
        'tiis' => 'thies',
        'ndar' => 'saint louis',
        'kawlax' => 'kaolack',
        'jurbel' => 'diourbel',
    ];

    /** @return array{region_id:int|null, departement_id:int|null, commune_id:int|null} */
    public function trouver(?string $texte): array
    {
        $t = ' '.Texte::normaliser($texte).' ';
        foreach (self::ALIAS as $alias => $vrai) {
            $t = str_replace(' '.$alias.' ', ' '.$vrai.' ', $t);
        }

        // Les communes d'abord (plus précises), en ignorant celles qui portent le nom d'un département.
        foreach ($this->index('communes') as [$nom, $id, $parent]) {
            if (str_contains($t, ' '.$nom.' ')) {
                $dep = Departement::find($parent);

                return ['region_id' => $dep?->region_id, 'departement_id' => $parent, 'commune_id' => $id];
            }
        }
        foreach ($this->index('departements') as [$nom, $id, $parent]) {
            if (str_contains($t, ' '.$nom.' ')) {
                return ['region_id' => $parent, 'departement_id' => $id, 'commune_id' => null];
            }
        }

        return ['region_id' => null, 'departement_id' => null, 'commune_id' => null];
    }

    private function index(string $quoi): array
    {
        return Cache::remember("localisateur.$quoi", 3600, function () use ($quoi) {
            $lignes = $quoi === 'communes'
                ? Commune::query()->get(['id', 'nom', 'departement_id'])->map(fn ($c) => [Texte::normaliser($c->nom), $c->id, $c->departement_id])
                : Departement::query()->get(['id', 'nom', 'region_id'])->map(fn ($d) => [Texte::normaliser($d->nom), $d->id, $d->region_id]);

            $deps = Departement::query()->pluck('nom')->map(fn ($n) => Texte::normaliser($n))->all();

            return $lignes
                ->filter(fn ($l) => strlen($l[0]) >= 4 && ($quoi === 'departements' || ! in_array($l[0], $deps, true)))
                ->sortByDesc(fn ($l) => strlen($l[0])) // « Saly Portudal » avant « Saly »
                ->values()->all();
        });
    }
}
