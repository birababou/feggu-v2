<?php

namespace App\Services;

use App\Models\Signalement;
use App\Models\Structure;
use Illuminate\Support\Collection;

/**
 * Propose les structures les plus adaptées à un signalement : proximité (département puis région)
 * et correspondance entre les besoins exprimés et les services de la structure.
 */
class Orientation
{
    /** Domaines déduits de la catégorie quand la victime n'a pas exprimé de besoin. */
    private const DOMAINES_PAR_CATEGORIE = [
        1 => ['medical', 'psychologique', 'juridique'],
        2 => ['medical', 'juridique', 'securite'],
        3 => ['psychologique', 'mediation'],
        4 => ['mediation', 'juridique', 'economique'],
        5 => ['protection_enfant'],
        6 => ['medical', 'protection_enfant'],
        7 => ['protection_enfant', 'juridique'],
        8 => ['protection_enfant', 'medical', 'psychologique'],
    ];

    /** Correspondance besoins exprimés → domaines de service. */
    private const BESOIN_VERS_DOMAINE = [
        'hebergement' => 'hebergement', 'juridique' => 'juridique', 'psychologique' => 'psychologique',
        'medical' => 'medical', 'retour_famille' => 'mediation', 'mediation' => 'mediation',
        'reinsertion_scolaire' => 'protection_enfant', 'securite' => 'securite', 'economique' => 'economique',
        'retour_pays' => 'juridique',
    ];

    public function domainesRecherches(Signalement $s): array
    {
        $domaines = collect($s->besoins ?? [])->map(fn ($b) => self::BESOIN_VERS_DOMAINE[$b] ?? null)->filter();
        if ($domaines->isEmpty()) {
            $domaines = collect(self::DOMAINES_PAR_CATEGORIE[$s->categorie_id] ?? ['mediation']);
        }
        if (in_array($s->victime_tranche_age, ['0-11', '12-17'], true)) {
            $domaines->prepend('protection_enfant');
        }

        return $domaines->unique()->values()->all();
    }

    /** @return Collection<int, array{structure: Structure, score:int, services_couverts: array}> */
    public function suggerer(Signalement $s, int $limite = 3): Collection
    {
        $domaines = $this->domainesRecherches($s);

        return Structure::query()
            ->with('organisation', 'departement')
            ->where('actif', true)
            ->when($s->departement_id || $s->region_id, function ($q) use ($s) {
                $q->whereIn('departement_id', fn ($sq) => $sq->select('id')->from('departements')
                    ->when($s->region_id, fn ($w) => $w->where('region_id', $s->region_id), fn ($w) => $w->where('id', $s->departement_id)));
            })
            ->get()
            ->map(function (Structure $st) use ($s, $domaines) {
                $couverts = array_values(array_intersect($domaines, $st->servicesEffectifs()));
                $score = count($couverts) * 10
                    + ($st->departement_id === $s->departement_id ? 25 : 0)
                    + (in_array($domaines[0] ?? null, $couverts, true) ? 5 : 0)
                    + ($st->users()->where('actif', true)->exists() ? 5 : -20); // une structure sans agent actif ne peut pas répondre

                return ['structure' => $st, 'score' => $score, 'services_couverts' => $couverts];
            })
            ->filter(fn ($x) => $x['services_couverts'] !== [])
            ->sortByDesc('score')
            ->take($limite)
            ->values();
    }

    /** Oriente automatiquement vers la meilleure structure, si l'une d'elles est pertinente. */
    public function orienterAutomatiquement(Signalement $s): ?Structure
    {
        $meilleure = $this->suggerer($s, 1)->first();
        if (! $meilleure || $meilleure['score'] < 20) {
            return null;
        }

        $s->update(['structure_id' => $meilleure['structure']->id, 'statut' => 'oriente', 'oriente_le' => now()]);
        $s->suivis()->create([
            'type' => 'orientation',
            'contenu' => 'Orientation automatique vers '.$meilleure['structure']->nom,
            'meta' => ['structure_id' => $meilleure['structure']->id, 'services' => $meilleure['services_couverts'], 'auto' => true],
            'visible_declarant' => true,
        ]);

        return $meilleure['structure'];
    }
}
