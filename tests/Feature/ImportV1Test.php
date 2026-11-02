<?php

namespace Tests\Feature;

use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\AvecReferentiels;
use Tests\TestCase;

class ImportV1Test extends TestCase
{
    use AvecReferentiels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReferentiels();

        // Une fausse base v1 (mêmes colonnes que l'export du serveur).
        config(['database.connections.v1' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $s = Schema::connection('v1');
        $s->create('feg_users', function (Blueprint $t) {
            $t->id(); $t->string('prenom'); $t->string('nom'); $t->string('mail')->nullable(); $t->string('login');
            $t->string('mdp'); $t->integer('tel')->nullable(); $t->integer('actif')->default(1);
        });
        $s->create('feg_uProfil', function (Blueprint $t) { $t->id(); $t->integer('user'); $t->integer('profil'); $t->integer('departement')->nullable(); });
        $s->create('feg_collecte', function (Blueprint $t) {
            $t->id();
            foreach (['region', 'departement', 'commune', 'localite', 'age', 'lien_auteur_victime', 'source_information'] as $c) { $t->integer($c)->nullable(); }
            foreach (['sexe', 'tel_victime', 'besoin_exprime', 'typeviol', 'agresseur_presume', 'zone_production', 'handicap', 'date_viol', 'ethnie'] as $c) { $t->text($c)->nullable(); }
            $t->timestamp('date')->nullable();
        });
        $s->create('feg_suivi_cas', function (Blueprint $t) {
            $t->id(); $t->integer('collecte');
            foreach (['mediation', 'reconciliation', 'autres_pc', 'commentaire_pc', 'police_g', 'aj', 'autres_pj', 'commentaire_pj', 'commentaire_pm', 'commentaire_pe', 'prevention', 'soins', 'hospi', 'deces', 'autres_pm', 'appui_financier', 'dedomagement', 'autres_pe'] as $c) { $t->text($c)->nullable(); }
        });
        $s->create('feg_alert', function (Blueprint $t) { $t->id(); $t->text('tel')->nullable(); $t->text('message')->nullable(); $t->integer('departement')->nullable(); $t->integer('etat')->default(1); $t->timestamp('date')->nullable(); });

        $v1 = DB::connection('v1');
        $v1->table('feg_users')->insert([
            ['prenom' => 'Awa', 'nom' => 'Diop', 'mail' => 'awa@exemple.sn', 'login' => 'awa', 'mdp' => md5('motdepasse'), 'tel' => 771112233, 'actif' => 1],
            ['prenom' => 'Admin', 'nom' => 'V1', 'mail' => null, 'login' => 'admin', 'mdp' => md5('x'), 'tel' => null, 'actif' => 1],
        ]);
        $v1->table('feg_uProfil')->insert([['user' => 1, 'profil' => 26, 'departement' => 40], ['user' => 2, 'profil' => 1, 'departement' => null]]);
        $v1->table('feg_collecte')->insert(['region' => 13, 'departement' => 40, 'commune' => 1, 'localite' => 1, 'age' => 15, 'sexe' => 'F',
            'tel_victime' => '770000001', 'besoin_exprime' => serialize(['2', '4']), 'typeviol' => serialize(['4']), 'ethnie' => '2', 'source_information' => 2, 'date' => '2023-05-02 10:00:00']);
        $v1->table('feg_suivi_cas')->insert(['collecte' => 1, 'aj' => 'Orientée vers la boutique de droit', 'soins' => 'Certificat médical']);
        $v1->table('feg_alert')->insert(['tel' => '221778889900', 'message' => 'MBOUR une femme battue', 'departement' => 40, 'etat' => 2, 'date' => '2024-01-10 08:00:00']);
    }

    public function test_reprise_complete_des_donnees_v1(): void
    {
        $this->artisan('feggu:import-v1')->assertSuccessful();

        $awa = User::where('email', 'awa@exemple.sn')->firstOrFail();
        $this->assertSame('agent', $awa->role);
        $this->assertSame(40, $awa->structure->departement_id);
        $this->assertSame('AJS', $awa->structure->organisation->sigle);
        $this->assertSame('admin', User::where('email', 'admin@v1.feggu.org')->value('role'));

        $cas = Signalement::where('reference', 'V1-C1')->firstOrFail();
        $this->assertSame('12-17', $cas->victime_tranche_age);
        $this->assertSame(1, $cas->categorie_id);
        $this->assertSame(['juridique', 'medical'], $cas->besoins);
        $this->assertSame('2023-05-02', $cas->created_at->toDateString());
        $this->assertSame('clos', $cas->statut);
        $this->assertFalse($cas->consentement_contact);
        $this->assertSame(2, $cas->suivis()->where('type', 'prise_en_charge')->count());

        $alerte = Signalement::where('reference', 'V1-A1')->firstOrFail();
        $this->assertSame('sms', $alerte->canal);
        $this->assertSame('MBOUR une femme battue', $alerte->recit);

        // Relancer l'import ne crée pas de doublons.
        $this->artisan('feggu:import-v1')->assertSuccessful();
        $this->assertSame(2, Signalement::count());

        // L'ancien mot de passe fonctionne une fois, puis devient bcrypt.
        $this->post('/espace/connexion', ['email' => 'awa@exemple.sn', 'password' => 'motdepasse'])->assertRedirect('/espace');
        $this->assertNull($awa->fresh()->legacy_md5);
    }
}
