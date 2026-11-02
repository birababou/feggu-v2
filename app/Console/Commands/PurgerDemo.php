<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Console\Command;

class PurgerDemo extends Command
{
    protected $signature = 'feggu:purger-demo {--comptes : supprime aussi les comptes @demo.feggu.org}';

    protected $description = 'Supprime toutes les données de démonstration avant la mise en production';

    public function handle(): int
    {
        $ids = Signalement::where('est_demo', true)->pluck('conversation_id')->filter();
        $n = Signalement::where('est_demo', true)->delete();
        Conversation::whereIn('id', $ids)->delete();
        Conversation::where('contact', 'like', 'demo-%')->delete();
        $this->info("$n signalements de démonstration supprimés.");

        if ($this->option('comptes')) {
            $c = User::where('email', 'like', '%@demo.feggu.org')->orWhere('email', 'demo@feggu.org')->delete();
            $this->info("$c comptes de démonstration supprimés.");
        }

        return self::SUCCESS;
    }
}
