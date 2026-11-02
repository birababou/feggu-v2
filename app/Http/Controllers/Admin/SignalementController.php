<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ref\Region;
use App\Models\Ref\TypeViolence;
use App\Models\Signalement;
use App\Models\Structure;
use App\Services\Canaux\EnvoiSms;
use App\Services\Orientation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SignalementController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $request->only(['statut', 'urgence', 'canal', 'region', 'q']);

        $liste = Signalement::query()
            ->visiblesPar($request->user())
            ->with('departement:id,nom', 'categorie:id,nom', 'structure:id,nom')
            ->when($filtres['statut'] ?? null, fn ($q, $v) => $v === 'ouverts' ? $q->where('statut', '!=', 'clos') : $q->where('statut', $v))
            ->when($filtres['urgence'] ?? null, fn ($q, $v) => $q->where('urgence', $v))
            ->when($filtres['canal'] ?? null, fn ($q, $v) => $q->where('canal', $v))
            ->when($filtres['region'] ?? null, fn ($q, $v) => $q->where('region_id', $v))
            ->when($filtres['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('reference', 'like', '%'.strtoupper($v).'%')
                ->orWhereHas('departement', fn ($d) => $d->where('nom', 'like', "%$v%"))
                ->orWhereHas('structure', fn ($st) => $st->where('nom', 'like', "%$v%"))))
            ->parPriorite()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Signalement $s) => [
                'id' => $s->id,
                'reference' => $s->reference,
                'urgence' => $s->urgence,
                'statut' => $s->statut,
                'statut_libelle' => $s->libelleStatut(),
                'canal' => Signalement::CANAUX[$s->canal] ?? $s->canal,
                'categorie' => $s->categorie?->nom,
                'departement' => $s->departement?->nom,
                'structure' => $s->structure?->nom,
                'cree_le' => $s->created_at->format('d/m/Y H:i'),
                'depuis' => $s->created_at->diffForHumans(),
                'est_demo' => $s->est_demo,
            ]);

        return Inertia::render('Admin/Signalements/Index', [
            'signalements' => $liste,
            'filtres' => $filtres,
            'options' => [
                'statuts' => Signalement::STATUTS,
                'canaux' => Signalement::CANAUX,
                'regions' => Region::orderBy('nom')->get(['id', 'nom']),
            ],
        ]);
    }

    public function show(Request $request, Signalement $signalement, Orientation $orientation)
    {
        abort_unless($signalement->estVisiblePar($request->user()), 403);
        AuditLog::noter('consultation', $signalement);

        $s = $signalement->load('categorie', 'region', 'departement', 'commune', 'lienAuteur', 'structure.organisation', 'suivis.user:id,name', 'conversation.messages');

        return Inertia::render('Admin/Signalements/Show', [
            'signalement' => [
                'id' => $s->id,
                'reference' => $s->reference,
                'est_demo' => $s->est_demo,
                'canal' => Signalement::CANAUX[$s->canal] ?? $s->canal,
                'statut' => $s->statut,
                'urgence' => $s->urgence,
                'score_urgence' => $s->score_urgence,
                'raisons_urgence' => $s->raisons_urgence ?? [],
                'declarant' => $s->declarant,
                'danger_immediat' => $s->danger_immediat,
                'categorie' => $s->categorie?->nom,
                'types' => TypeViolence::whereIn('id', $s->types ?? [])->pluck('nom'),
                'besoins' => $s->besoins ?? [],
                'lieu' => collect([$s->commune?->nom, $s->departement?->nom, $s->region?->nom])->filter()->implode(', ') ?: 'Non précisé',
                'type_lieu' => $s->lieu,
                'periode_faits' => $s->periode_faits,
                'victime' => array_filter([
                    'sexe' => ['F' => 'Femme / fille', 'M' => 'Homme / garçon'][$s->victime_sexe] ?? null,
                    'age' => $s->victime_tranche_age ? $s->victime_tranche_age.' ans' : null,
                    'handicap' => $s->victime_handicap ? 'Situation de handicap' : null,
                ]),
                'auteur' => $s->auteur_connu === null ? null : ($s->auteur_connu ? 'Connu de la victime'.($s->lienAuteur ? ' ('.$s->lienAuteur->nom.')' : '') : 'Inconnu'),
                'langue' => $s->langue === 'wo' ? 'Wolof' : 'Français',
                'recit' => $s->recit,
                'transcription' => $s->transcription,
                'consentement_contact' => $s->consentement_contact,
                'contact_telephone' => $s->consentement_contact ? $s->contact_telephone : null,
                'structure' => $s->structure ? ['id' => $s->structure->id, 'nom' => $s->structure->nom] : null,
                'cree_le' => $s->created_at->format('d/m/Y à H:i'),
                'messages' => $s->conversation?->messages->map(fn ($m) => ['sens' => $m->sens, 'contenu' => $m->contenu, 'audio' => $m->media_type === 'audio'])->values() ?? [],
            ],
            'suivis' => $s->suivis->map(fn ($x) => [
                'id' => $x->id, 'type' => $x->type, 'domaine' => $x->domaine, 'contenu' => $x->contenu, 'meta' => $x->meta,
                'auteur' => $x->user?->name ?? 'FEGGU (automatique)', 'date' => $x->created_at?->format('d/m/Y H:i'),
            ]),
            'suggestions' => $orientation->suggerer($s)->map(fn ($x) => [
                'id' => $x['structure']->id, 'nom' => $x['structure']->nom, 'departement' => $x['structure']->departement?->nom,
                'services' => $x['services_couverts'], 'score' => $x['score'],
            ]),
            'structures' => Structure::with('departement:id,nom')->where('actif', true)
                ->when($s->region_id, fn ($q) => $q->whereIn('departement_id', fn ($sq) => $sq->select('id')->from('departements')->where('region_id', $s->region_id)))
                ->orderBy('nom')->get(['id', 'nom', 'departement_id'])
                ->map(fn ($st) => ['id' => $st->id, 'nom' => $st->nom.' ('.$st->departement?->nom.')']),
            'domaines' => Signalement::DOMAINES,
            'statuts' => Signalement::STATUTS,
            'ia_active' => app(\App\Services\Ia\ClientIa::class)->actif(),
        ]);
    }

    public function orienter(Request $request, Signalement $signalement)
    {
        abort_unless($signalement->estVisiblePar($request->user()), 403);
        $data = $request->validate(['structure_id' => 'required|exists:structures,id']);
        $structure = Structure::find($data['structure_id']);

        $signalement->update(['structure_id' => $structure->id, 'oriente_le' => $signalement->oriente_le ?? now(), 'statut' => $signalement->statut === 'nouveau' ? 'oriente' : $signalement->statut]);
        $signalement->suivis()->create(['type' => 'orientation', 'user_id' => $request->user()->id, 'contenu' => 'Orienté vers '.$structure->nom, 'meta' => ['structure_id' => $structure->id], 'visible_declarant' => true]);
        AuditLog::noter('orientation', $signalement, ['structure_id' => $structure->id]);

        return back()->with('succes', 'Signalement orienté vers '.$structure->nom.'.');
    }

    public function suivre(Request $request, Signalement $signalement)
    {
        abort_unless($signalement->estVisiblePar($request->user()), 403);
        $data = $request->validate([
            'type' => 'required|in:contact,prise_en_charge,note,cloture',
            'domaine' => 'nullable|required_if:type,prise_en_charge|in:'.implode(',', array_keys(Signalement::DOMAINES)),
            'contenu' => 'nullable|string|max:3000',
        ]);

        $signalement->suivis()->create($data + ['user_id' => $request->user()->id, 'visible_declarant' => $data['type'] !== 'note']);

        $maj = match ($data['type']) {
            'contact' => ['statut' => in_array($signalement->statut, ['nouveau', 'oriente'], true) ? 'en_cours' : $signalement->statut, 'premier_contact_le' => $signalement->premier_contact_le ?? now()],
            'prise_en_charge' => ['statut' => 'pris_en_charge', 'pris_en_charge_le' => $signalement->pris_en_charge_le ?? now(), 'premier_contact_le' => $signalement->premier_contact_le ?? now()],
            'cloture' => ['statut' => 'clos', 'clos_le' => now()],
            default => [],
        };
        $signalement->update($maj);
        AuditLog::noter('suivi_'.$data['type'], $signalement);

        return back()->with('succes', 'Suivi enregistré.');
    }

    /** Rappel de la victime par SMS neutre, uniquement si elle l'a accepté. */
    public function contacter(Request $request, Signalement $signalement, EnvoiSms $sms)
    {
        abort_unless($signalement->estVisiblePar($request->user()), 403);
        abort_unless($signalement->consentement_contact && $signalement->contact_telephone, 422, 'La personne n\'a pas accepté d\'être contactée.');

        $structure = $signalement->structure?->nom ?? 'notre service';
        $sms->envoyer($signalement->contact_telephone, 'Bonjour, '.$structure.' souhaite vous parler. Appelez-nous quand vous pouvez le faire en sécurité'.($signalement->structure?->telephone ? ' au '.$signalement->structure->telephone : '').'. Ref '.$signalement->reference);
        $signalement->suivis()->create(['type' => 'contact', 'user_id' => $request->user()->id, 'contenu' => 'SMS de prise de contact envoyé']);
        $signalement->update(['premier_contact_le' => $signalement->premier_contact_le ?? now(), 'statut' => in_array($signalement->statut, ['nouveau', 'oriente'], true) ? 'en_cours' : $signalement->statut]);
        AuditLog::noter('contact_sms', $signalement);

        return back()->with('succes', 'SMS de prise de contact envoyé.');
    }
}
