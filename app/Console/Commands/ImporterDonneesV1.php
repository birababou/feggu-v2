<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\Ref\Departement;
use App\Models\Signalement;
use App\Models\Structure;
use App\Models\User;
use App\Services\Signalements;
use App\Services\Triage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reprise des données de FEGGU v1 (comptes, cas collectés, alertes SMS, suivis).
 *
 * À lancer SUR LE SERVEUR : la commande lit l'ancienne base directement (connexion « v1 »),
 * aucune donnée de victime ne transite ailleurs. Les récits et numéros sont chiffrés à l'écriture.
 * L'ethnie et la nationalité de la v1 ne sont pas reprises (minimisation des données).
 */
class ImporterDonneesV1 extends Command
{
    protected $signature = 'feggu:import-v1 {--simulation : affiche ce qui serait importé sans rien écrire} {--ouverts : garde les anciens cas ouverts au lieu de les clore}';

    protected $description = 'Importe les comptes, cas, alertes et suivis de la base FEGGU v1';

    private const TRANCHES = [[0, 11, '0-11'], [12, 17, '12-17'], [18, 24, '18-24'], [25, 49, '25-49'], [50, 200, '50+']];

    private const SUIVI_DOMAINES = [
        'mediation' => 'mediation', 'reconciliation' => 'mediation', 'autres_pc' => 'psychologique', 'commentaire_pc' => 'psychologique',
        'police_g' => 'securite', 'aj' => 'juridique', 'autres_pj' => 'juridique', 'commentaire_pj' => 'juridique',
        'soins' => 'medical', 'hospi' => 'medical', 'prevention' => 'medical', 'autres_pm' => 'medical', 'commentaire_pm' => 'medical',
        'appui_financier' => 'economique', 'dedomagement' => 'economique', 'autres_pe' => 'economique', 'commentaire_pe' => 'economique',
    ];

