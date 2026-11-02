<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Webhooks\SmsController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le 21211 (agrégateur LAM) appelle encore les adresses de la v1 :
 *   /alert-info…?id=&FROM=&MESSAGE=   et   /index.php?p=alert-info&id=&FROM=&MESSAGE=
 * Ces appels sont traités par le webhook SMS de la V2, avec la même vérification (jeton ou IP autorisée).
 */
class AnciennesAdressesV1
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with($request->path(), 'alert-info') || $request->query('p') === 'alert-info') {
            return app(VerifierJetonWebhook::class)->handle($request, fn ($r) => app()->call(SmsController::class, ['request' => $r]));
        }

        return $next($request);
    }
}
