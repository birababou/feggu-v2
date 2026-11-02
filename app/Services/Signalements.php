<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Ref\Departement;
use App\Models\Signalement;
use App\Services\Canaux\EnvoiSms;
use Illuminate\Support\Facades\DB;

/**
 * Création d'un signalement, quel que soit le canal : tri, orientation, notification neutre des structures.
 */
class Signalements
{
    public function __construct(
        private Triage $triage,
        private Orientation $orientation,
        private EnvoiSms $sms,
    ) {}

    public function creer(array $donnees, string $canal): Signalement
    {
        return DB::transaction(function () use ($donnees, $canal) {
            if (empty($donnees['region_id']) && ! empty($donnees['departement_id'])) {
                $donnees['region_id'] = Departement::find($donnees['departement_id'])?->region_id;
            }

            $tri = $this->triage->evaluer($donnees);

            $s = Signalement::create(array_merge($donnees, [
                'reference' => $this->nouvelleReference(),
                'canal' => $canal,
                'statut' => 'nouveau',
                'urgence' => $tri['urgence'],
                'score_urgence' => $tri['score'],
                'raisons_urgence' => $tri['raisons'],
                'categorie_id' => $donnees['categorie_id'] ?? $tri['categorie_id'],
            ]));

            $s->suivis()->create(['type' => 'creation', 'meta' => ['canal' => $canal], 'visible_declarant' => true]);
            $s->suivis()->create(['type' => 'triage', 'meta' => ['urgence' => $tri['urgence'], 'score' => $tri['score'], 'raisons' => $tri['raisons']]]);

            $this->orientation->orienterAutomatiquement($s);
            if (! $s->est_demo || config('feggu.demo')) {
                $this->notifier($s->fresh());
            }
            $this->planifierAnalyseIa($s);

            return $s->fresh();
        });
    }

    /** L'analyse IA du récit tourne après l'envoi de la réponse (SMS, USSD, page web) : elle ne retarde jamais une alerte. */
    public function planifierAnalyseIa(Signalement $s): void
    {
        if (! filled($s->recit) || ! app(\App\Services\Ia\ClientIa::class)->actif()) {
            return;
        }
        $id = $s->id;
        dispatch(fn () => app(self::class)->analyserAvecIa($id))->afterResponse();
    }

    /**
     * Complète le tri avec l'avis de l'IA : l'urgence peut monter, jamais descendre.
     * Renvoie l'avis, ou null si l'IA n'a pas répondu (les règles restent appliquées).
     */
    public function analyserAvecIa(int $id): ?array
    {
        $s = Signalement::find($id);
        if (! $s || ! ($avis = $this->triage->avisIa((string) $s->recit))) {
            return null;
        }

        $avant = $s->urgence;
        $raisons = $s->raisons_urgence ?? [];
        if ($avis['danger_immediat'] && Signalement::URGENCES[$s->urgence] < Signalement::URGENCES['critique']) {
            $s->urgence = Signalement::URGENCES[$s->urgence] >= Signalement::URGENCES['haute'] ? 'critique' : 'haute';
            $s->score_urgence = max($s->score_urgence, $s->urgence === 'critique' ? 70 : 45);
            $raisons[] = 'IA : '.($avis['raison'] ?: 'danger immédiat probable');
        }
        $s->raisons_urgence = array_values(array_unique($raisons));
        $s->categorie_id ??= $avis['categorie_id'];
        $s->save();

        $s->suivis()->create(['type' => 'triage', 'meta' => [
            'ia' => true, 'modele' => config('feggu.ia.modele'), 'urgence' => $s->urgence, 'score' => $s->score_urgence,
            'danger_immediat' => $avis['danger_immediat'], 'urgence_relevee' => $s->urgence !== $avant,
            'raisons' => [$avis['raison'] ?: 'Analyse du récit'], 'categorie_ia' => $avis['categorie_id'],
        ]]);

        if (! $s->structure_id) {
            $this->orientation->orienterAutomatiquement($s);
        }
        if ($s->urgence !== $avant && $s->urgence === 'critique' && ! $s->est_demo) {
            $this->notifier($s->fresh());
        }

        return $avis;
    }

    /** Met à jour un signalement déjà créé (ex. alerte SOS complétée ensuite par la conversation) et refait le tri. */
    public function completer(Signalement $s, array $donnees, bool $analyser = true): Signalement
    {
        $s->fill(array_filter($donnees, fn ($v) => $v !== null && $v !== []));
        $tri = $this->triage->evaluer($s->only(['types', 'categorie_id', 'danger_immediat', 'victime_tranche_age', 'periode_faits', 'recit', 'besoins']));
        // L'urgence ne baisse jamais automatiquement.
        if (Signalement::URGENCES[$tri['urgence']] > Signalement::URGENCES[$s->urgence]) {
            $s->urgence = $tri['urgence'];
            $s->score_urgence = $tri['score'];
            $s->raisons_urgence = $tri['raisons'];
        }
        $s->categorie_id ??= $tri['categorie_id'];
        $s->save();

        if (! $s->structure_id) {
            $this->orientation->orienterAutomatiquement($s);
        }
        if ($analyser && $s->wasChanged('recit')) {
            $this->planifierAnalyseIa($s);
        }

        return $s->fresh();
    }

    /**
     * Prévient les agents de la structure (et les superviseurs pour les cas critiques) par SMS.
     * Le SMS ne contient aucun détail sur la victime : seulement la référence et le niveau d'urgence.
     */
    public function notifier(Signalement $s): void
    {
        $destinataires = collect();
        if ($s->structure) {
            $destinataires = $s->structure->users()->where('actif', true)->whereNotNull('telephone')->pluck('telephone');
        }
        if ($s->urgence === 'critique') {
            $destinataires = $destinataires->merge(
                \App\Models\User::where('actif', true)->where('role', 'superviseur')->where('region_id', $s->region_id)->whereNotNull('telephone')->pluck('telephone')
            );
        }

        $texte = 'FEGGU : nouveau signalement '.$s->reference.' (urgence '.$s->urgence.'). Connectez-vous pour le traiter.';
        foreach ($destinataires->unique() as $tel) {
            $this->sms->envoyer($tel, $texte);
        }

        if ($destinataires->isNotEmpty()) {
            AuditLog::noter('notification_sms', $s, ['destinataires' => $destinataires->unique()->count()]);
        }
    }

    public function nouvelleReference(): string
    {
        $lettres = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $chiffres = '23456789';
        do {
            $ref = 'FG-';
            for ($i = 0; $i < 4; $i++) {
                $ref .= $lettres[random_int(0, strlen($lettres) - 1)];
            }
            $ref .= '-';
            for ($i = 0; $i < 4; $i++) {
                $ref .= $chiffres[random_int(0, strlen($chiffres) - 1)];
            }
        } while (Signalement::where('reference', $ref)->exists());

        return $ref;
    }
}
