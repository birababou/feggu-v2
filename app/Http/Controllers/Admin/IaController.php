<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Signalement;
use App\Models\Suivi;
use App\Services\Ia\ClientIa;
use App\Services\Signalements;
use App\Services\Triage;
use Illuminate\Http\Request;

/**
 * Rend l'IA visible et contrôlable depuis l'espace : état, essai, nouvelle analyse d'un récit.
 */
class IaController extends Controller
{
    /** État affiché sur le tableau de bord. */
    public static function etat(): array
    {
        $analyses = Suivi::query()->where('type', 'triage')->where('meta', 'like', '%"ia":true%');
        $derniere = (clone $analyses)->latest('id')->first();

        return [
            'actif' => app(ClientIa::class)->actif(),
            'modele' => app(ClientIa::class)->actif() ? config('feggu.ia.modele') : null,
            'analyses_30j' => (clone $analyses)->where('created_at', '>=', now()->subDays(30))->count(),
            'urgences_relevees_30j' => (clone $analyses)->where('created_at', '>=', now()->subDays(30))->where('meta', 'like', '%"urgence_relevee":true%')->count(),
            'derniere' => $derniere?->created_at?->diffForHumans(),
            'assistant' => collect(['web' => 'le site', 'whatsapp' => 'WhatsApp', 'sms' => 'les SMS'])
                ->filter(fn ($libelle, $canal) => app(\App\Services\Canaux\AssistantIa::class)->disponiblePour($canal))->values()->all(),
        ];
    }

    /** Essai sur un récit fictif en wolof : vérifie que le modèle répond et mesure le temps. */
    public function tester(Triage $triage, ClientIa $ia)
    {
        if (! $ia->actif()) {
            return back()->with('erreur', 'L\'IA n\'est pas installée sur ce serveur. Lancez « ./deploy.sh ia » depuis le Mac.');
        }
        @set_time_limit(200);
        $debut = microtime(true);
        $avis = $triage->avisIa('Sama jëkkër mi ngi ci kër gi léegi, dafa may dóor ak paaka. (récit fictif de test)');
        $duree = round(microtime(true) - $debut, 1);
        AuditLog::noter('ia_test', null, ['ok' => (bool) $avis, 'duree' => $duree]);

        return $avis
            ? back()->with('succes', "L'IA répond ({$duree} s, modèle ".config('feggu.ia.modele').') : '
                .($avis['danger_immediat'] ? 'danger immédiat détecté' : 'pas de danger immédiat').' — « '.$avis['raison'].' »')
            : back()->with('erreur', "L'IA n'a pas répondu après {$duree} s. Vérifiez le service sur le serveur : sudo systemctl status ollama");
    }

    /** Relance l'analyse d'un récit (par exemple après installation de l'IA). */
    public function analyser(Request $request, Signalement $signalement, Signalements $service, ClientIa $ia)
    {
        abort_unless($signalement->estVisiblePar($request->user()), 403);
        if (! $ia->actif()) {
            return back()->with('erreur', 'L\'IA n\'est pas installée sur ce serveur.');
        }
        if (! filled($signalement->recit)) {
            return back()->with('erreur', 'Ce signalement n\'a pas de récit à analyser.');
        }
        @set_time_limit(200);
        $avis = $service->analyserAvecIa($signalement->id);
        AuditLog::noter('ia_analyse', $signalement, ['ok' => (bool) $avis]);

        return $avis
            ? back()->with('succes', 'Analyse IA terminée : '.($avis['raison'] ?: 'voir l\'historique').'.')
            : back()->with('erreur', 'L\'IA n\'a pas répondu. Le tri par règles reste appliqué.');
    }
}
