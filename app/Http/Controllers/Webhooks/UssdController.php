<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Canaux\Conversation;
use Illuminate\Http\Request;

/**
 * Session USSD. Deux usages :
 *  - FEGGU a son propre code : l'agrégateur appelle directement ce webhook ;
 *  - FEGGU est une entrée d'un menu existant (ex. #2001# puis 8) : la plateforme USSD transmet la session ici.
 *
 * Paramètres acceptés : sessionId|session_id, phoneNumber|msisdn|phone, text|input (cumulé « 5*1*2 » ou dernière saisie seule).
 * Réponse texte « CON ... » / « END ... », ou JSON {reponse, fin} si la requête demande du JSON.
 */
class UssdController extends Controller
{
    public function __invoke(Request $request, Conversation $moteur)
    {
        $session = (string) ($request->input('sessionId') ?? $request->input('session_id', ''));
        $numero = (string) ($request->input('phoneNumber') ?? $request->input('msisdn') ?? $request->input('phone', ''));
        $cumul = (string) ($request->input('text') ?? $request->input('input', ''));

        abort_if($session === '' || $numero === '', 422, 'sessionId et phoneNumber sont obligatoires');

        // Seule la dernière saisie est nouvelle ; une saisie vide (ou le choix du menu parent) ouvre FEGGU.
        $saisie = $cumul === '' ? '' : (string) last(explode('*', $cumul));
        $resultat = $moteur->recevoir('ussd', $numero, $saisie, $session);

        if ($request->wantsJson() || $request->isJson()) {
            return response()->json(['reponse' => $resultat['reponse'], 'fin' => $resultat['fin']]);
        }

        return response(($resultat['fin'] ? 'END ' : 'CON ').$resultat['reponse'])->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
