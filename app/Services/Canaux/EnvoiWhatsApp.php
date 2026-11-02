<?php

namespace App\Services\Canaux;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnvoiWhatsApp
{
    public function envoyer(string $numero, string $texte): bool
    {
        if (config('feggu.whatsapp.driver') !== 'meta') {
            Log::channel('single')->info('[WhatsApp simulé] '.$texte);

            return true;
        }

        $c = config('feggu.whatsapp');
        $reponse = Http::timeout(15)->withToken((string) $c['access_token'])
            ->post("https://graph.facebook.com/v21.0/{$c['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D+/', '', $numero),
                'type' => 'text',
                'text' => ['body' => $texte],
            ]);

        return $reponse->successful();
    }
}
