<?php

namespace Tests;

use App\Models\Organisation;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

trait AvecReferentiels
{
    use RefreshDatabase;

    protected function setUpReferentiels(): void
    {
        Cache::flush();
        Artisan::call('feggu:import-referentiels');
    }

    /** Antenne d'une organisation v1 dans un département, avec un agent actif. */
    protected function antenne(string $sigle, int $departement): Structure
    {
        $st = Structure::create([
            'organisation_id' => Organisation::where('sigle', $sigle)->firstOrFail()->id,
            'departement_id' => $departement,
            'nom' => "$sigle $departement",
        ]);
        User::create(['name' => "Agent $sigle", 'email' => strtolower($sigle).$departement.'@test.sn', 'password' => 'Secret-123456', 'role' => 'agent', 'structure_id' => $st->id, 'telephone' => '770000000']);

        return $st;
    }
}
