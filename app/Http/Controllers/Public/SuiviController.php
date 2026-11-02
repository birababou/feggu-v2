<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use App\Services\Contenu\Textes;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuiviController extends Controller
{
    public function create()
    {
        return Inertia::render('Public/Suivi', ['resultat' => null]);
    }

    /** Recherche en POST : le code ne s'inscrit pas dans l'historique. Seul l'avancement est montré, jamais le contenu. */
    public function show(Request $request)
    {
        $request->validate(['reference' => 'required|string|max:20']);
        $s = Signalement::where('reference', strtoupper(trim($request->input('reference'))))->first();
        $langue = session('langue', 'fr');

        return Inertia::render('Public/Suivi', [
            'resultat' => $s ? [
                'reference' => $s->reference,
                'statut' => $s->statut,
                'statut_libelle' => Textes::statut($s->statut, $langue),
                'etapes' => [
                    ['cle' => 'nouveau', 'date' => $s->created_at?->toDateString()],
                    ['cle' => 'oriente', 'date' => $s->oriente_le?->toDateString()],
                    ['cle' => 'en_cours', 'date' => $s->premier_contact_le?->toDateString()],
                    ['cle' => 'pris_en_charge', 'date' => $s->pris_en_charge_le?->toDateString()],
                    ['cle' => 'clos', 'date' => $s->clos_le?->toDateString()],
                ],
            ] : false,
        ]);
    }
}
