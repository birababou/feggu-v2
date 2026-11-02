<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ref\CategorieViolence;
use App\Models\Ref\Region;
use App\Models\Signalement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class TableauDeBordController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $base = fn () => Signalement::query()->visiblesPar($user)->when(! config('feggu.demo'), fn ($q) => $q->where('est_demo', false));

        $total = $base()->count();
        $ouverts = $base()->where('statut', '!=', 'clos');
        $orientes = $base()->whereNotNull('oriente_le')->count();
        $priseEnCharge = $base()->whereNotNull('pris_en_charge_le')->count();

        // Délai médian entre le signalement et le premier contact (en heures).
        $delais = $base()->whereNotNull('premier_contact_le')->get(['created_at', 'premier_contact_le'])
            ->map(fn ($s) => $s->created_at->diffInMinutes($s->premier_contact_le) / 60)->sort()->values();
        $delaiMedian = $delais->isEmpty() ? null : round($delais[intdiv($delais->count(), 2)], 1);

        $parMois = collect(range(11, 0))->map(function ($i) use ($base) {
            $debut = Carbon::now()->startOfMonth()->subMonths($i);

            return [
                'mois' => $debut->translatedFormat('M y'),
                'total' => $base()->whereBetween('created_at', [$debut, $debut->copy()->endOfMonth()])->count(),
            ];
        });

        $categories = CategorieViolence::pluck('nom', 'id');
        $regions = Region::pluck('nom', 'id');

        return Inertia::render('Admin/TableauDeBord', [
            'kpi' => [
                'total' => $total,
                'trente_jours' => $base()->where('created_at', '>=', now()->subDays(30))->count(),
                'critiques_ouverts' => (clone $ouverts)->where('urgence', 'critique')->count(),
                'non_orientes' => $base()->whereNull('structure_id')->where('statut', '!=', 'clos')->count(),
                'taux_prise_en_charge' => $orientes ? round(100 * $priseEnCharge / $orientes) : null,
                'delai_median_heures' => $delaiMedian,
            ],
            'ia' => IaController::etat(),
            'peut_tester_ia' => in_array($user->role, ['admin', 'superviseur'], true),
            'par_mois' => $parMois,
            'par_categorie' => $base()->selectRaw('categorie_id, count(*) as n')->groupBy('categorie_id')->orderByDesc('n')->get()
                ->map(fn ($r) => ['libelle' => $categories[$r->categorie_id] ?? 'Non précisé', 'n' => $r->n]),
            'par_canal' => $base()->selectRaw('canal, count(*) as n')->groupBy('canal')->orderByDesc('n')->get()
                ->map(fn ($r) => ['libelle' => Signalement::CANAUX[$r->canal] ?? $r->canal, 'n' => $r->n]),
            'par_region' => $base()->selectRaw('region_id, count(*) as n')->groupBy('region_id')->orderByDesc('n')->get()
                ->map(fn ($r) => ['libelle' => $regions[$r->region_id] ?? 'Non localisé', 'n' => $r->n]),
            'a_traiter' => $base()->where('statut', '!=', 'clos')->whereIn('urgence', ['critique', 'haute'])
                ->with('departement:id,nom', 'categorie:id,nom')->parPriorite()->limit(6)->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'reference' => $s->reference, 'urgence' => $s->urgence, 'statut' => $s->libelleStatut(),
                    'categorie' => $s->categorie?->nom, 'departement' => $s->departement?->nom,
                    'depuis' => $s->created_at->diffForHumans(), 'canal' => Signalement::CANAUX[$s->canal] ?? $s->canal,
                ]),
        ]);
    }
}
