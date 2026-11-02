<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Canaux\Conversation;
use App\Services\Canaux\EnvoiWhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business (API Cloud de Meta) : messages texte et notes vocales.
 * Accepte aussi un format simple {from, text, audio_url} pour les tests et d'autres fournisseurs.
 */
class WhatsAppController extends Controller
{
    /** Vérification de l'abonnement par Meta. */
    public function verifier(Request $request)
    {
        abort_unless(
            $request->query('hub_mode') === 'subscribe'
            && hash_equals((string) config('feggu.whatsapp.verify_token'), (string) $request->query('hub_verify_token')),
            403
        );

        return response((string) $request->query('hub_challenge'));
    }

    public function recevoir(Request $request, Conversation $moteur, EnvoiWhatsApp $whatsapp)
    {
        if ($secret = config('feggu.whatsapp.app_secret')) {
            $signature = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            abort_unless(hash_equals($signature, (string) $request->header('X-Hub-Signature-256')), 403);
        }

        // Format simple {from, text, audio_url} : relais depuis une autre plateforme WhatsApp (ex. WhatsApp Mlouma).
        // La réponse est renvoyée dans la réponse HTTP ; c'est la plateforme relais qui l'envoie à l'abonnée.
        if (! $request->has('entry')) {
            $donnees = $request->validate(['from' => 'required|string|max:30', 'text' => 'nullable|string|max:4000', 'audio_url' => 'nullable|url']);
            $resultat = $moteur->recevoir('whatsapp', $donnees['from'], $donnees['text'] ?? null, null, $donnees['audio_url'] ?? null);

            return response()->json(['ok' => true, 'reponse' => $resultat['reponse'], 'fin' => $resultat['fin']]);
        }

        // Format de l'API Cloud de Meta : FEGGU répond lui-même par l'API.
        $traites = 0;
        foreach (collect(data_get($request->all(), 'entry.*.changes.*.value.messages.*')) as $m) {
            $de = (string) ($m['from'] ?? '');
            if ($de === '') {
                continue;
            }
            $audio = isset($m['audio']['id']) ? $this->urlMedia($m['audio']['id']) : null;
            $texte = $m['text']['body'] ?? null;
            $traiter = function () use ($de, $texte, $audio) {
                $resultat = app(Conversation::class)->recevoir('whatsapp', $de, $texte, null, $audio);
                app(EnvoiWhatsApp::class)->envoyer($de, $resultat['reponse']);
            };
            // Meta attend une réponse HTTP rapide : avec l'assistante IA, la réponse est rédigée puis envoyée juste après.
            app(\App\Services\Canaux\AssistantIa::class)->disponiblePour('whatsapp') ? dispatch($traiter)->afterResponse() : $traiter();
            $traites++;
        }

        return response()->json(['ok' => true, 'traites' => $traites]);
    }

    private function urlMedia(string $id): ?string
    {
        if (config('feggu.whatsapp.driver') !== 'meta') {
            return null;
        }

        return Http::withToken((string) config('feggu.whatsapp.access_token'))
            ->get("https://graph.facebook.com/v21.0/$id")->json('url');
    }
}
