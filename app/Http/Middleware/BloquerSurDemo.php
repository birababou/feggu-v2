<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Site de démonstration : les identifiants du compte de démo sont publics (README, dossier de candidature).
 * On empêche donc ce qui modifierait la démo pour les visiteurs suivants : comptes, structures, double authentification.
 */
class BloquerSurDemo
{
    public function handle(Request $request, Closure $next)
    {
        if (config('feggu.demo')) {
            if ($request->expectsJson()) {
                abort(403, 'Désactivé sur le site de démonstration.');
            }

            return back()->with('erreur', 'Action désactivée sur le site de démonstration.');
        }

        return $next($request);
    }
}
