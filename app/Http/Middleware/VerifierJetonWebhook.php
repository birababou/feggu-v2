<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les webhooks (SMS, USSD, WhatsApp, voix) n'acceptent que les appels portant le jeton partagé.
 * En production, sans jeton configuré, tout est refusé (la v1 acceptait n'importe quel appel).
 */
class VerifierJetonWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        // Alternative au jeton : adresses IP de l'agrégateur (utile si son URL de rappel ne peut pas porter de paramètre).
        $ipsAutorisees = array_filter(array_map('trim', explode(',', (string) config('feggu.webhook_ips'))));
        if ($ipsAutorisees && in_array($request->ip(), $ipsAutorisees, true)) {
            return $next($request);
        }

        $attendu = (string) config('feggu.webhook_token');
        $recu = (string) ($request->header('X-Feggu-Token') ?? $request->query('token', ''));

        if ($attendu === '') {
            abort_if(app()->isProduction(), 403, 'Webhook non configuré');

            return $next($request);
        }

        if (! hash_equals($attendu, $recu)) {
            \Illuminate\Support\Facades\Log::warning('Webhook refusé', ['ip' => $request->ip(), 'chemin' => $request->path()]);
            abort(403);
        }

        return $next($request);
    }
}
