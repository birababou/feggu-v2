<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Canaux\Conversation;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Démonstration des canaux sans téléphone : envoie un message au moteur comme le ferait l'agrégateur.
 * Les signalements créés ici sont marqués « démo ».
 */
class SimulateurController extends Controller
{
    public function page()
    {
        return Inertia::render('Admin/Simulateur', [
            'numero_court' => config('feggu.numero_court'),
            'code_ussd' => trim(config('feggu.code_ussd').(config('feggu.ussd_choix') ? ' puis '.config('feggu.ussd_choix') : '')),
        ]);
    }

    public function envoyer(Request $request, Conversation $moteur)
    {
        $d = $request->validate([
            'canal' => 'required|in:sms,ussd,whatsapp',
            'numero' => 'required|string|max:20',
            'texte' => 'nullable|string|max:500',
            'session' => 'nullable|string|max:60',
        ]);

        $r = $moteur->recevoir($d['canal'], 'demo-'.$d['numero'], (string) ($d['texte'] ?? ''), $d['canal'] === 'ussd' ? ($d['session'] ?? null) : null);
        if ($r['conversation']->signalement) {
            $r['conversation']->signalement->update(['est_demo' => true]);
        }

        return response()->json([
            'reponse' => $r['reponse'],
            'fin' => $r['fin'],
            'reference' => $r['conversation']->signalement?->reference,
            'signalement_id' => $r['conversation']->signalement_id,
        ]);
    }
}
