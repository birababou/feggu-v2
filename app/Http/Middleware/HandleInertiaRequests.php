<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'langue' => $request->session()->get('langue', 'fr'),
            'feggu' => [
                'numero_court' => config('feggu.numero_court'),
                'code_ussd' => config('feggu.code_ussd'),
                'ussd_choix' => config('feggu.ussd_choix'),
                'numero_whatsapp' => config('feggu.numero_whatsapp'),
                'numero_vocal' => config('feggu.numero_vocal'),
                'numeros_urgence' => config('feggu.numeros_urgence'),
                'demo' => (bool) config('feggu.demo'),
                'code_source' => config('feggu.code_source'),
                'assistant' => app(\App\Services\Canaux\AssistantIa::class)->disponiblePour('web'),
            ],
            'auth' => $user ? [
                'nom' => $user->name,
                'role' => $user->role,
                'role_libelle' => User::ROLES[$user->role] ?? $user->role,
                'structure' => $user->structure?->nom,
            ] : null,
            'flash' => [
                'succes' => fn () => $request->session()->get('succes'),
                'erreur' => fn () => $request->session()->get('erreur'),
            ],
        ];
    }
}
