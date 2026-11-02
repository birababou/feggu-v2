<?php

namespace App\Services\Ia;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client pour un modèle de langue hébergé localement (API compatible OpenAI : Ollama, vLLM...).
 * Les données des victimes ne quittent pas le serveur FEGGU.
 */
class ClientIa
{
    public function actif(): bool
    {
        return filled(config('feggu.ia.url'));
    }

    /** Renvoie la réponse JSON du modèle, ou null si l'IA est indisponible (les règles prennent alors le relais). */
    public function json(string $systeme, string $utilisateur): ?array
    {
        return $this->discuter([
            ['role' => 'system', 'content' => $systeme],
            ['role' => 'user', 'content' => $utilisateur],
        ]);
    }

    /**
     * Conversation complète (consignes + historique), réponse attendue en JSON.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     */
    public function discuter(array $messages, ?int $timeout = null, float $temperature = 0): ?array
    {
        if (! $this->actif()) {
            return null;
        }

        try {
            $reponse = Http::timeout($timeout ?? config('feggu.ia.timeout', 20))
                ->withToken((string) config('feggu.ia.cle'))
                ->post(rtrim(config('feggu.ia.url'), '/').'/chat/completions', [
                    'model' => config('feggu.ia.modele'),
                    'temperature' => $temperature,
                    // Borne la réponse : en mode JSON, un petit modèle peut sinon produire des espaces sans fin
                    // et occuper le serveur d'IA jusqu'au délai maximal.
                    'max_tokens' => (int) config('feggu.ia.max_tokens', 400),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => $messages,
                ])->throw()->json('choices.0.message.content');

            $donnees = json_decode((string) $reponse, true);

            return is_array($donnees) ? $donnees : null;
        } catch (\Throwable $e) {
            Log::warning('IA indisponible, repli sur les règles', ['erreur' => $e->getMessage()]);

            return null;
        }
    }

    /** Transcrit un message vocal (service compatible Whisper). */
    public function transcrire(string $urlAudio, string $langue = 'wo'): ?string
    {
        $service = config('feggu.ia.transcription_url');
        if (! $service) {
            return null;
        }

        try {
            $audio = Http::timeout(30)->get($urlAudio)->throw()->body();

            return Http::timeout(60)
                ->withToken((string) config('feggu.ia.cle'))
                ->attach('file', $audio, 'message.ogg')
                ->post(rtrim($service, '/').'/audio/transcriptions', ['model' => 'whisper-1', 'language' => $langue])
                ->throw()->json('text');
        } catch (\Throwable $e) {
            Log::warning('Transcription impossible', ['erreur' => $e->getMessage()]);

            return null;
        }
    }
}
