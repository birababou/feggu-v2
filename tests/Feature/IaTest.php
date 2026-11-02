<?php

namespace Tests\Feature;

use App\Models\Signalement;
use Illuminate\Support\Facades\Http;
use Tests\AvecReferentiels;
use Tests\TestCase;

class IaTest extends TestCase
{
    use AvecReferentiels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReferentiels();
        config(['feggu.webhook_token' => 'jeton-test', 'feggu.ia.url' => 'http://ia.local/v1']);
    }

    private function reponseIa(array $avis): void
    {
        Http::fake(['ia.local/*' => Http::response(['choices' => [['message' => ['content' => json_encode($avis)]]]])]);
    }

    public function test_l_ia_releve_l_urgence_apres_la_reponse_sms(): void
    {
        $this->reponseIa(['danger_immediat' => true, 'categorie_id' => 2, 'raison' => 'Le mari est présent et menace']);

        $this->get('/webhooks/sms?token=jeton-test&FROM=221770000001&MESSAGE='.urlencode('Sama jëkkër mi ngi ci kër gi, dafa bëgg a door'))->assertOk();

        $s = Signalement::firstOrFail();
        $this->assertSame('haute', $s->urgence);
        $this->assertContains('IA : Le mari est présent et menace', $s->raisons_urgence);
        $this->assertTrue($s->suivis()->where('type', 'triage')->get()->contains(fn ($x) => ($x->meta['ia'] ?? false) === true));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'chat/completions') && $r['messages'][1]['content'] !== '');
    }

    public function test_l_ia_ne_fait_jamais_baisser_l_urgence(): void
    {
        $this->reponseIa(['danger_immediat' => false, 'categorie_id' => 3, 'raison' => 'Pas de danger apparent']);
        $this->get('/webhooks/sms?token=jeton-test&FROM=221770000002&MESSAGE='.urlencode('SOS il est la avec un couteau'))->assertOk();

        $this->assertSame('critique', Signalement::firstOrFail()->urgence);
    }

    public function test_si_l_ia_est_indisponible_rien_ne_casse(): void
    {
        Http::fake(['ia.local/*' => Http::response('erreur', 500)]);
        $this->get('/webhooks/sms?token=jeton-test&FROM=221770000003&MESSAGE='.urlencode('MBOUR ma voisine est battue'))->assertOk();

        $s = Signalement::firstOrFail();
        $this->assertSame(40, $s->departement_id);
        $this->assertSame(1, $s->suivis()->where('type', 'triage')->count());
    }

    public function test_l_ussd_n_attend_jamais_l_ia(): void
    {
        $this->reponseIa(['danger_immediat' => false]);
        foreach (['', '1', '1*1', '1*1*1', '1*1*1*2', '1*1*1*2*2', '1*1*1*2*2*3', '1*1*1*2*2*3*Mbour', '1*1*1*2*2*3*Mbour*il me frappe', '1*1*1*2*2*3*Mbour*il me frappe*2'] as $cumul) {
            $this->post('/webhooks/ussd?token=jeton-test', ['sessionId' => 'u1', 'phoneNumber' => '221770000004', 'text' => $cumul])->assertOk();
        }
        $this->assertSame(1, Signalement::count());
    }

    public function test_l_ia_est_visible_et_testable_depuis_l_espace(): void
    {
        $sup = \App\Models\User::create(['name' => 'Sup', 'email' => 'sup-ia@test.sn', 'password' => 'Secret-123456', 'role' => 'superviseur', 'region_id' => 13]);
        $this->reponseIa(['danger_immediat' => true, 'categorie_id' => 2, 'raison' => 'Violence en cours au domicile']);

        $this->actingAs($sup)->get('/espace')->assertOk()
            ->assertInertia(fn ($p) => $p->where('ia.actif', true)->where('peut_tester_ia', true));

        $this->actingAs($sup)->from('/espace')->post('/espace/ia/tester')->assertRedirect('/espace')
            ->assertSessionHas('succes', fn ($m) => str_contains($m, 'danger immédiat détecté'));
    }

    public function test_un_agent_peut_relancer_l_analyse_d_un_recit(): void
    {
        $s = app(\App\Services\Signalements::class)->creer(['recit' => 'Mon mari me frappe en ce moment', 'departement_id' => 40], 'web');
        $sup = \App\Models\User::create(['name' => 'Sup', 'email' => 'sup-ia2@test.sn', 'password' => 'Secret-123456', 'role' => 'superviseur', 'region_id' => $s->region_id]);
        $this->reponseIa(['danger_immediat' => true, 'categorie_id' => 2, 'raison' => 'Violence en cours']);

        $this->actingAs($sup)->post("/espace/signalements/{$s->id}/analyser")->assertSessionHas('succes');
        $ia = $s->suivis()->where('type', 'triage')->get()->last(fn ($x) => $x->meta['ia'] ?? false);
        $this->assertTrue($ia->meta['danger_immediat']);

        config(['feggu.ia.url' => null]);
        $this->actingAs($sup)->post("/espace/signalements/{$s->id}/analyser")->assertSessionHas('erreur');
    }
}
