<?php

namespace App\Services\Contenu;

/**
 * Textes du chatbot (SMS, USSD, WhatsApp, web), en français et en wolof.
 * Ils sont courts pour tenir dans un SMS ou un écran USSD.
 *
 * Traductions wolof : première version, à faire relire par GESTES et des locutrices avant la mise en production.
 */
class Textes
{
    private const T = [
        'choix_langue' => [
            'fr' => "FEGGU, vous n'êtes pas seule.\n1 Français\n2 Wolof",
            'wo' => "FEGGU, du yaw rekk.\n1 Français\n2 Wolof",
        ],
        'menu' => [
            'fr' => "FEGGU\n1 Signaler une violence\n2 Connaître mes droits\n3 Numéros d'urgence\n4 Suivre mon signalement",
            'wo' => "FEGGU\n1 Yëgle ab fitna\n2 Xam sama yelleef\n3 Nimero yu ndimbal ci gaaw\n4 Seet sama yëgle",
        ],
        'qui' => [
            'fr' => "Qui est concerné ?\n1 Moi-même\n2 Une autre personne",
            'wo' => "Kan la jot?\n1 Man\n2 Keneen",
        ],
        'danger' => [
            'fr' => "Êtes-vous en danger en ce moment ?\n1 Oui\n2 Non",
            'wo' => "Ndax yaa ngi ci musiba léegi?\n1 Waaw\n2 Déedéet",
        ],
        'type' => [
            'fr' => "Quelle violence ?\n1 Sexuelle\n2 Coups, blessures\n3 Menaces, insultes\n4 Abandon familial\n5 Enfant maltraité\n6 Excision\n7 Mariage forcé ou d'enfant\n8 Exploitation\n9 Autre",
            'wo' => "Ban fitna?\n1 Ci wàll jëmm\n2 Dóor, gaañ\n3 Tëkk, saaga\n4 Bàyyi njaboot\n5 Fitnaal xale\n6 Xaraf\n7 Séyal xale walla ci doole\n8 Jëfandikoo jëmm\n9 Leneen",
        ],
        'age' => [
            'fr' => "Âge de la victime ?\n1 Moins de 12 ans\n2 12 à 17 ans\n3 18 ans et plus\n4 Je ne sais pas",
            'wo' => "Ñaata at la am?\n1 Ndaw na ci 12 at\n2 12 ba 17 at\n3 18 at walla lu ko ëpp\n4 Xamuma",
        ],
        'lieu' => [
            'fr' => "Dans quelle commune ou quel département ? (ex : Mbour)",
            'wo' => "Ci ban dëkk? (misaal: Mbuur)",
        ],
        'recit' => [
            'fr' => "Racontez en quelques mots ce qui se passe, ou envoyez un message vocal. Envoyez 0 pour passer.",
            'wo' => "Waxal ci ay baat lu xew, walla yónnee ab kàddu. Bindal 0 ngir romb.",
        ],
        'contact' => [
            'fr' => "Une structure d'aide peut vous rappeler sur ce numéro, en toute discrétion. D'accord ?\n1 Oui\n2 Non",
            'wo' => "Benn mbootaay mën na la woote ci nimero bii, ci sutura. Nangu nga?\n1 Waaw\n2 Déedéet",
        ],
        'confirmation' => [
            'fr' => "Merci. Votre signalement est enregistré. Code de suivi : :code. Gardez-le. Si votre téléphone n'est pas sûr, effacez cette conversation.",
            'wo' => "Jërëjëf. Sa yëgle jot na. Sa code: :code. Denc ko. Su sa telefon wóoradiwul, far waxtaan wii.",
        ],
        'confirmation_contact' => [
            'fr' => "Une structure d'aide proche de vous va vous rappeler.",
            'wo' => "Benn mbootaay bu la jege dina la woote.",
        ],
        'sos' => [
            'fr' => "Alerte reçue. Si vous le pouvez, mettez-vous à l'abri. :numeros. Code : :code",
            'wo' => "Sa woote jot na. Su la neexee, wutal fu wóor. :numeros. Code: :code",
        ],
        'sos_court' => [
            'fr' => "Alerte reçue. Code :code. Police 17, Gendarmerie 800 00 20 20.",
            'wo' => "Sa woote jot na. Code :code. Polis 17, Sàndarmëri 800 00 20 20.",
        ],
        'continuer' => [
            'fr' => '1 Continuer',
            'wo' => '1 Wéy',
        ],
        'sos_suite' => [
            'fr' => "Nous continuons pour mieux vous aider.",
            'wo' => "Nu ngi wéy ngir gën la dimbali.",
        ],
        'rapide' => [
            'fr' => "Message reçu, merci. Une structure d'aide est prévenue. Code de suivi : :code. Envoyez MENU pour d'autres options.",
            'wo' => "Sa bataaxal jot na, jërëjëf. Benn mbootaay xam na ko. Sa code: :code. Yónnee MENU ngir yeneen.",
        ],
        'droits' => [
            'fr' => "Au Sénégal, le viol, les coups, l'excision et le mariage forcé sont punis par la loi. Vous avez droit à des soins, à une écoute et à une aide juridique gratuite. Envoyez 0 pour le menu.",
            'wo' => "Ci Senegaal, sàcc jëmm, dóor, xaraf ak séyal ci doole, yoon tere na ko te dina ko mbugal. Am nga sañ-sañ ci fajoo, ci ku la déglu ak ndimbal ci àtte, te doo fey dara. Bindal 0 ngir menu.",
        ],
        'urgences' => [
            'fr' => ":numeros. Envoyez 0 pour le menu.",
            'wo' => ":numeros. Bindal 0 ngir menu.",
        ],
        'suivi_demande' => [
            'fr' => "Envoyez votre code de suivi (ex : FG-ABCD-2345).",
            'wo' => "Yónnee sa code (misaal: FG-ABCD-2345).",
        ],
        'suivi_resultat' => [
            'fr' => "Signalement :code : :statut. Envoyez 0 pour le menu.",
            'wo' => "Yëgle :code : :statut. Bindal 0 ngir menu.",
        ],
        'suivi_inconnu' => [
            'fr' => "Code introuvable. Vérifiez-le, ou envoyez 0 pour le menu.",
            'wo' => "Code bi amul. Xoolaatal ko, walla bindal 0 ngir menu.",
        ],
        'incompris' => [
            'fr' => "Réponse non comprise. Envoyez le numéro de votre choix.",
            'wo' => "Xamuma li nga bind. Yónneel nimero bi nga tànn.",
        ],
        'ia_accueil' => [
            'fr' => "Bonjour, je suis l'assistante FEGGU. Ici, c'est gratuit et confidentiel, vous n'avez pas à donner votre nom.\nÉcrivez-moi ce qui se passe, en français ou en wolof : je vous écoute.\nEn danger maintenant ? Écrivez SOS. Pour le menu, écrivez MENU.",
            'wo' => "Asalaa maalekum, man maay ndimbalkat FEGGU. Fii, doo fey dara te kenn du xam sa tur.\nBindal ma li xew, ci wolof walla ci faranse: maa ngi lay déglu.\nNdax yaa ngi ci musiba léegi? Bindal SOS. Ngir menu bi, bindal MENU.",
        ],
        'ia_ecoute' => [
            'fr' => "Merci de m'en parler. Ce qui arrive n'est pas de votre faute, et vous n'êtes pas seule.",
            'wo' => "Jërëjëf ci li nga ma ko wax. Li xew du sa tooñ, te du yaw rekk.",
        ],
        'ia_ecoute_temoin' => [
            'fr' => "Merci de nous alerter, vous avez bien fait d'en parler.",
            'wo' => "Jërëjëf ci li nga nu ko yëgle, def nga lu baax.",
        ],
        'ia_danger_temoin' => [
            'fr' => "La personne est-elle en danger en ce moment ?\n1 Oui\n2 Non",
            'wo' => "Ndax moom mi ngi ci musiba léegi?\n1 Waaw\n2 Déedéet",
        ],
        'ia_enregistre' => [
            'fr' => "Votre signalement est enregistré et transmis à une structure d'aide. Code de suivi : :code (gardez-le).",
            'wo' => "Sa yëgle bind nañu ko te yónnee nañu ko benn mbootaay. Sa code: :code (denc ko).",
        ],
        'ia_urgence' => [
            'fr' => "Si vous le pouvez, mettez-vous à l'abri. :numeros.",
            'wo' => "Su la neexee, wutal fu wóor. :numeros.",
        ],
        'ia_recit' => [
            'fr' => "Pouvez-vous me raconter ce qui s'est passé, avec vos mots ?",
            'wo' => "Mën nga ma wax li xew, ci sa wax?",
        ],
        'ia_contact_web' => [
            'fr' => "Une structure d'aide peut vous rappeler, en toute discrétion. Si vous le souhaitez, envoyez un numéro où l'on peut vous joindre sans danger. Sinon, écrivez NON.",
            'wo' => "Benn mbootaay mën na la woote, ci sutura. Su la neexee, yónnee ab nimero bu ñu la mën a woote te amul benn musiba. Lu ko moy, bindal DÉEDÉET.",
        ],
        'ia_contact_numero' => [
            'fr' => "À quel numéro peut-on vous joindre sans danger ?",
            'wo' => "Ci ban nimero lañu la mën a woote te amul musiba?",
        ],
        'ia_complet' => [
            'fr' => "C'est noté. Une structure d'aide proche va s'occuper de votre signalement :code. Vous pouvez m'écrire à tout moment pour ajouter un détail ou savoir où il en est.",
            'wo' => "Bind naa ko. Benn mbootaay bu la jege dina topptoo sa yëgle :code. Mën nga ma bind saa su la neexee ngir yokk dara walla xam fu mu tollu.",
        ],
        'ia_note' => [
            'fr' => "C'est noté, j'ai ajouté ce que vous m'avez dit à votre signalement.",
            'wo' => "Bind naa ko, yokk naa li nga ma wax ci sa yëgle.",
        ],
        'ia_relance' => [
            'fr' => "Je vous écoute. Racontez-moi ce qui se passe, pour vous ou pour quelqu'un d'autre. Vous pouvez aussi me poser une question sur vos droits.",
            'wo' => "Maa ngi lay déglu. Wax ma li xew, ci yaw walla ci keneen. Mën nga tamit laaj ci sa yelleef.",
        ],
        'ia_incompris' => [
            'fr' => "Je n'ai pas bien compris. Racontez-moi ce qui se passe, ou écrivez MENU.",
            'wo' => "Dégguma bu baax. Wax ma li xew, walla bindal MENU.",
        ],
        'ia_droits' => [
            'fr' => "Au Sénégal, le viol, les coups, l'excision et le mariage forcé sont punis par la loi. Vous avez droit à des soins, à une écoute et à une aide juridique gratuite.",
            'wo' => "Ci Senegaal, sàcc jëmm, dóor, xaraf ak séyal ci doole, yoon tere na ko te dina ko mbugal. Am nga sañ-sañ ci fajoo, ci ku la déglu ak ndimbal ci àtte, te doo fey dara.",
        ],
        'ia_fonctionnement' => [
            'fr' => "FEGGU reçoit votre signalement sans vous demander votre nom et le transmet à une structure d'aide proche de chez vous (écoute, santé, aide juridique, hébergement). Vous recevez un code pour suivre votre dossier.",
            'wo' => "FEGGU dina jël sa yëgle te du la laaj sa tur, te dina ko yónnee benn mbootaay bu la jege (déglu, fajoo, ndimbal ci àtte, dal). Dinañu la jox ab code ngir seet sa yëgle.",
        ],
        'ia_invitation' => [
            'fr' => "Si vous voulez signaler une violence, écrivez-moi simplement ce qui s'est passé.",
            'wo' => "Soo bëggee yëgle ab fitna, bindal ma li xew rekk.",
        ],
        'ia_suivi' => [
            'fr' => "Signalement :code : :statut.",
            'wo' => "Yëgle :code : :statut.",
        ],
        'ia_suivi_inconnu' => [
            'fr' => "Je ne trouve pas ce code. Vérifiez-le (format FG-ABCD-2345).",
            'wo' => "Gisuma code bii. Xoolaatal ko (misaal: FG-ABCD-2345).",
        ],
        'ia_fin' => [
            'fr' => "Merci pour votre confiance. Code de suivi : :code. Vous pouvez revenir à tout moment. Si votre téléphone n'est pas sûr, effacez cette conversation.",
            'wo' => "Jërëjëf ci sa kóolute. Sa code: :code. Mën ngaa dellu saa su la neexee. Su sa telefon wóoradiwul, far waxtaan wii.",
        ],
        'au_revoir' => [
            'fr' => "Merci. Vous pouvez revenir à tout moment.",
            'wo' => "Jërëjëf. Mën ngaa dellu saa su la neexee.",
        ],
    ];

