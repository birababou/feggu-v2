<?php

namespace Tests\Unit;

use App\Services\Localisateur;
use App\Services\Triage;
use Tests\AvecReferentiels;
use Tests\TestCase;

class TriageEtLocalisationTest extends TestCase
{
    use AvecReferentiels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReferentiels();
    }

    public function test_un_recit_en_wolof_signalant_un_danger_present_est_prioritaire(): void
    {
        $r = app(Triage::class)->evaluer(['recit' => 'Sama jëkkër dafa may dóor léegi, mi ngi fi ak paaka']);
        $this->assertContains($r['urgence'], ['critique', 'haute']);
        $this->assertSame(2, $r['categorie_id']);
    }

    public function test_violence_verbale_n_est_pas_classee_comme_un_viol(): void
    {
        $r = app(Triage::class)->evaluer(['types' => [42]]); // « Violence verbale »
        $this->assertSame('basse', $r['urgence']);
    }

    public function test_une_victime_mineure_et_un_viol_donnent_une_urgence_haute_au_moins(): void
    {
        $r = app(Triage::class)->evaluer(['types' => [4], 'victime_tranche_age' => '12-17']);
        $this->assertContains($r['urgence'], ['critique', 'haute']);
        $this->assertContains('Victime mineure', $r['raisons']);
    }

    public function test_localisation_des_noms_de_lieux(): void
    {
        $l = app(Localisateur::class);
        $this->assertSame(40, $l->trouver('Mbuur')['departement_id']);
        $this->assertSame(39, $l->trouver('à TAMBA près du marché')['departement_id']);
        $saly = $l->trouver('ça se passe à Saly Portudal');
        $this->assertSame(40, $saly['departement_id']);
        $this->assertNotNull($saly['commune_id']);
        $this->assertNull($l->trouver('je ne sais pas')['departement_id']);
    }
}
