<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ref\Region;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UtilisateurController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $request->only(['q', 'role', 'etat']);

        return Inertia::render('Admin/Utilisateurs', [
            'utilisateurs' => User::with('structure:id,nom')
                ->when($filtres['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")
                    ->orWhereHas('structure', fn ($s) => $s->where('nom', 'like', "%$v%"))))
                ->when($filtres['role'] ?? null, fn ($q, $v) => $q->where('role', $v))
                ->when(($filtres['etat'] ?? null) === 'actifs', fn ($q) => $q->where('actif', true))
                ->when(($filtres['etat'] ?? null) === 'inactifs', fn ($q) => $q->where('actif', false))
                ->orderBy('name')->paginate(20)->withQueryString()
                ->through(fn (User $u) => [
                    'id' => $u->id, 'nom' => $u->name, 'email' => $u->email, 'role' => User::ROLES[$u->role] ?? $u->role,
                    'structure' => $u->structure?->nom, 'actif' => $u->actif, 'deux_facteurs' => $u->aDeuxFacteurs(),
                    'derniere_connexion' => $u->last_login_at?->diffForHumans() ?? 'jamais',
                ]),
            'filtres' => $filtres,
            'roles' => User::ROLES,
            'structures' => Structure::with('departement:id,nom')->orderBy('nom')->get()->map(fn ($s) => ['id' => $s->id, 'nom' => $s->nom.' ('.$s->departement?->nom.')']),
            'regions' => Region::orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'telephone' => 'nullable|string|max:20',
            'role' => 'required|in:'.implode(',', array_keys(User::ROLES)),
            'structure_id' => 'nullable|required_if:role,agent|exists:structures,id',
            'region_id' => 'nullable|required_if:role,superviseur|exists:regions,id',
            'password' => ['required', Password::min(10)->mixedCase()->numbers()],
        ]);

        $u = User::create($d);
        AuditLog::noter('utilisateur_cree', $u);

        return back()->with('succes', 'Compte créé pour '.$u->name.'.');
    }

    public function basculer(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Vous ne pouvez pas désactiver votre propre compte.');
        $user->update(['actif' => ! $user->actif]);
        AuditLog::noter($user->actif ? 'utilisateur_active' : 'utilisateur_desactive', $user);

        return back();
    }
}
