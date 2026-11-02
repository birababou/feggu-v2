<?php

namespace App\Services\Canaux;

use App\Models\Conversation as ModeleConversation;
use App\Models\Signalement;
use App\Services\Contenu\Textes;
use App\Services\Ia\ClientIa;
use App\Services\Localisateur;
use App\Services\Signalements;
use App\Services\Triage;
use App\Services\Texte;

/**
 * Moteur de conversation commun à tous les canaux (SMS, USSD, WhatsApp, chatbot web, vocal transcrit).
 * Chaque canal ne fait que traduire son format vers recevoir() et renvoyer la réponse.
 *
 * Principes : aucune inscription, aucune connexion internet nécessaire (SMS/USSD),
 * le mot-code SOS déclenche une alerte critique à n'importe quelle étape,
 * et une alerte n'est jamais perdue même si la personne abandonne la conversation.
 */
class Conversation
{
    /** Délai après lequel une conversation inactive repart du début. */
    private const EXPIRATION_MINUTES = 30;

    private const CATEGORIES = ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => null];

    private const AGES = ['1' => '0-11', '2' => '12-17', '3' => '18+', '4' => null];

    public function __construct(
        private Signalements $signalements,
        private Localisateur $localisateur,
        private ClientIa $ia,
        private AssistantIa $assistant,
        private Triage $triage,
    ) {}

    /**
     * @return array{reponse:string, fin:bool, conversation:ModeleConversation}
     */
    public function recevoir(string $canal, string $contact, ?string $texte, ?string $sessionExterne = null, ?string $urlAudio = null): array
    {
        $conv = $this->retrouver($canal, $contact, $sessionExterne);
        $texte = trim((string) $texte);

        if ($urlAudio) {
            $transcription = $this->ia->transcrire($urlAudio, $conv->langue);
            $conv->messages()->create(['sens' => 'in', 'contenu' => $transcription, 'media_type' => 'audio', 'media_url' => $urlAudio]);
            $texte = $transcription ?? '';
            $conv->donnees = array_merge($conv->donnees ?? [], ['audio' => array_merge($conv->donnees['audio'] ?? [], [$urlAudio])]);
        } else {
            $conv->messages()->create(['sens' => 'in', 'contenu' => $texte]);
        }

        [$reponse, $fin] = $this->traiter($conv, $texte, (bool) $urlAudio);

        $conv->dernier_message_le = now();
        $conv->save();
        $conv->messages()->create(['sens' => 'out', 'contenu' => $reponse]);

        return ['reponse' => $reponse, 'fin' => $fin, 'conversation' => $conv];
    }

    private function retrouver(string $canal, string $contact, ?string $sessionExterne): ModeleConversation
    {
        $empreinte = ModeleConversation::empreinte($contact);
        $conv = ModeleConversation::query()
            ->where('canal', $canal)
            ->where('contact_hash', $empreinte)
            ->when($sessionExterne, fn ($q) => $q->where('session_externe', $sessionExterne))
            ->latest('id')
            ->first();

        $expiree = $conv && $conv->dernier_message_le && $conv->dernier_message_le->lt(now()->subMinutes(self::EXPIRATION_MINUTES));
        if (! $conv || $expiree || $conv->etape === 'termine') {
            $conv = ModeleConversation::create([
                'canal' => $canal,
                'contact_hash' => $empreinte,
                'contact' => $contact,
                'session_externe' => $sessionExterne,
                'langue' => $conv?->langue ?? 'fr',
                'etape' => 'accueil',
                'donnees' => [],
            ]);
        }

        return $conv;
    }

    /** @return array{0:string,1:bool} réponse, fin de session */
    private function traiter(ModeleConversation $conv, string $texte, bool $estAudio): array
    {
        $l = $conv->langue;
        $n = Texte::normaliser($texte);

        // Mot-code SOS : à n'importe quelle étape.
        foreach (config('feggu.mots_sos') as $mot) {
            if ($n !== '' && Texte::contientMot($n, $mot)) {
                return $this->alerteSos($conv, $texte);
            }
        }

        if (in_array($n, ['menu', '0'], true) && ! in_array($conv->etape, ['accueil', 'recit'], true)) {
            return $this->aller($conv, 'menu', Textes::t('menu', $l));
        }

        // Assistante IA : messages libres compris et réponses rédigées, sur les canaux où elle est activée.
        if (in_array($conv->etape, ['accueil', 'ia', 'rapide'], true) && $n !== 'menu' && $this->assistant->disponiblePour($conv->canal)) {
            return $this->avecAssistant($conv, $texte, $n);
        }

        switch ($conv->etape) {
            case 'accueil':
                // Ancien format v1 du 21211 : « FEGGU MBOUR femme battue ». Tout message libre assez long est traité comme un signalement direct.
                if ($this->estMessageLibre($n)) {
                    return $this->signalementRapide($conv, $texte);
                }

                return $this->aller($conv, 'langue', Textes::t('choix_langue', $l));

            case 'langue':
                if (! in_array($n, ['1', '2'], true)) {
                    return [Textes::t('choix_langue', $l), false];
                }
                $conv->langue = $n === '2' ? 'wo' : 'fr';

                return $this->aller($conv, 'menu', Textes::t('menu', $conv->langue));

            case 'menu':
                return match ($n) {
                    '1' => $this->aller($conv, 'qui', Textes::t('qui', $l)),
                    '2' => $this->aller($conv, 'menu', Textes::t('droits', $l)),
                    '3' => $this->aller($conv, 'menu', Textes::t('urgences', $l)),
                    '4' => $this->aller($conv, 'suivi', Textes::t('suivi_demande', $l)),
                    default => [Textes::t('incompris', $l)."\n".Textes::t('menu', $l), false],
                };

            case 'qui':
                if (! in_array($n, ['1', '2'], true)) {
                    return [Textes::t('qui', $l), false];
                }
                $this->retenir($conv, ['declarant' => $n === '1' ? 'victime' : 'temoin']);

                return $this->aller($conv, 'danger', Textes::t('danger', $l));

            case 'danger':
                if (! in_array($n, ['1', '2'], true)) {
                    return [Textes::t('danger', $l), false];
                }
                $this->retenir($conv, ['danger_immediat' => $n === '1']);
                if ($n === '1') {
                    // Danger : on crée l'alerte tout de suite, sans attendre la fin des questions.
                    $s = $this->enregistrer($conv);
                    $conv->etape = 'type';

                    if ($conv->canal === 'ussd') {
                        // Un écran USSD est limité à ~180 caractères : l'alerte d'abord, les questions ensuite.
                        $conv->etape = 'apres_sos';

                        return [Textes::t('sos_court', $l, ['code' => $s->reference])."\n".Textes::t('continuer', $l), false];
                    }

                    return [Textes::t('sos', $l, ['code' => $s->reference])."\n".Textes::t('type', $l), false];
                }

                return $this->aller($conv, 'type', Textes::t('type', $l));

            case 'apres_sos':
                return $this->aller($conv, 'type', Textes::t('type', $l));

            case 'type':
                if (! array_key_exists($n, self::CATEGORIES)) {
                    return [Textes::t('type', $l), false];
                }
                $this->retenir($conv, ['categorie_id' => self::CATEGORIES[$n]]);

                return $this->aller($conv, 'age', Textes::t('age', $l));

            case 'age':
                if (! array_key_exists($n, self::AGES)) {
                    return [Textes::t('age', $l), false];
                }
                $this->retenir($conv, ['victime_tranche_age' => self::AGES[$n]]);

                return $this->aller($conv, 'lieu', Textes::t('lieu', $l));

            case 'lieu':
                $lieu = $this->localisateur->trouver($texte);
                $this->retenir($conv, array_merge($lieu, ['lieu_texte' => $texte]));

                return $this->aller($conv, 'recit', Textes::t('recit', $l));

            case 'recit':
                if ($n !== '0' && $texte !== '') {
                    $this->retenir($conv, ['recit' => trim(($conv->donnees['recit'] ?? '').' '.$texte)]);
                }

                return $this->aller($conv, 'contact', Textes::t('contact', $l));

            case 'contact':
                if (! in_array($n, ['1', '2'], true)) {
                    return [Textes::t('contact', $l), false];
                }
                $this->retenir($conv, ['consentement_contact' => $n === '1']);
                $s = $this->enregistrer($conv);
                $conv->etape = 'termine';
                $message = Textes::t('confirmation', $l, ['code' => $s->reference]);
                if ($n === '1') {
                    $message .= ' '.Textes::t('confirmation_contact', $l);
                }

                return [$message, true];

            case 'suivi':
                $s = Signalement::where('reference', strtoupper(trim($texte)))->first();
                $conv->etape = 'menu';

                return $s
                    ? [Textes::t('suivi_resultat', $l, ['code' => $s->reference, 'statut' => Textes::statut($s->statut, $l)]), false]
                    : [Textes::t('suivi_inconnu', $l), false];

            case 'rapide':
                // Après un signalement direct, un nouveau message libre complète le même dossier.
                if ($this->estMessageLibre($n) && $conv->signalement_id) {
                    $this->retenir($conv, ['recit' => trim(($conv->donnees['recit'] ?? '').' '.$texte)]);
                    $this->enregistrer($conv);

                    return [Textes::t('rapide', $l, ['code' => $conv->signalement->reference]), false];
                }

                return $this->aller($conv, 'langue', Textes::t('choix_langue', $l));
        }

        return $this->aller($conv, 'menu', Textes::t('menu', $l));
    }

    private const OUI = ['1', 'oui', 'waaw', 'wao', 'yes', 'ok', 'daccord', 'd accord'];

    private const NON = ['2', 'non', 'deedeet', 'dedeet', 'deedet', 'no', 'nan'];

    private const FIN = ['merci', 'au revoir', 'jerejef', 'jarajef', 'ba beneen', 'ba suba', 'bye', 'c est tout', 'cest tout'];

    /** Une autre personne est concernée (la personne qui écrit est témoin). */
    private const PROCHES = ['ma fille', 'mon fils', 'ma voisine', 'ma soeur', 'ma cousine', 'ma niece', 'mon amie', 'ma copine', 'ma mere', 'ma tante', 'une fille', 'une femme', 'une enfant', 'un enfant',
        'sama doom', 'sama rakk', 'sama mag', 'sama deukkando', 'sama dekkando', 'sama xarit', 'sama yaay', 'sama jarbaat', 'benn xale', 'benn jigeen'];

    private const SALUTATIONS = ['bonjour', 'bonsoir', 'salut', 'salam', 'asalaa maalekum', 'salaamaalekum', 'nanga def', 'hello', 'hi', 'allo'];

    /** Mots très courants en wolof, pour répondre dans la bonne langue sans attendre l'IA. */
    private const MOTS_WOLOF = ['sama', 'dafa', 'dama', 'dafay', 'dañu', 'danu', 'mu', 'nga', 'ngi', 'yi', 'bi', 'ak', 'ci', 'leegi', 'jerejef', 'waaw', 'deedeet', 'jekker', 'doom', 'ker', 'door', 'xale', 'naka', 'nanga', 'def', 'maa', 'ko', 'nit', 'jigeen', 'goor', 'mangi', 'ngay'];

    private const MOTS_FRANCAIS = ['je', 'il', 'elle', 'mon', 'ma', 'mes', 'est', 'suis', 'les', 'des', 'une', 'qui', 'pas', 'avec', 'mais', 'pour', 'dans', 'mari', 'moi'];

    /**
     * Conversation libre avec l'assistante : l'IA comprend, le serveur répond avec des textes validés.
     *
     * @return array{0:string,1:bool}
     */
    private function avecAssistant(ModeleConversation $conv, string $texte, string $n): array
    {
        if ($texte === '') {
            return $this->aller($conv, 'ia', Textes::t('ia_accueil', $conv->langue));
        }
        $conv->etape = 'ia';
        $conv->langue = $l = $this->detecterLangue($n) ?? $conv->langue;

        // Un code de suivi reçoit une réponse exacte, donnée par le serveur.
        if (preg_match('/FG-[A-Z]{4}-\d{4}/i', $texte, $m)) {
            $s = Signalement::where('reference', strtoupper($m[0]))->first();

            return [$s ? Textes::t('ia_suivi', $l, ['code' => $s->reference, 'statut' => Textes::statut($s->statut, $l)]) : Textes::t('ia_suivi_inconnu', $l), false];
        }

        $question = $conv->donnees['question'] ?? null;
        $court = count(explode(' ', $n)) <= 3;
        $repondu = $question && $this->appliquerReponse($conv, $question, $n, $texte);

        if (! $repondu && $court && $this->contientUn($n, self::FIN)) {
            $conv->etape = 'termine';
            if ($conv->signalement) {
                $this->signalements->planifierAnalyseIa($conv->signalement);
            }

            return [$conv->signalement_id ? Textes::t('ia_fin', $l, ['code' => $conv->signalement->reference]) : Textes::t('au_revoir', $l), true];
        }

        if (! $repondu && ! $question && $court && $this->contientUn($n, self::SALUTATIONS)) {
            return [Textes::t('ia_relance', $l), false];
        }

        // Une réponse simple à la question posée (« 1 », « waaw », « Mbour ») n'a pas besoin de l'IA.
        $avis = $repondu && $court ? null : $this->assistant->comprendre($texte, $question ? strtok(Textes::t($this->texteQuestion($conv, $question), 'fr'), "\n") : null);

        if (! $avis && ! $repondu) {
            // IA muette ou trop lente : le message n'est jamais perdu.
            if ($conv->signalement_id || $this->estMessageLibre($n)) {
                // Sans l'IA, les règles lisent quand même le message : danger, type, lieu, proche concerné.
                $d = $conv->donnees ?? [];
                $this->retenir($conv, array_filter([
                    'recit' => $this->recitDeLaPersonne($conv),
                    'danger_immediat' => $this->triage->indicesDanger($n) ? true : null,
                    'categorie_id' => $d['categorie_id'] ?? $this->triage->devinerCategorie($n),
                    'declarant' => $d['declarant'] ?? ($this->contientUn($n, self::PROCHES) ? 'temoin' : null),
                ], fn ($v) => $v !== null) + (empty($d['departement_id']) ? array_filter($this->localisateur->trouver($texte)) : []));
                $s = $this->enregistrer($conv);
                $reponse = Textes::t('rapide', $l, ['code' => $s->reference]);
                if (! empty($conv->donnees['danger_immediat']) && empty($conv->donnees['urgences_donnees'])) {
                    $reponse .= "\n\n".Textes::t('ia_urgence', $l);
                    $this->retenir($conv, ['urgences_donnees' => true]);
                }

                return [$reponse, false];
            }

            return [Textes::t($this->contientUn($n, self::SALUTATIONS) ? 'ia_relance' : 'ia_incompris', $l), false];
        }

        // Les règles passent d'abord (elles connaissent les mots wolof du danger), l'IA complète ce qui manque.
        $d = $conv->donnees ?? [];
        $indices = $this->triage->indicesDanger($n);
        $categorie = $this->triage->devinerCategorie($n);
        $complements = array_filter([
            'categorie_id' => $d['categorie_id'] ?? $categorie ?? $avis['categorie_id'] ?? null,
            'declarant' => $d['declarant'] ?? ($this->contientUn($n, self::PROCHES) ? 'temoin' : null) ?? $avis['declarant'] ?? null,
        ], fn ($v) => $v !== null);
        // « 13 ans », « 13 at » dans le récit : l'âge est connu sans le demander.
        if (empty($d['victime_tranche_age']) && preg_match('/\b(\d{1,2}) (ans|an|at)\b/', $n, $m) && (int) $m[1] > 0) {
            $complements['victime_tranche_age'] = $this->trancheAge((int) $m[1]);
        }
        // Danger : les mots-indices suffisent ; un « danger » vu par l'IA seule est vérifié en posant la question à la personne.
        if ($indices) {
            $complements['danger_immediat'] = true;
        } elseif (! array_key_exists('danger_immediat', $d) && ($avis['danger_immediat'] ?? null) === false) {
            $complements['danger_immediat'] = false;
        }
        if (empty($d['departement_id'])) {
            $lieu = array_filter($this->localisateur->trouver($texte)) ?: (! empty($avis['lieu']) ? array_filter($this->localisateur->trouver($avis['lieu'])) : []);
            $complements += $lieu + (! empty($avis['lieu']) && ! $lieu ? ['lieu_texte' => $avis['lieu']] : []);
        }
        if (empty($d['victime_tranche_age']) && ! empty($avis['age'])) {
            $complements['victime_tranche_age'] = $this->trancheAge($avis['age']);
        }
        $this->retenir($conv, $complements + ['assistant' => true]);

        // Une question (« l'excision est-elle interdite ? ») cite une violence sans en signaler une.
        $intention = $avis['intention'] ?? null;
        $signaler = $conv->signalement_id || $question || $intention === 'signaler' || $indices
            || ($categorie && ! in_array($intention, ['information', 'salutation', 'fin'], true));
        if (! $signaler) {
            if (($avis['intention'] ?? null) === 'information') {
                $reponse = match ($avis['sujet']) {
                    'urgences' => Textes::numerosUrgence($l),
                    'fonctionnement' => Textes::t('ia_fonctionnement', $l),
                    default => Textes::t('ia_droits', $l),
                };

                return [$reponse."\n\n".Textes::t('ia_invitation', $l), false];
            }
            if (($avis['intention'] ?? null) === 'fin') {
                $conv->etape = 'termine';

                return [Textes::t('au_revoir', $l), true];
            }

            return [Textes::t('ia_relance', $l), false];
        }

        // Le récit enregistré, ce sont les mots de la personne : jamais un texte rédigé par l'IA.
        $this->retenir($conv, ['recit' => $this->recitDeLaPersonne($conv)]);

        $parties = [];
        $nouveau = ! $conv->signalement_id;
        $s = $this->enregistrer($conv);
        if ($nouveau) {
            $parties[] = $avis['ecoute'] ?? Textes::t(($conv->donnees['declarant'] ?? null) === 'temoin' ? 'ia_ecoute_temoin' : 'ia_ecoute', $l);
            $parties[] = Textes::t('ia_enregistre', $l, ['code' => $s->reference]);
        }
        if (! empty($conv->donnees['danger_immediat']) && empty($conv->donnees['urgences_donnees'])) {
            $parties[] = Textes::t('ia_urgence', $l);
            $this->retenir($conv, ['urgences_donnees' => true]);
        }

        $suivante = $this->prochaineQuestion($conv);
        $this->retenir($conv, ['question' => $suivante, 'demandees' => array_values(array_unique(array_merge($conv->donnees['demandees'] ?? [], array_filter([$suivante]))))]);
        if ($suivante) {
            $parties[] = Textes::t($this->texteQuestion($conv, $suivante), $l);
        } elseif (empty($conv->donnees['complet'])) {
            $parties[] = Textes::t('ia_complet', $l, ['code' => $s->reference]);
            $this->retenir($conv, ['complet' => true]);
        } else {
            $parties[] = Textes::t('ia_note', $l);
        }

        return [implode("\n\n", $parties), false];
    }

    /** Les messages de la personne qui racontent quelque chose (pas les « 1 », « oui », « Mbour »). */
    private function recitDeLaPersonne(ModeleConversation $conv): string
    {
        // Les numéros de téléphone donnés pour le rappel ne vont pas dans le récit.
        return $conv->messages()->where('sens', 'in')->orderBy('id')->get()
            ->map(fn ($m) => trim(preg_replace('/\+?\d[\d .-]{7,}\d/', '', (string) $m->contenu)))
            ->filter(fn ($t) => count(explode(' ', Texte::normaliser($t))) >= 3)->implode("\n");
    }

    /** Prochaine information utile à demander ; chaque question n'est posée qu'une fois. */
    private function prochaineQuestion(ModeleConversation $conv): ?string
    {
        $d = $conv->donnees ?? [];
        $manque = [
            'danger' => ! array_key_exists('danger_immediat', $d),
            'qui' => empty($d['declarant']),
            'lieu' => empty($d['departement_id']) && empty($d['lieu_texte']),
            'recit' => empty($d['categorie_id']) && count(explode(' ', Texte::normaliser($d['recit'] ?? ''))) < 8,
            'age' => empty($d['victime_tranche_age']) && empty($d['age_inconnu']),
            'contact' => ! array_key_exists('consentement_contact', $d),
            'numero' => $conv->canal === 'web' && ! empty($d['consentement_contact']) && empty($d['telephone_rappel']),
        ];
        foreach ($manque as $q => $oui) {
            if ($oui && ! in_array($q, $d['demandees'] ?? [], true)) {
                return $q;
            }
        }

        return null;
    }

    private function texteQuestion(ModeleConversation $conv, string $question): string
    {
        return match ($question) {
            'recit' => 'ia_recit',
            'danger' => ($conv->donnees['declarant'] ?? null) === 'temoin' ? 'ia_danger_temoin' : 'danger',
            'contact' => $conv->canal === 'web' ? 'ia_contact_web' : 'contact',
            'numero' => 'ia_contact_numero',
            default => $question,
        };
    }

    /** Réponse directe à la question posée (chiffre, oui/non, lieu, âge, numéro) : sans IA. */
    private function appliquerReponse(ModeleConversation $conv, string $question, string $n, string $texte): bool
    {
        $oui = $this->contientUn($n, self::OUI, true);
        $non = $this->contientUn($n, self::NON, true);
        $tel = $this->numero($texte);

        switch ($question) {
            case 'danger':
                if ($oui || $non) {
                    $this->retenir($conv, ['danger_immediat' => $oui && ! $non]);

                    return true;
                }

                return false;
            case 'qui':
                $victime = $n === '1' || $this->contientUn($n, ['moi', 'moi meme', 'man', 'maa ngi', 'c est moi']);
                $temoin = $n === '2' || $this->contientUn($n, ['autre', 'keneen', 'une autre personne', 'quelqu un']);
                if ($victime xor $temoin) {
                    $this->retenir($conv, ['declarant' => $victime ? 'victime' : 'temoin']);

                    return true;
                }

                return false;
            case 'lieu':
                $lieu = array_filter($this->localisateur->trouver($texte));
                if ($lieu) {
                    $this->retenir($conv, $lieu);

                    return true;
                }
                if (count(explode(' ', $n)) <= 4) {
                    $this->retenir($conv, ['lieu_texte' => mb_substr($texte, 0, 100)]);

                    return true;
                }

                return false;
            case 'age':
                $tranches = ['1' => '0-11', '2' => '12-17', '3' => '18+'];
                if (isset($tranches[$n])) {
                    $this->retenir($conv, ['victime_tranche_age' => $tranches[$n]]);

                    return true;
                }
                if ($n === '4' || $this->contientUn($n, ['sais pas', 'xamuma', 'je ne sais'])) {
                    $this->retenir($conv, ['age_inconnu' => true]);

                    return true;
                }
                if (preg_match('/\b(\d{1,2})\b/', $n, $m) && (int) $m[1] > 0) {
                    $this->retenir($conv, ['victime_tranche_age' => $this->trancheAge((int) $m[1])]);

                    return true;
                }

                return false;
            case 'contact':
            case 'numero':
                if ($tel) {
                    $this->retenir($conv, ['consentement_contact' => true, 'telephone_rappel' => $tel]);

                    return true;
                }
                if ($non) {
                    $this->retenir($conv, ['consentement_contact' => false]);

                    return true;
                }
                if ($oui && $question === 'contact') {
                    $this->retenir($conv, ['consentement_contact' => true]);

                    return true;
                }

                return $question === 'numero' && count(explode(' ', $n)) <= 3;
        }

        return false;
    }

    private function contientUn(string $n, array $mots, bool $seul = false): bool
    {
        foreach ($mots as $mot) {
            if ($seul ? ($n === $mot || str_starts_with($n, $mot.' ')) : Texte::contientMot($n, $mot)) {
                return true;
            }
        }

        return false;
    }

    private function detecterLangue(string $n): ?string
    {
        $mots = explode(' ', $n);
        $wo = count(array_intersect($mots, self::MOTS_WOLOF));
        $fr = count(array_intersect($mots, self::MOTS_FRANCAIS));

        return match (true) {
            $wo >= 2 && $wo > $fr, $this->contientUn($n, ['jerejef', 'waaw', 'deedeet', 'asalaa maalekum', 'nanga def']) => 'wo',
            $fr >= 2 && $fr > $wo, $this->contientUn($n, ['bonjour', 'bonsoir', 'merci', 'oui', 'non']) => 'fr',
            default => null,
        };
    }

    private function numero(string $texte): ?string
    {
        $chiffres = preg_replace('/^(00)?221/', '', preg_replace('/\D/', '', $texte));

        return preg_match('/^7[05678]\d{7}$/', $chiffres) ? $chiffres : null;
    }

    private function trancheAge(int $age): string
    {
        return $age < 12 ? '0-11' : ($age < 18 ? '12-17' : '18+');
    }

    private function aller(ModeleConversation $conv, string $etape, string $reponse): array
    {
        $conv->etape = $etape;

        return [$reponse, false];
    }

    private function retenir(ModeleConversation $conv, array $valeurs): void
    {
        $conv->donnees = array_merge($conv->donnees ?? [], $valeurs);
    }

    private function estMessageLibre(string $n): bool
    {
        return str_word_count($n) >= 3 && ! preg_match('/^\d+$/', $n);
    }

    private function alerteSos(ModeleConversation $conv, string $texte): array
    {
        $lieu = empty($conv->donnees['departement_id']) ? array_filter($this->localisateur->trouver($texte)) : [];
        $this->retenir($conv, array_merge($lieu, ['danger_immediat' => true, 'recit' => trim(($conv->donnees['recit'] ?? '').' '.$texte)]));
        $s = $this->enregistrer($conv);
        $l = $conv->langue;
        $conv->etape = $conv->etape === 'accueil' ? 'qui' : $conv->etape;

        // Après l'alerte, on propose de continuer pour préciser, sans l'imposer.
        if ($conv->canal === 'ussd') {
            return [Textes::t('sos_court', $l, ['code' => $s->reference])."\n".Textes::t('qui', $l), false];
        }

        return [Textes::t('sos', $l, ['code' => $s->reference])."\n".Textes::t('sos_suite', $l)."\n".Textes::t('qui', $l), false];
    }

    private function signalementRapide(ModeleConversation $conv, string $texte): array
    {
        $this->retenir($conv, array_merge($this->localisateur->trouver($texte), ['recit' => $texte]));
        $s = $this->enregistrer($conv);
        $conv->etape = 'rapide';

        return [Textes::t('rapide', $conv->langue, ['code' => $s->reference]), false];
    }

    /** Crée le signalement au premier appel, puis le complète aux appels suivants. */
    private function enregistrer(ModeleConversation $conv): Signalement
    {
        $d = $conv->donnees ?? [];
        $recit = trim(($d['recit'] ?? '').(! empty($d['lieu_texte']) && empty($d['departement_id']) ? ' (lieu indiqué : '.$d['lieu_texte'].')' : ''));
        $champs = [
            'declarant' => $d['declarant'] ?? null,
            'danger_immediat' => (bool) ($d['danger_immediat'] ?? false),
            'categorie_id' => $d['categorie_id'] ?? null,
            'victime_tranche_age' => $d['victime_tranche_age'] ?? null,
            'victime_sexe' => $d['victime_sexe'] ?? null,
            'auteur_connu' => $d['auteur_connu'] ?? null,
            'besoins' => $d['besoins'] ?? null,
            'region_id' => $d['region_id'] ?? null,
            'departement_id' => $d['departement_id'] ?? null,
            'commune_id' => $d['commune_id'] ?? null,
            'recit' => $recit !== '' ? $recit : null,
            'langue' => $conv->langue,
            'consentement_contact' => (bool) ($d['consentement_contact'] ?? false),
            'contact_telephone' => empty($d['consentement_contact']) ? null
                : ($d['telephone_rappel'] ?? ($conv->canal !== 'web' && ! str_starts_with((string) $conv->contact, 'demo-') ? $conv->contact : null)),
            'periode_faits' => ! empty($d['danger_immediat']) ? 'en_cours' : null,
        ];

        if ($conv->signalement_id && ($s = Signalement::find($conv->signalement_id))) {
            // Avec l'assistante, l'IA lit déjà chaque message : la relecture complète du récit attend la fin de l'échange
            // (sinon les deux analyses se disputent le modèle et la personne attend plus longtemps).
            return $this->signalements->completer($s, $champs, empty($d['assistant']));
        }

        $canal = $conv->canal === 'web' ? 'web' : (! empty($d['audio']) ? 'voix' : $conv->canal);
        // Messages du simulateur du back-office : marqués démo dès la création, aucune structure n'est prévenue.
        $demo = str_starts_with((string) $conv->contact, 'demo-');
        $s = $this->signalements->creer(array_merge($champs, ['conversation_id' => $conv->id, 'est_demo' => $demo]), $canal);
        $conv->signalement_id = $s->id;

        return $s;
    }
}
