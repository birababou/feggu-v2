<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organisation;
use App\Models\Ref\Departement;
use App\Models\Signalement;
use App\Models\Structure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Antennes des structures partenaires : ce sont elles qui reçoivent les signalements de leur département. */
class StructureController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $request->only(['q', 'etat']);

        return Inertia::render('Admin/Structures', [
            'structures' => Structure::with('organisation:id,nom,sigle,services', 'departement.region')->withCount(['users' => fn ($q) => $q->where('actif', true)])
                ->when($filtres['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('nom', 'like', "%$v%")
                    ->orWhereHas('organisation', fn ($o) => $o->where('nom', 'like', "%$v%")->orWhere('sigle', 'like', "%$v%"))
                    ->orWhereHas('departement', fn ($d) => $d->where('nom', 'like', "%$v%"))))
                ->when(($filtres['etat'] ?? null) === 'sans_agent', fn ($q) => $q->whereDoesntHave('users', fn ($u) => $u->where('actif', true)))
                ->when(($filtres['etat'] ?? null) === 'inactives', fn ($q) => $q->where('actif', false))
                ->orderBy('departement_id')->orderBy('nom')->paginate(20)->withQueryString()
                ->through(fn (Structure $s) => [
                    'id' => $s->id, 'nom' => $s->nom, 'organisation' => $s->organisation?->sigle ?: $s->organisation?->nom,
                    'departement' => $s->departement?->nom, 'region' => $s->departement?->region?->nom,
                    'telephone' => $s->telephone, 'services' => $s->servicesEffectifs(), 'agents' => $s->users_count, 'actif' => $s->actif,
                ]),
            'filtres' => $filtres,
            'organisations' => Organisation::orderBy('nom')->get(['id', 'nom', 'sigle', 'services']),
            'departements' => Departement::with('region:id,nom')->orderBy('nom')->get()->map(fn ($d) => ['id' => $d->id, 'nom' => $d->nom.' ('.$d->region?->nom.')']),
            'domaines' => Signalement::DOMAINES,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'organisation_id' => 'required|exists:organisations,id',
            'departement_id' => ['required', 'integer', 'exists:departements,id', Rule::unique('structures')->where('organisation_id', $request->input('organisation_id'))],
            'nom' => 'nullable|string|max:120',
            'telephone' => 'nullable|string|max:30',
            'services' => 'array',
            'services.*' => 'in:'.implode(',', array_keys(Signalement::DOMAINES)),
        ], ['departement_id.unique' => 'Cette organisation a déjà une antenne dans ce département.']);

        $org = Organisation::find($d['organisation_id']);
        $s = Structure::create([
            'organisation_id' => $org->id,
            'departement_id' => $d['departement_id'],
            'nom' => ($d['nom'] ?? null) ?: ($org->sigle ?: $org->nom).' '.Departement::find($d['departement_id'])->nom,
            'telephone' => $d['telephone'] ?? null,
            'services' => ! empty($d['services']) && $d['services'] !== $org->services ? $d['services'] : null,
        ]);
        AuditLog::noter('structure_creee', $s);

        return back()->with('succes', 'Antenne « '.$s->nom.' » créée. Ajoutez maintenant ses agents dans Utilisateurs.');
    }

    public function basculer(Structure $structure)
    {
        $structure->update(['actif' => ! $structure->actif]);
        AuditLog::noter($structure->actif ? 'structure_activee' : 'structure_desactivee', $structure);

        return back();
    }
}
