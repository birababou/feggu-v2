<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use Inertia\Inertia;

class AccueilController extends Controller
{
    public function __invoke()
    {
        $base = Signalement::query()->when(! config('feggu.demo'), fn ($q) => $q->where('est_demo', false));

        return Inertia::render('Public/Accueil', [
            'chiffres' => [
                'signalements' => (clone $base)->count(),
                'pris_en_charge' => (clone $base)->whereNotNull('pris_en_charge_le')->count(),
                'departements' => (clone $base)->distinct()->count('departement_id'),
            ],
        ]);
    }
}