    /** Libellés des statuts montrés au déclarant (jamais de détail sur le dossier). */
    private const STATUTS = [
        'nouveau' => ['fr' => 'reçu, en cours d\'examen', 'wo' => 'jot na, ñu ngi koy xool'],
        'oriente' => ['fr' => 'transmis à une structure d\'aide', 'wo' => 'yónnee nañu ko benn mbootaay'],
        'en_cours' => ['fr' => 'une structure s\'en occupe', 'wo' => 'benn mbootaay a ngi ci'],
        'pris_en_charge' => ['fr' => 'prise en charge en cours', 'wo' => 'ñu ngi koy topptoo'],
        'clos' => ['fr' => 'traité', 'wo' => 'jeex na'],
    ];

    public static function t(string $cle, string $langue = 'fr', array $params = []): string
    {
        $texte = self::T[$cle][$langue] ?? self::T[$cle]['fr'] ?? $cle;
        if (str_contains($texte, ':numeros')) {
            $params['numeros'] ??= self::numerosUrgence($langue);
        }
        foreach ($params as $k => $v) {
            $texte = str_replace(':'.$k, (string) $v, $texte);
        }

        return $texte;
    }

    public static function statut(string $statut, string $langue = 'fr'): string
    {
        return self::STATUTS[$statut][$langue] ?? self::STATUTS[$statut]['fr'] ?? $statut;
    }

    public static function numerosUrgence(string $langue = 'fr'): string
    {
        return collect(config('feggu.numeros_urgence'))
            ->map(fn ($n) => ($langue === 'wo' ? $n['nom_wo'] : $n['nom']).' '.$n['numero'])
            ->implode(', ');
    }
}
