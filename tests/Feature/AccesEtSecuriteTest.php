<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Signalement;
use App\Models\User;
use App\Services\Signalements;
use App\Services\Totp;
use Tests\AvecReferentiels;
use Tests\TestCase;

class AccesEtSecuriteTest extends TestCase
{
    use AvecReferentiels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReferentiels();
        config(['feggu.deux_facteurs_obligatoire' => []]);
    }

    private function signalementA(int $departement, string $sigle): Signalement
    {
        $st = $this->antenne($sigle, $departement);

        $s = app(Signalements::class)->creer(['categorie_id' => 2, 'departement_id' => $departement, 'recit' => 'test'], 'web');
        $s->update(['structure_id' => $st->id]);

        return $s;
    }

    public function test_un_agent_ne_voit_que_les_dossiers_de_sa_structure(): void
    {
        $mbour = $this->signalementA(40, 'DS');
        $tamba = $this->signalementA(39, 'AJS');
        $agentMbour = User::where('email', 'ds40@test.sn')->first();

        $this->actingAs($agentMbour)->get("/espace/signalements/{$mbour->id}")->assertOk();
        $this->actingAs($agentMbour)->get("/espace/signalements/{$tamba->id}")->assertForbidden();
        $this->actingAs($agentMbour)->post("/espace/signalements/{$tamba->id}/suivi", ['type' => 'note'])->assertForbidden();
    }

    public function test_un_superviseur_ne_voit_que_sa_region(): void
    {
        $mbour = $this->signalementA(40, 'DS');   // Thiès
        $tamba = $this->signalementA(39, 'AJS');  // Tambacounda
        $sup = User::create(['name' => 'Sup', 'email' => 'sup@test.sn', 'password' => 'Secret-123456', 'role' => 'superviseur', 'region_id' => 13]);

        $this->actingAs($sup)->get("/espace/signalements/{$mbour->id}")->assertOk();
        $this->actingAs($sup)->get("/espace/signalements/{$tamba->id}")->assertForbidden();
    }

    public function test_les_pages_d_administration_sont_reservees(): void
    {
        $this->signalementA(40, 'DS');
        $agent = User::where('role', 'agent')->first();

        $this->get('/espace')->assertRedirect('/espace/connexion');
        $this->actingAs($agent)->get('/espace/utilisateurs')->assertForbidden();
        $this->actingAs($agent)->get('/espace/journal')->assertForbidden();
        $this->actingAs($agent)->get('/espace/simulateur')->assertForbidden();
    }

    public function test_chaque_consultation_est_journalisee(): void
    {
        $s = $this->signalementA(40, 'DS');
        $agent = User::where('email', 'ds40@test.sn')->first();
        $this->actingAs($agent)->get("/espace/signalements/{$s->id}");

        $this->assertTrue(AuditLog::where('action', 'consultation')->where('cible_id', $s->id)->where('user_id', $agent->id)->exists());
    }

    public function test_un_compte_v1_en_md5_est_converti_a_la_premiere_connexion(): void
    {
        $u = User::create(['name' => 'Ancien', 'email' => 'ancien@test.sn', 'password' => bin2hex(random_bytes(16)), 'role' => 'admin', 'legacy_md5' => md5('ancienMotDePasse')]);

        $this->post('/espace/connexion', ['email' => 'ancien@test.sn', 'password' => 'ancienMotDePasse'])->assertRedirect('/espace');

        $u->refresh();
        $this->assertNull($u->legacy_md5);
        $this->assertStringStartsWith('$2y$', $u->password);
    }

    public function test_mauvais_mot_de_passe_refuse_et_journalise(): void
    {
        User::create(['name' => 'A', 'email' => 'a@test.sn', 'password' => 'Secret-123456', 'role' => 'admin']);
        $this->post('/espace/connexion', ['email' => 'a@test.sn', 'password' => 'faux'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'connexion_echec')->exists());
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        User::create(['name' => 'B', 'email' => 'b@test.sn', 'password' => 'Secret-123456', 'role' => 'agent', 'actif' => false]);
        $this->post('/espace/connexion', ['email' => 'b@test.sn', 'password' => 'Secret-123456'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_double_authentification_obligatoire_pour_les_administrateurs(): void
    {
        config(['feggu.deux_facteurs_obligatoire' => ['admin']]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.sn', 'password' => 'Secret-123456', 'role' => 'admin']);

        $this->actingAs($admin)->get('/espace')->assertRedirect('/espace/securite/activer');

        $this->actingAs($admin)->get('/espace/securite/activer')->assertOk();
        $secret = session('2fa_secret_temp');
        $code = Totp::code($secret, intdiv(time(), 30));
        $this->actingAs($admin)->post('/espace/securite/activer', ['code' => $code])->assertRedirect('/espace');

        $this->assertTrue($admin->fresh()->aDeuxFacteurs());
        $this->actingAs($admin)->get('/espace')->assertOk();
    }

    public function test_code_totp_conforme_a_la_rfc_6238(): void
    {
        // Vecteur de test de la RFC 6238 (clé « 12345678901234567890 », T = 59 s → 94287082, 6 derniers chiffres).
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::code($secret, 1));
        $this->assertTrue(Totp::verifier($secret, '287082', 0, 59));
        $this->assertFalse(Totp::verifier($secret, '000000', 1, 59));
    }

    public function test_un_agent_peut_suivre_et_clore_un_dossier(): void
    {
        $s = $this->signalementA(40, 'DS');
        $agent = User::where('email', 'ds40@test.sn')->first();

        $this->actingAs($agent)->post("/espace/signalements/{$s->id}/suivi", ['type' => 'prise_en_charge', 'domaine' => 'medical', 'contenu' => 'Consultation faite'])->assertRedirect();
        $this->assertSame('pris_en_charge', $s->fresh()->statut);
        $this->assertNotNull($s->fresh()->pris_en_charge_le);

        $this->actingAs($agent)->post("/espace/signalements/{$s->id}/suivi", ['type' => 'cloture'])->assertRedirect();
        $this->assertSame('clos', $s->fresh()->statut);
    }

    public function test_pas_de_sms_si_la_personne_n_a_pas_accepte_d_etre_contactee(): void
    {
        $s = $this->signalementA(40, 'DS');
        $agent = User::where('email', 'ds40@test.sn')->first();
        $this->actingAs($agent)->post("/espace/signalements/{$s->id}/contacter")->assertStatus(422);
    }

    public function test_un_admin_cree_une_antenne_qui_recoit_ensuite_les_cas(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'adm@test.sn', 'password' => 'Secret-123456', 'role' => 'admin']);
        $ajs = \App\Models\Organisation::where('sigle', 'AJS')->first();

        $this->actingAs($admin)->get('/espace/structures')->assertOk();
        $this->actingAs($admin)->post('/espace/structures', ['organisation_id' => $ajs->id, 'departement_id' => 40, 'services' => ['juridique']])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/espace/structures', ['organisation_id' => $ajs->id, 'departement_id' => 40])->assertSessionHasErrors('departement_id');

        $st = \App\Models\Structure::firstOrFail();
        $this->assertSame('AJS Mbour', $st->nom);
        User::create(['name' => 'Agent', 'email' => 'ag@test.sn', 'password' => 'Secret-123456', 'role' => 'agent', 'structure_id' => $st->id]);

        $s = app(Signalements::class)->creer(['categorie_id' => 4, 'departement_id' => 40, 'besoins' => ['juridique']], 'web');
        $this->assertSame($st->id, $s->structure_id);
    }

    public function test_les_listes_sont_paginees_et_filtrables(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'adm2@test.sn', 'password' => 'Secret-123456', 'role' => 'admin']);
        foreach (range(1, 25) as $i) {
            User::create(['name' => "Agent $i", 'email' => "agent$i@test.sn", 'password' => 'Secret-123456', 'role' => 'agent']);
        }

        $this->actingAs($admin)->get('/espace/utilisateurs')->assertOk()
            ->assertInertia(fn ($p) => $p->where('utilisateurs.total', 26)->count('utilisateurs.data', 20)->where('utilisateurs.last_page', 2));
        $this->actingAs($admin)->get('/espace/utilisateurs?q=agent12')->assertOk()
            ->assertInertia(fn ($p) => $p->where('utilisateurs.total', 1)->where('utilisateurs.data.0.email', 'agent12@test.sn'));
        $this->actingAs($admin)->get('/espace/utilisateurs?role=admin')->assertOk()
            ->assertInertia(fn ($p) => $p->where('utilisateurs.total', 1));

        foreach (['/espace/structures?q=AJS', '/espace/journal?q=Admin&action=connexion', '/espace/signalements?q=Mbour'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_sur_la_demo_le_compte_public_ne_peut_rien_casser(): void
    {
        config(['feggu.demo' => true]);
        $admin = User::create(['name' => 'Démo', 'email' => 'demo@test.sn', 'password' => 'Secret-123456', 'role' => 'admin']);
        $avant = User::count();

        $this->actingAs($admin)->from('/espace/utilisateurs')->post('/espace/utilisateurs', ['name' => 'X', 'email' => 'x@test.sn', 'password' => 'Secret-123456', 'role' => 'admin'])
            ->assertRedirect('/espace/utilisateurs')->assertSessionHas('erreur');
        $this->actingAs($admin)->post("/espace/utilisateurs/{$admin->id}/basculer")->assertSessionHas('erreur');
        $this->actingAs($admin)->post('/espace/securite/activer', ['code' => '000000'])->assertSessionHas('erreur');

        $this->assertSame($avant, User::count());
        $this->assertTrue($admin->fresh()->actif);
    }
}
