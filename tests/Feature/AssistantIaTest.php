<?php

namespace Tests\Feature;

use App\Models\Signalement;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Support\Facades\Http;
use Tests\AvecReferentiels;
use Tests\TestCase;

class AssistantIaTest extends TestCase
{
    use AvecReferentiels;

    private array $lectures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReferentiels();
        config(['feggu.webhook_token' => 'jeton-test', 'feggu.ia.url' => 'http://ia.local/v1', 'feggu.assistant.canaux' => ['web', 'whatsapp']]);
    }

    /** Simule le modèle : chaque message lu par l'assistante reçoit la lecture suivante de la liste. */
    private function ia(array ...$lectures): void
    {
        $this->lectures = $lectures;
        Http::fake(function (RequeteHttp $r) {
            $assistante = str_contains($r['messages'][0]['content'] ?? '', 'Tu analyses un message reçu par FEGGU');
            $contenu = $assistante ? (array_shift($this->lectures) ?? ['intention' => 'autre']) : ['danger_immediat' => false, 'categorie_id' => null, 'raison' => 'RAS'];

            return Http::response(['choices' => [['message' => ['content' => json_encode($contenu, JSON_UNESCAPED_UNICODE)]]]]);
        });
    }

    private function message(string $texte): string
    {
        return $this->postJson('/parler/message', ['texte' => $texte])->assertOk()->json('reponse');
    }

    public function test_une_conversation_complete_en_francais(): void
    {
        $this->ia(['langue' => 'fr', 'intention' => 'signaler', 'declarant' => 'victime', 'categorie_id' => 2, 'lieu' => 'Mbour', 'danger_immediat' => null]);

        $this->assertStringContainsString("l'assistante FEGGU", $this->message(''));

        $r = $this->message('Mon mari me frappe depuis des mois, hier il m\'a blessée au bras. J\'habite à Mbour.');
        $this->assertStringContainsString('pas de votre faute', $r);
        $this->assertMatchesRegularExpression('/Code de suivi : FG-[A-Z]{4}-\d{4}/', $r);
        $this->assertStringContainsString('Êtes-vous en danger', $r); // déjà connus : qui, lieu, type → seul le danger est demandé

        $this->assertStringContainsString('Âge de la victime', $this->message('Non'));
        $this->assertStringContainsString('vous rappeler', $this->message('32 ans'));
        $r = $this->message('77 456 78 90');
        $this->assertStringContainsString("C'est noté", $r);
        $this->assertStringContainsString('Merci pour votre confiance', $this->message('Merci, au revoir'));

        $s = Signalement::sole();
        $this->assertSame(['web', 2, 'Mbour', 'victime', '18+', false, true, '774567890'], [
            $s->canal, $s->categorie_id, $s->departement?->nom, $s->declarant, $s->victime_tranche_age, $s->danger_immediat, $s->consentement_contact, $s->contact_telephone,
        ]);
        $this->assertSame("Mon mari me frappe depuis des mois, hier il m'a blessée au bras. J'habite à Mbour.", $s->recit); // les mots de la personne
    }

    public function test_en_wolof_les_regles_reperent_le_danger_que_l_ia_rate(): void
    {
        // Lecture réelle de qwen2.5:3b : danger non vu, lieu « non spécifié ».
        $this->ia(['langue' => 'wo', 'intention' => 'signaler', 'declarant' => 'victime', 'categorie_id' => 2, 'lieu' => 'ville non spécifiée', 'danger_immediat' => null]);

        $r = $this->postJson('/webhooks/whatsapp?token=jeton-test', ['from' => '221770000002', 'text' => 'Sama jëkkër dafa may dóor, mu ngi ci kër gi léegi ak paaka'])->json('reponse');

        $this->assertStringContainsString('Sa code', $r);
        $this->assertStringContainsString('Sàndarmëri 800 00 20 20', $r); // numéros d'urgence en wolof, ajoutés par le serveur
        $this->assertStringContainsString('Ci ban dëkk', $r); // le lieu n'est pas connu : on le demande
        $s = Signalement::sole();
        $this->assertSame(['critique', 'wo', true], [$s->urgence, $s->langue, $s->danger_immediat]);
        $this->assertNull($s->contact_telephone); // pas de rappel sans accord
    }

    public function test_une_salutation_ou_une_question_ne_cree_pas_de_dossier(): void
    {
        $this->ia(['langue' => 'fr', 'intention' => 'information', 'sujet' => 'droits', 'categorie_id' => 6]); // « Bonjour » n'attend pas l'IA

        $this->message('');
        $this->assertStringContainsString('Je vous écoute', $this->message('Bonjour'));
        $r = $this->message('Est-ce que l\'excision est interdite au Sénégal ?');
        $this->assertStringContainsString('punis par la loi', $r);
        $this->assertSame(0, Signalement::count());
    }

    public function test_si_l_ia_ne_repond_pas_le_message_n_est_jamais_perdu(): void
    {
        Http::fake(['ia.local/*' => Http::response('panne', 500)]);

        $this->message('');
        $r = $this->message('Ma voisine est battue chaque nuit à Thiès');

        $this->assertMatchesRegularExpression('/FG-[A-Z]{4}-\d{4}/', $r);
        $s = Signalement::sole();
        $this->assertSame('Ma voisine est battue chaque nuit à Thiès', $s->recit);
        $this->assertSame('Thiès', $s->departement?->nom);
    }

    public function test_le_suivi_par_code_ne_passe_pas_par_l_ia(): void
    {
        $this->ia();
        $s = app(\App\Services\Signalements::class)->creer(['recit' => 'test', 'departement_id' => 40], 'sms');

        $this->message('');
        $this->assertStringContainsString($s->reference, $this->message('où en est '.$s->reference.' ?'));
        Http::assertNotSent(fn (RequeteHttp $r) => str_contains($r['messages'][0]['content'] ?? '', 'Tu analyses un message reçu par FEGGU'));
    }

    public function test_l_ussd_n_utilise_jamais_l_assistante(): void
    {
        config(['feggu.assistant.canaux' => ['web', 'whatsapp', 'ussd']]);
        $this->ia();

        $this->post('/webhooks/ussd?token=jeton-test', ['sessionId' => 's1', 'phoneNumber' => '221770000001', 'text' => ''])->assertOk();

        Http::assertNothingSent();
    }

    public function test_un_temoin_en_wolof_avec_l_age_dans_le_recit(): void
    {
        // Lecture réelle de qwen2.5:3b : il croit que c'est la victime qui écrit et ne voit pas l'âge.
        $this->ia(['langue' => 'wo', 'intention' => 'signaler', 'declarant' => 'victime', 'categorie_id' => 2, 'lieu' => 'Kolda', 'age_victime' => null]);

        $this->postJson('/webhooks/whatsapp?token=jeton-test', ['from' => '221770000003', 'text' => 'Sama doom bu jigéen bu am 13 at, dañu koy séyal ci doole ci Kolda']);

        $s = Signalement::sole();
        $this->assertSame([7, 'temoin', '12-17', 'Kolda', 'haute'], [$s->categorie_id, $s->declarant, $s->victime_tranche_age, $s->departement?->nom, $s->urgence]);
    }
}
