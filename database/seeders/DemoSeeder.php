<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\Signalement;
use App\Models\Structure;
use App\Models\User;
use App\Services\Signalements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Données de démonstration, entièrement fictives et marquées est_demo = true.
 * Elles servent à montrer la plateforme (UNICEF, partenaires) sans exposer aucun cas réel.
 * Suppression : php artisan feggu:purger-demo
 */
class DemoSeeder extends Seeder
{
    /** Antennes de démonstration : sigle v1 de l'organisation → départements (identifiants v1). */
    private const ANTENNES = [
        'AJS' => [40, 41, 2, 30, 39],
        'DS' => [40, 41, 2, 3, 30, 39, 23, 45, 18, 16],
        'CLVF' => [40, 41, 39, 23, 16],
        'AEMO' => [40, 2, 30, 39, 45],
        'CCA' => [40, 2, 30],
        'PFICDPE' => [40, 41, 39, 23, 18, 45],
    ];

    private const RECITS = [
        [2, '25-49', 'F', 'Ma voisine est frappée régulièrement par son mari, on l\'entend crier la nuit.', ['medical', 'securite']],
        [7, '12-17', 'F', 'Une fille de 14 ans de notre quartier va être mariée le mois prochain.', ['juridique']],
        [4, '25-49', 'F', 'Mon mari ne donne plus la dépense depuis trois mois et menace de me chasser.', ['mediation', 'economique']],
        [6, '0-11', 'F', 'Une excision est prévue pour deux petites filles pendant les vacances.', []],
        [1, '12-17', 'F', 'Une élève est harcelée par un enseignant qui lui envoie des messages.', ['psychologique', 'juridique']],
        [2, '25-49', 'F', 'Sama jëkkër dafa may dóor, du ma may dara.', ['securite']],
        [5, '0-11', 'M', 'Un talibé est obligé de mendier toute la journée et il est puni s\'il ne ramène pas assez.', []],
        [3, '18-24', 'F', 'Mon ancien fiancé me menace de publier des photos si je ne reviens pas.', ['psychologique', 'juridique']],
        [1, '18-24', 'F', 'J\'ai été agressée en rentrant du marché, je n\'ose pas en parler à ma famille.', ['medical', 'psychologique']],
        [2, '50+', 'F', 'Une femme âgée est maltraitée par son fils, elle a des blessures au bras.', ['medical']],
        [8, '12-17', 'F', 'Des adolescentes sont recrutées près de la gare routière par des hommes.', ['securite']],
        [3, '25-49', 'F', 'Ma belle-famille m\'insulte tous les jours et veut que je parte avec mes enfants.', ['mediation', 'hebergement']],
    ];

    public function run(Signalements $service): void
    {
        mt_srand(2026);
        $agents = 0;

        foreach (self::ANTENNES as $sigle => $departements) {
            $org = Organisation::where('sigle', $sigle)->first();
            if (! $org) {
                continue;
            }
            foreach ($departements as $dep) {
                $nomDep = \App\Models\Ref\Departement::find($dep)?->nom;
                $st = Structure::firstOrCreate(
                    ['organisation_id' => $org->id, 'departement_id' => $dep],
                    ['nom' => $sigle.' '.$nomDep]
                );
                User::firstOrCreate(['email' => strtolower($sigle).'.'.\Illuminate\Support\Str::slug($nomDep).'@demo.feggu.org'], [
                    'name' => 'Agent '.$st->nom,
                    'password' => 'Demo-Feggu-2026',
                    'role' => 'agent',
                    'structure_id' => $st->id,
                ]);
                $agents++;
            }
        }

        User::firstOrCreate(['email' => 'demo@feggu.org'], ['name' => 'Compte de démonstration', 'password' => 'Demo-Feggu-2026', 'role' => 'admin']);
        User::firstOrCreate(['email' => 'superviseur.thies@demo.feggu.org'], ['name' => 'Superviseure Thiès', 'password' => 'Demo-Feggu-2026', 'role' => 'superviseur', 'region_id' => 13]);

        $deps = [40, 40, 40, 41, 41, 2, 3, 30, 39, 39, 23, 45, 18, 16, 4, 31];
        $canaux = ['sms', 'sms', 'sms', 'web', 'web', 'whatsapp', 'whatsapp', 'ussd', 'voix', 'appel_manque', 'relais'];

        for ($i = 0; $i < 90; $i++) {
            [$cat, $age, $sexe, $recit, $besoins] = self::RECITS[$i % count(self::RECITS)];
            $dep = $deps[mt_rand(0, count($deps) - 1)];
            $creeLe = Carbon::now()->subDays(mt_rand(0, 330))->setTime(mt_rand(6, 22), mt_rand(0, 59));

            $s = $service->creer([
                'declarant' => mt_rand(0, 2) ? 'victime' : 'temoin',
                'danger_immediat' => mt_rand(0, 9) === 0,
                'categorie_id' => $cat,
                'departement_id' => $dep,
                'victime_tranche_age' => $age,
                'victime_sexe' => $sexe,
                'besoins' => $besoins,
                'recit' => $recit,
                'langue' => str_contains($recit, 'dóor') ? 'wo' : 'fr',
                'consentement_contact' => (bool) mt_rand(0, 1),
                'contact_telephone' => '77'.mt_rand(1000000, 9999999),
                'consentement_partage' => true,
                'est_demo' => true,
            ], $canaux[mt_rand(0, count($canaux) - 1)]);

            // Sans IA installée, la démo montre tout de même à quoi ressemble son analyse (exemple fictif).
            if (! app(\App\Services\Ia\ClientIa::class)->actif()) {
                $s->suivis()->create(['type' => 'triage', 'meta' => [
                    'ia' => true, 'modele' => 'qwen2.5:3b (exemple de démo)', 'urgence' => $s->urgence, 'score' => $s->score_urgence,
                    'danger_immediat' => (bool) $s->danger_immediat, 'urgence_relevee' => false, 'categorie_ia' => $cat,
                    'raisons' => [$s->danger_immediat ? 'La personne indique être en danger en ce moment.' : self::LECTURES_IA[$cat] ?? 'Récit cohérent avec la catégorie déclarée, pas de danger immédiat décrit.'],
                ]]);
            }

            $this->faireAvancer($s, $creeLe);
        }

        $this->command?->info("Démo : $agents antennes, 90 signalements fictifs. Connexion : demo@feggu.org / Demo-Feggu-2026");
    }

