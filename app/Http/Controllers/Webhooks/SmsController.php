<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Canaux\Conversation;
use App\Services\Canaux\EnvoiSms;
use Illuminate\Http\Request;

/**
 * SMS entrant sur le numéro court (21211) via l'agrégateur LAM.
 * Accepte le format de la v1 (GET ?id=&FROM=&MESSAGE=) pour ne rien changer côté agrégateur.
 */
class SmsController extends Controller
{
    public function __invoke(Request $request, Conversation $moteur, EnvoiSms $sms)
    {
        $de = (string) ($request->input('FROM') ?? $request->input('from', ''));
        $texte = (string) ($request->input('MESSAGE') ?? $request->input('message') ?? $request->input('text', ''));

        abort_if($de === '', 422, 'Expéditeur manquant');

        $texte = strip_tags($texte);
        $traiter = function () use ($de, $texte) {
            $resultat = app(Conversation::class)->recevoir('sms', $de, $texte);
            app(EnvoiSms::class)->envoyer($de, $resultat['reponse']);
        };
        // Avec l'assistante IA activée pour les SMS, LAM reçoit « OK » tout de suite et la réponse part ensuite.
        app(\App\Services\Canaux\AssistantIa::class)->disponiblePour('sms') ? dispatch($traiter)->afterResponse() : $traiter();

        return response("OK\n", 200)->header('Content-Type', 'text/plain');
    }
}
