<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Ref\Besoin;
use App\Models\Ref\CategorieViolence;
use App\Models\Ref\Region;
use App\Services\Signalements;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SignalementController extends Controller
{
    public function create()
    {
        return Inertia::render('Public/Signaler', [
            'regions' => Region::orderBy('nom')->get(['id', 'nom']),
            'categories' => CategorieViolence::with(['types' => fn ($q) => $q->orderBy('nom')])->orderBy('id')->get(),
            'besoins' => Besoin::orderBy('id')->get(['code', 'nom', 'nom_wo']),
        ]);
    }

    public function store(Request $request, Signalements $signalements)
    {
        $d = $request->validate([
            'declarant' => 'required|in:victime,temoin,relais',
            'danger_immediat' => 'boolean',
            'categorie_id' => 'nullable|integer|exists:categories_violence,id',
            'types' => 'array|max:10',
            'types.*' => 'integer|exists:types_violence,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'departement_id' => 'nullable|integer|exists:departements,id',
            'commune_id' => 'nullable|integer|exists:communes,id',
            'lieu' => 'nullable|in:maison,ecole,travail,rue,autre',
            'periode_faits' => 'nullable|in:en_cours,aujourdhui,semaine,mois,plus_ancien',
            'victime_sexe' => 'nullable|in:F,M',
            'victime_tranche_age' => 'nullable|in:0-11,12-17,18-24,25-49,50+',
            'victime_handicap' => 'nullable|boolean',
            'auteur_connu' => 'nullable|boolean',
            'besoins' => 'array',
            'besoins.*' => 'string|exists:besoins,code',
            'recit' => 'nullable|string|max:3000',
            'consentement_contact' => 'boolean',
            'contact_telephone' => 'nullable|required_if:consentement_contact,true|string|max:20',
            'consentement_partage' => 'accepted',
            'langue' => 'nullable|in:fr,wo',
            'hors_ligne' => 'boolean',
        ], [
            'contact_telephone.required_if' => 'Indiquez un numéro, ou décochez la demande de rappel.',
            'consentement_partage.accepted' => 'Votre accord est nécessaire pour transmettre le signalement à une structure d\'aide.',
        ]);

        $canal = ! empty($d['hors_ligne']) ? 'hors_ligne' : 'web';
        unset($d['hors_ligne']);
        $d['consentement_partage'] = true;
        if (empty($d['consentement_contact'])) {
            $d['contact_telephone'] = null;
        }

        $s = $signalements->creer($d, $canal);

        // La référence passe par la session, jamais par l'URL : elle n'apparaît pas dans l'historique du navigateur.
        return redirect()->route('signaler.merci')->with('reference', $s->reference)->with('urgence', $s->urgence);
    }

    public function merci(Request $request)
    {
        if (! $request->session()->has('reference')) {
            return redirect()->route('accueil');
        }

        return Inertia::render('Public/Merci', [
            'reference' => $request->session()->get('reference'),
            'critique' => in_array($request->session()->get('urgence'), ['critique', 'haute'], true),
        ]);
    }
}