    private const LECTURES_IA = [
        1 => 'Violence sexuelle décrite, besoin probable de prise en charge médicale rapide.',
        2 => 'Coups répétés au domicile par un proche, risque de récidive.',
        3 => 'Menaces et humiliations répétées, pas de danger immédiat décrit.',
        4 => 'Refus de dépense et abandon du foyer, besoin socio-économique.',
        5 => 'Enfant concerné : signalement à traiter avec la protection de l\'enfance.',
        6 => 'Excision évoquée sur une mineure, intervention préventive possible.',
        7 => 'Mariage précoce ou forcé en préparation, agir avant la date.',
        8 => 'Exploitation sexuelle probable, orientation vers une structure spécialisée.',
    ];

    /** Simule le traitement : plus le cas est ancien, plus il a avancé. */
    private function faireAvancer(Signalement $s, Carbon $creeLe): void
    {
        $age = $creeLe->diffInDays(now());
        $s->timestamps = false;
        $s->forceFill(['created_at' => $creeLe, 'updated_at' => $creeLe])->save();
        $s->suivis()->update(['created_at' => $creeLe]);

        if (! $s->structure_id) {
            return;
        }
        $s->oriente_le = $creeLe->copy()->addMinutes(1);
        if ($age < 2 && mt_rand(0, 1)) {
            $s->save();

            return;
        }

        $contact = $creeLe->copy()->addHours($s->urgence === 'critique' ? mt_rand(1, 4) : mt_rand(6, 72));
        $s->premier_contact_le = $contact;
        $s->statut = 'en_cours';
        $s->suivis()->create(['type' => 'contact', 'contenu' => 'Premier échange téléphonique avec la personne.', 'created_at' => $contact, 'visible_declarant' => true]);

        if ($age > 5 && mt_rand(0, 9) < 7) {
            $pec = $contact->copy()->addDays(mt_rand(1, 6));
            $domaine = $s->besoins[0] ?? ['medical', 'juridique', 'psychologique'][mt_rand(0, 2)];
            $domaine = array_key_exists($domaine, Signalement::DOMAINES) ? $domaine : 'psychologique';
            $s->pris_en_charge_le = $pec;
            $s->statut = 'pris_en_charge';
            $s->suivis()->create(['type' => 'prise_en_charge', 'domaine' => $domaine, 'contenu' => 'Accompagnement engagé.', 'created_at' => $pec, 'visible_declarant' => true]);

            if ($age > 45 && mt_rand(0, 9) < 6) {
                $s->clos_le = $pec->copy()->addDays(mt_rand(10, 40));
                $s->statut = 'clos';
                $s->suivis()->create(['type' => 'cloture', 'contenu' => 'Situation stabilisée, dossier clos.', 'created_at' => $s->clos_le, 'visible_declarant' => true]);
            }
        }
        $s->save();
    }
}
