<?php

namespace Tests\Feature;

use App\Models\Signalement;
use Illuminate\Support\Facades\DB;
use Tests\AvecReferentiels;
use Tests\TestCase;

class SignalementWebTest extends TestCase
{
    use AvecReferentiels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReferentiels();
    }

    private function donnees(array $surcharge = []): array
    {
        return array_merge([
            'declarant' => 'temoin',
            'danger_immediat' => true,
            'categorie_id' => 1,
            'region_id' => 13,
            'departement_id' => 40,
            'victime_tranche_age' => '12-17',
            'besoins' => ['medical'],
            'recit' => 'Une adolescente est en danger chez son oncle.',
            'consentement_contact' => true,
            'contact_telephone' => '771234567',
            'consentement_partage' => true,
        ], $surcharge);
    }

    public function test_un_signalement_critique_est_trie_oriente_et_chiffre(): void
    {
        $ds = $this->antenne('DS', 40);

        $this->post('/signaler', $this->donnees())
            ->assertRedirect('/signaler/merci')
            ->assertSessionHas('reference');

        $s = Signalement::firstOrFail();
        $this->assertSame('critique', $s->urgence);
        $this->assertSame('web', $s->canal);
        $this->assertSame($ds->id, $s->structure_id, 'Orienté vers la structure médicale du département');
        $this->assertSame('oriente', $s->statut);
        $this->assertMatchesRegularExpression('/^FG-[A-Z]{4}-\d{4}$/', $s->reference);

        // Le récit et le téléphone ne sont jamais stockés en clair.
        $brut = DB::table('signalements')->first();
        $this->assertStringNotContainsString('adolescente', $brut->recit);
        $this->assertStringNotContainsString('771234567', $brut->contact_telephone);
        $this->assertSame('Une adolescente est en danger chez son oncle.', $s->recit);
    }

    public function test_l_accord_de_transmission_est_obligatoire(): void
    {
        $this->post('/signaler', $this->donnees(['consentement_partage' => false]))->assertSessionHasErrors('consentement_partage');
        $this->assertSame(0, Signalement::count());
    }

    public function test_sans_demande_de_rappel_le_numero_n_est_pas_garde(): void
    {
        $this->post('/signaler', $this->donnees(['consentement_contact' => false, 'contact_telephone' => '771234567']));
        $this->assertNull(Signalement::first()->contact_telephone);
    }

    public function test_la_reference_n_apparait_pas_dans_l_url(): void
    {
        $r = $this->post('/signaler', $this->donnees());
        $this->assertStringNotContainsString('FG-', $r->headers->get('Location'));
    }

    public function test_le_suivi_public_montre_l_avancement_sans_le_contenu(): void
    {
        $this->post('/signaler', $this->donnees());
        $s = Signalement::first();

        $this->post('/suivi', ['reference' => strtolower($s->reference)])
            ->assertOk()
            ->assertSee($s->reference)
            ->assertDontSee('adolescente')
            ->assertDontSee('771234567');
    }

    public function test_les_pages_publiques_s_affichent(): void
    {
        foreach (['/', '/signaler', '/suivi', '/parler', '/aide', '/confidentialite'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_les_pages_sensibles_ne_sont_pas_mises_en_cache(): void
    {
        $this->get('/signaler')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $this->get('/signaler')->headers->get('Cache-Control'));
        $this->get('/')->assertHeader('Referrer-Policy', 'no-referrer');
    }
}
