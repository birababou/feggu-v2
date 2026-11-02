<?php

namespace Tests\Feature;

use App\Models\Signalement;
use Tests\AvecReferentiels;
use Tests\TestCase;

class CanauxTest extends TestCase
{
    use AvecReferentiels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReferentiels();
        config(['feggu.webhook_token' => 'jeton-test']);
    }

    private function sms(string $de, string $texte)
    {
        return $this->get('/webhooks/sms?token=jeton-test&id=1&FROM='.urlencode($de).'&MESSAGE='.urlencode($texte));
    }

    private function ussd(string $session, string $numero, string $cumul)
    {
        return $this->post('/webhooks/ussd?token=jeton-test', ['sessionId' => $session, 'phoneNumber' => $numero, 'text' => $cumul]);
    }

    public function test_un_webhook_sans_le_bon_jeton_est_refuse(): void
    {
        $this->get('/webhooks/sms?FROM=221770000000&MESSAGE=test')->assertForbidden();
        $this->get('/webhooks/sms?token=faux&FROM=221770000000&MESSAGE=test')->assertForbidden();
        $this->assertSame(0, Signalement::count());
    }

    public function test_sms_libre_au_format_v1_cree_un_signalement_localise(): void
    {
        $this->antenne('DS', 40);
        $this->sms('221771112233', 'FEGGU MBOUR ma voisine est battue par son mari')->assertOk();

        $s = Signalement::firstOrFail();
        $this->assertSame('sms', $s->canal);
        $this->assertSame(40, $s->departement_id);
        $this->assertSame(13, $s->region_id);
        $this->assertSame(2, $s->categorie_id, 'Catégorie devinée : violence physique');
        $this->assertNotNull($s->structure_id);
    }

    public function test_un_second_sms_complete_le_meme_dossier(): void
    {
        $this->sms('221771112233', 'FEGGU MBOUR ma voisine est battue');
        $this->sms('221771112233', 'elle a deux enfants et elle a peur');
        $this->assertSame(1, Signalement::count());
        $this->assertStringContainsString('deux enfants', Signalement::first()->recit);
    }

    public function test_sos_declenche_une_alerte_critique_a_toute_etape(): void
    {
        $this->sms('221770000001', '1');                // accueil → choix langue
        $this->sms('221770000001', 'sos aidez moi');

        $s = Signalement::firstOrFail();
        $this->assertSame('critique', $s->urgence);
        $this->assertTrue($s->danger_immediat);
    }

    public function test_parcours_ussd_complet_en_wolof(): void
    {
        $this->antenne('AJS', 40);
        $session = 'abc123';
        $reponses = [];
        foreach (['', '2', '2*1', '2*1*1', '2*1*1*2', '2*1*1*2*7', '2*1*1*2*7*2', '2*1*1*2*7*2*Mbuur', '2*1*1*2*7*2*Mbuur*0', '2*1*1*2*7*2*Mbuur*0*1'] as $cumul) {
            $reponses[] = $this->ussd($session, '221775556677', $cumul)->assertOk()->getContent();
        }

        foreach ($reponses as $r) {
            $this->assertLessThanOrEqual(186, mb_strlen($r), "Écran USSD trop long : $r");
        }
        $this->assertStringStartsWith('CON ', $reponses[0]);
        $this->assertStringStartsWith('END ', end($reponses));
        $this->assertStringContainsString('Jërëjëf', end($reponses));

        $s = Signalement::firstOrFail();
        $this->assertSame('ussd', $s->canal);
        $this->assertSame('wo', $s->langue);
        $this->assertSame(7, $s->categorie_id);
        $this->assertSame('12-17', $s->victime_tranche_age);
        $this->assertSame(40, $s->departement_id, '« Mbuur » reconnu comme Mbour');
        $this->assertTrue($s->consentement_contact);
        $this->assertSame('221775556677', $s->contact_telephone);
    }

    public function test_danger_en_ussd_cree_l_alerte_avant_la_fin_des_questions(): void
    {
        foreach (['', '1', '1*1', '1*1*1', '1*1*1*1'] as $cumul) {
            $r = $this->ussd('s2', '221770000002', $cumul)->getContent();
        }
        $this->assertSame(1, Signalement::count());
        $this->assertSame('critique', Signalement::first()->urgence);
        $this->assertLessThanOrEqual(186, mb_strlen($r));
    }

    public function test_whatsapp_format_simple(): void
    {
        $this->postJson('/webhooks/whatsapp?token=jeton-test', ['from' => '221778889900', 'text' => 'Ziguinchor une fille va être excisée demain'])
            ->assertOk()->assertJsonPath('fin', false)->assertJsonPath('reponse', fn ($r) => str_contains($r, 'FG-'));
        $s = Signalement::firstOrFail();
        $this->assertSame('whatsapp', $s->canal);
        $this->assertSame(6, $s->categorie_id);
        $this->assertSame(45, $s->departement_id);
    }

    public function test_whatsapp_format_meta(): void
    {
        $this->postJson('/webhooks/whatsapp?token=jeton-test', ['entry' => [['changes' => [['value' => ['messages' => [
            ['from' => '221778889901', 'type' => 'text', 'text' => ['body' => 'Bonjour']],
        ]]]]]]])->assertOk()->assertJson(['traites' => 1]);
        $this->assertSame(0, Signalement::count(), 'Un simple bonjour ouvre le menu, sans créer de signalement');
    }

    public function test_appel_manque_demande_un_rappel(): void
    {
        $this->postJson('/webhooks/voix/appel-manque?token=jeton-test', ['from' => '221771234567'])->assertOk();
        $s = Signalement::firstOrFail();
        $this->assertSame('appel_manque', $s->canal);
        $this->assertTrue($s->consentement_contact);
        $this->assertSame('221771234567', $s->contact_telephone);
    }

    public function test_message_vocal_sans_transcription_cree_quand_meme_une_alerte(): void
    {
        $this->postJson('/webhooks/voix/message?token=jeton-test', ['from' => '221771234568', 'recording_url' => 'https://exemple.sn/audio.ogg'])->assertOk();
        $s = Signalement::firstOrFail();
        $this->assertSame('voix', $s->canal);
    }

    public function test_le_chatbot_web_repond(): void
    {
        $this->postJson('/parler/message', ['texte' => ''])->assertOk()->assertJsonFragment(['fin' => false])
            ->assertJsonPath('reponse', fn ($r) => str_contains($r, 'Wolof'));
    }

    public function test_les_anciennes_adresses_du_21211_restent_actives(): void
    {
        $this->get('/alert-info?id=1&FROM=221770000001&MESSAGE=test')->assertForbidden();
        $this->get('/alert-info?token=jeton-test&id=1&FROM=221770000001&MESSAGE=MBOUR%20femme%20battue%20ce%20soir')->assertOk();
        $this->get('/index.php?p=alert-info&token=jeton-test&id=2&FROM=221770000002&MESSAGE=SOS')->assertOk();
        $this->get('/?p=alert-info&token=jeton-test&id=3&FROM=221770000003&MESSAGE=SOS%20Thies')->assertOk();
        $this->assertSame(3, Signalement::count());

        config(['feggu.webhook_ips' => '127.0.0.1']);
        $this->get('/alert-info?id=4&FROM=221770000004&MESSAGE=SOS')->assertOk();
        $this->assertSame(4, Signalement::count());
    }

    public function test_ussd_comme_entree_8_d_un_menu_existant(): void
    {
        // La plateforme #2001# transmet la session avec le texte cumulé, qui commence par le choix « 8 ».
        $r = $this->postJson('/webhooks/ussd?token=jeton-test', ['session_id' => 'm1', 'msisdn' => '221770001122', 'text' => '8']);
        $r->assertOk()->assertJson(['fin' => false]);
        $this->assertStringContainsString('Wolof', $r->json('reponse'));

        $r = $this->postJson('/webhooks/ussd?token=jeton-test', ['session_id' => 'm1', 'msisdn' => '221770001122', 'text' => '8*1']);
        $this->assertStringContainsString('Signaler', $r->json('reponse'));
    }
}
