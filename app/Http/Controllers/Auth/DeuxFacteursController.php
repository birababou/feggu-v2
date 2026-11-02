<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DeuxFacteursController extends Controller
{
    public function activer(Request $request)
    {
        $secret = $request->session()->get('2fa_secret_temp') ?? Totp::nouveauSecret();
        $request->session()->put('2fa_secret_temp', $secret);

        return Inertia::render('Auth/DeuxFacteursActiver', [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'otpauth' => Totp::urlOtpauth($secret, $request->user()->email),
            'obligatoire' => $request->user()->deuxFacteursObligatoire(),
        ]);
    }

    public function confirmer(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $secret = (string) $request->session()->get('2fa_secret_temp');

        if (! $secret || ! Totp::verifier($secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.']);
        }

        $request->user()->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('2fa_secret_temp');
        $request->session()->put('2fa_valide', true);
        AuditLog::noter('2fa_activee');

        return redirect()->route('admin.tableau')->with('succes', 'Double authentification activée.');
    }

    public function verifier()
    {
        return Inertia::render('Auth/DeuxFacteursVerifier');
    }

    public function valider(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        if (! Totp::verifier((string) $request->user()->two_factor_secret, $request->input('code'))) {
            AuditLog::noter('2fa_echec');
            throw ValidationException::withMessages(['code' => 'Code incorrect.']);
        }

        $request->session()->put('2fa_valide', true);

        return redirect()->intended(route('admin.tableau'));
    }
}
