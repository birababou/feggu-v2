<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Référentiels v1 + un compte administrateur.
     * Les antennes et comptes de démonstration sont créés par DemoSeeder (php artisan db:seed --class=DemoSeeder).
     */
    public function run(): void
    {
        Artisan::call('feggu:import-referentiels');
        $this->command?->line(Artisan::output());

        if (! User::where('role', 'admin')->exists()) {
            $mdp = env('FEGGU_ADMIN_PASSWORD') ?: Str::password(16);
            User::create([
                'name' => 'Administrateur FEGGU',
                'email' => env('FEGGU_ADMIN_EMAIL', 'admin@feggu.org'),
                'password' => $mdp,
                'role' => 'admin',
            ]);
            $this->command?->warn('Compte administrateur : '.env('FEGGU_ADMIN_EMAIL', 'admin@feggu.org').' / '.$mdp.' (à changer)');
        }
    }
}
