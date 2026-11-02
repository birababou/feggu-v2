<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Canaux\Conversation;
use App\Services\Canaux\EnvoiSms;
use App\Services\Contenu\Textes;
use App\Services\Signalements;
use Illuminate\Http\Request;

/**
 * Ligne vocale : message vocal laissé par la victime, et appel « bip » (appel manqué gratuit → rappel).
 */
class VoixController extends Controller
{
    /** Message vocal enregistré par le serveur vocal : transcrit puis traité comme un signalement libre. */
    public function message(Request $request, Conversation $moteur, EnvoiSms $sms)
    {
        $data = $request->validate(['from' => 'required|string', 'recording_url' => 'required|url', 'langue' => 'nullable|in:fr,wo']);
        $resultat = $moteur->recevoir('voix', $data['from'], null, null, $data['recording_url']);

        // Si la transcription n'a rien donné, on crée quand même l'alerte : un agent écoutera l'enregistrement.
        $conv = $resultat['conversation']->fresh();
        if (! $conv->signalement_id) {
            app(Signalements::class)->creer([
                'recit' => '[Message vocal à écouter]',
                'langue' => $data['langue'] ?? 'wo',
                'consentement_contact' => true,
                'contact_telephone' => $data['from'],
                'conversation_id' => $conv->id,
            ], 'voix');
        }

        $sms->envoyer($data['from'], Textes::t('rapide', $data['langue'] ?? 'fr', ['code' => $conv->fresh()->signalement?->reference ?? '']));

        return response()->json(['ok' => true]);
    }

    /** Appel manqué : la personne demande à être rappelée, gratuitement pour elle. */
    public function appelManque(Request $request, Signalements $signalements, EnvoiSms $sms)
    {
        $data = $request->validate(['from' => 'required|string']);

        $s = $signalements->creer([
            'recit' => 'Appel « bip » : la personne demande à être rappelée.',
            'consentement_contact' => true,
            'contact_telephone' => $data['from'],
        ], 'appel_manque');

        // Message neutre : rien ne doit indiquer une violence sur l'écran du téléphone.
        $sms->envoyer($data['from'], 'Bonjour, nous avons bien reçu votre appel et nous vous rappelons bientôt. Ref '.$s->reference);

        return response()->json(['ok' => true]);
    }
}
