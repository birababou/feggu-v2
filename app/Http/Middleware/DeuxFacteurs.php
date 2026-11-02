<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige la validation du code à usage unique après le mot de passe, et force l'activation
 * de la double authentification pour les rôles qui l'imposent (administrateurs).
 */
class DeuxFacteurs
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->aDeuxFacteurs() && ! $request->session()->get('2fa_valide')) {
            return redirect()->route('2fa.verifier');
        }

        if ($user->deuxFacteursObligatoire() && ! $user->aDeuxFacteurs() && ! $request->routeIs('2fa.*', 'deconnexion')) {
            return redirect()->route('2fa.activer');
        }

        return $next($request);
    }
}
