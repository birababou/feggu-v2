<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ConnexionController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Connexion');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|string', 'password' => 'required|string']);

        $user = User::where('email', $data['email'])->first();

        // Comptes repris de la v1 : mot de passe MD5, converti en bcrypt à la première connexion réussie.
        if ($user && $user->legacy_md5 && hash_equals($user->legacy_md5, md5($data['password']))) {
            $user->forceFill(['password' => Hash::make($data['password']), 'legacy_md5' => null])->save();
        }

        if (! $user || ! $user->actif || ! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            AuditLog::create(['action' => 'connexion_echec', 'ip' => $request->ip(), 'meta' => ['email' => $data['email']]]);
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects.']);
        }

        $request->session()->regenerate();
        $request->session()->forget('2fa_valide');
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::noter('connexion');

        return redirect()->intended(route('admin.tableau'));
    }

    public function destroy(Request $request)
    {
        AuditLog::noter('deconnexion');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion');
    }
}
