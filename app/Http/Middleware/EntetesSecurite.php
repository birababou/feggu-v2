<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de protection : pas d'indexation des pages sensibles, pas de cache des réponses,
 * pas d'envoi de l'adresse de la page aux autres sites, pas d'intégration dans un autre site.
 */
class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);
        $reponse->headers->set('Referrer-Policy', 'no-referrer');
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('X-Frame-Options', 'DENY');
        $reponse->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(self)');

        if (! $request->is('/') && ! $request->is('aide') && ! $request->is('confidentialite')) {
            $reponse->headers->set('Cache-Control', 'no-store, private');
            $reponse->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $reponse;
    }
}
