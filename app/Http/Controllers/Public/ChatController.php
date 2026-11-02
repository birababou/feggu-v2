<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Canaux\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** Chatbot web : même moteur que le SMS, l'USSD et WhatsApp. */
class ChatController extends Controller
{
    public function page()
    {
        return Inertia::render('Public/Chat');
    }

    public function message(Request $request, Conversation $moteur)
    {
        $request->validate(['texte' => 'nullable|string|max:1000']);
        $id = $request->session()->get('chat_id') ?? tap('web-'.Str::random(32), fn ($v) => $request->session()->put('chat_id', $v));

        $r = $moteur->recevoir('web', $id, (string) $request->input('texte', ''));
        if ($r['fin']) {
            $request->session()->forget('chat_id');
        }

        return response()->json(['reponse' => $r['reponse'], 'fin' => $r['fin']]);
    }
}