    public function handle(Triage $triage, Signalements $service): int
    {
        $v1 = DB::connection('v1');
        try {
            $v1->getPdo();
        } catch (\Throwable $e) {
            $this->error('Connexion à la base v1 impossible : renseignez V1_DB_* dans .env. ('.$e->getMessage().')');

            return self::FAILURE;
        }

        $sim = (bool) $this->option('simulation');
        $besoinsCodes = DB::table('besoins')->pluck('code', 'id');
        $categorieDeType = DB::table('types_violence')->pluck('categorie_id', 'id');

        // 1. Comptes
        $comptes = 0;
        foreach ($v1->table('feg_users')->get() as $u) {
            $profil = $v1->table('feg_uProfil')->where('user', $u->id)->first();
            $email = filter_var($u->mail, FILTER_VALIDATE_EMAIL) ? strtolower($u->mail) : strtolower($u->login).'@v1.feggu.org';
            if ($sim || User::where('email', $email)->exists()) {
                $comptes++;

                continue;
            }
            $structureId = null;
            $role = 'agent';
            if ($profil && (int) $profil->profil === 1) {
                $role = 'admin';
            } elseif ($profil && $profil->departement && ($org = Organisation::where('v1_id', $profil->profil)->first())) {
                $structureId = Structure::firstOrCreate(
                    ['organisation_id' => $org->id, 'departement_id' => $profil->departement],
                    ['nom' => ($org->sigle ?: $org->nom).' '.Departement::find($profil->departement)?->nom]
                )->id;
            }
            User::create([
                'name' => trim($u->prenom.' '.$u->nom),
                'email' => $email,
                'password' => bin2hex(random_bytes(24)),           // inutilisable : la connexion passe par l'ancien mot de passe
                'legacy_md5' => strlen((string) $u->mdp) === 32 ? strtolower($u->mdp) : null,
                'telephone' => $u->tel ? (string) $u->tel : null,
                'role' => $role,
                'structure_id' => $structureId,
                'actif' => (bool) $u->actif,
            ]);
            $comptes++;
        }
        $this->info("Comptes : $comptes");

        // 2. Cas collectés
        $cas = 0;
        foreach ($v1->table('feg_collecte')->orderBy('id')->get() as $c) {
            $cas++;
            if ($sim || Signalement::where('reference', 'V1-C'.$c->id)->exists()) {
                continue;
            }
            $types = $this->liste($c->typeviol);
            $besoins = collect($this->liste($c->besoin_exprime))->map(fn ($id) => $besoinsCodes[$id] ?? null)->filter()->values()->all();
            $donnees = [
                'types' => $types,
                'categorie_id' => $categorieDeType[$types[0] ?? 0] ?? null,
                'victime_tranche_age' => $this->tranche($c->age),
                'besoins' => $besoins,
            ];
            $tri = $triage->evaluer($donnees);
            $structureId = Structure::where('departement_id', $c->departement)->first()?->id;
            $date = Carbon::parse($c->date);

            $s = Signalement::create($donnees + [
                'reference' => 'V1-C'.$c->id,
                'canal' => 'relais',
                'statut' => $this->option('ouverts') ? ($structureId ? 'oriente' : 'nouveau') : 'clos',
                'urgence' => $tri['urgence'],
                'score_urgence' => $tri['score'],
                'raisons_urgence' => $tri['raisons'],
                'declarant' => match ((int) $c->source_information) { 1 => 'victime', 2 => 'temoin', default => 'relais' },
                'region_id' => $c->region ?: null,
                'departement_id' => $c->departement ?: null,
                'commune_id' => $c->commune ?: null,
                'localite_id' => $c->localite ?: null,
                'zone' => in_array(strtolower((string) $c->zone_production), ['rural', 'urbain'], true) ? strtolower($c->zone_production) : null,
                'date_faits' => $c->date_viol,
                'victime_sexe' => in_array(strtoupper((string) $c->sexe), ['F', 'M'], true) ? strtoupper($c->sexe) : null,
                'victime_handicap' => $c->handicap === null ? null : in_array(strtolower($c->handicap), ['oui', '1'], true),
                'auteur_connu' => $c->agresseur_presume === null ? null : in_array(strtolower($c->agresseur_presume), ['oui', '1'], true),
                'lien_auteur_id' => $c->lien_auteur_victime ?: null,
                'contact_telephone' => $c->tel_victime ?: null,     // gardé chiffré, mais pas de consentement connu : jamais utilisé pour un SMS
                'consentement_contact' => false,
                'consentement_partage' => true,
                'structure_id' => $structureId,
                'clos_le' => $this->option('ouverts') ? null : $date,
            ]);
            $s->timestamps = false;
            $s->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
            $s->suivis()->create(['type' => 'note', 'contenu' => 'Cas repris de FEGGU v1 (collecte n° '.$c->id.').', 'created_at' => $date]);

            if ($suivi = $v1->table('feg_suivi_cas')->where('collecte', $c->id)->first()) {
                foreach (self::SUIVI_DOMAINES as $champ => $domaine) {
                    if (filled($suivi->{$champ} ?? null)) {
                        $s->suivis()->create(['type' => 'prise_en_charge', 'domaine' => $domaine, 'contenu' => $suivi->{$champ}, 'created_at' => $date]);
                    }
                }
                $s->forceFill(['pris_en_charge_le' => $date, 'premier_contact_le' => $date])->save();
            }
        }
        $this->info("Cas collectés : $cas");

        // 3. Alertes SMS du 21211
        $alertes = 0;
        foreach ($v1->table('feg_alert')->orderBy('id')->get() as $a) {
            $alertes++;
            if ($sim || Signalement::where('reference', 'V1-A'.$a->id)->exists()) {
                continue;
            }
            $tri = $triage->evaluer(['recit' => $a->message]);
            $date = Carbon::parse($a->date);
            $s = Signalement::create([
                'reference' => 'V1-A'.$a->id,
                'canal' => 'sms',
                'statut' => $this->option('ouverts') ? 'nouveau' : 'clos',
                'urgence' => $tri['urgence'],
                'score_urgence' => $tri['score'],
                'raisons_urgence' => $tri['raisons'],
                'categorie_id' => $tri['categorie_id'],
                'departement_id' => $a->departement ?: null,
                'region_id' => $a->departement ? Departement::find($a->departement)?->region_id : null,
                'recit' => stripslashes((string) $a->message),
                'contact_telephone' => $a->tel ?: null,
                'consentement_contact' => false,
                'consentement_partage' => true,
                'clos_le' => $this->option('ouverts') ? null : $date,
            ]);
            $s->timestamps = false;
            $s->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
            $s->suivis()->create(['type' => 'note', 'contenu' => 'Alerte SMS reprise de FEGGU v1'.((int) $a->etat === 2 ? ' (confirmée).' : '.'), 'created_at' => $date]);
        }
        $this->info("Alertes SMS : $alertes");

        if ($sim) {
            $this->warn('Simulation : rien n\'a été écrit.');
        }

        return self::SUCCESS;
    }

    /** Les listes de la v1 sont stockées soit sérialisées en PHP, soit en JSON, soit séparées par des virgules. */
    private function liste(?string $valeur): array
    {
        if (blank($valeur)) {
            return [];
        }
        $v = @unserialize($valeur, ['allowed_classes' => false]);
        if (! is_array($v)) {
            $v = json_decode($valeur, true);
        }
        if (! is_array($v)) {
            $v = preg_split('/[,;|\s]+/', $valeur);
        }

        return array_values(array_filter(array_map('intval', $v)));
    }

    private function tranche($age): ?string
    {
        if ($age === null || $age === '') {
            return null;
        }
        foreach (self::TRANCHES as [$min, $max, $t]) {
            if ((int) $age >= $min && (int) $age <= $max) {
                return $t;
            }
        }

        return null;
    }
}
