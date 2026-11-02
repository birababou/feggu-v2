<?php

return [

    // Adresse du code source public (licence AGPL-3.0 : les utilisateurs du service doivent pouvoir l'obtenir).
    'code_source' => env('FEGGU_CODE_SOURCE_URL') ?: 'https://github.com/birababou/feggu-v2',

    // Données de démonstration visibles (bandeau dans le back-office).
    'demo' => env('FEGGU_DEMO', false),

    // Numéro court SMS et code USSD affichés sur le site.
    'numero_court' => env('FEGGU_NUMERO_COURT', '21211'),
    'code_ussd' => env('FEGGU_CODE_USSD', ''),               // ex. "*123*45#" (entre guillemets dans .env), à obtenir auprès de l'agrégateur
    'ussd_choix' => env('FEGGU_USSD_CHOIX', ''),              // si FEGGU est une entrée d'un menu existant (ex. 8 dans #2001#)
    'numero_whatsapp' => env('FEGGU_NUMERO_WHATSAPP', ''),     // ex. 221770000000
    'numero_vocal' => env('FEGGU_NUMERO_VOCAL', ''),           // ligne vocale / appel « bip »

    // Mot-code qui déclenche une alerte de danger immédiat sur tous les canaux.
    'mots_sos' => ['SOS', 'DANGER', 'TEMPALI', 'DIMBALI'],

    // Jeton partagé exigé par tous les webhooks (?token=... ou en-tête X-Feggu-Token).
    'webhook_token' => env('FEGGU_WEBHOOK_TOKEN'),
    'webhook_ips' => env('FEGGU_WEBHOOK_IPS', ''), // ex. IP du serveur de l'agrégateur LAM, séparées par des virgules

    // Envoi SMS sortant : "log" (développement) ou "lam".
    'sms' => [
        'driver' => env('FEGGU_SMS_DRIVER', 'log'),
        'expediteur' => env('FEGGU_SMS_EXPEDITEUR', 'INFO'), // nom neutre : jamais « FEGGU VBG » sur le téléphone d'une victime
        'lam' => [
            'url' => env('LAM_SMS_URL'),
            'utilisateur' => env('LAM_SMS_USER'),
            'mot_de_passe' => env('LAM_SMS_PASSWORD'),
        ],
    ],

    // WhatsApp Business (API Cloud de Meta) : "log" ou "meta".
    'whatsapp' => [
        'driver' => env('FEGGU_WHATSAPP_DRIVER', 'log'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
    ],

    // IA hébergée localement, compatible API OpenAI (Ollama, vLLM, LM Studio...).
    // Sans URL, le tri et le chatbot fonctionnent avec les règles intégrées.
    'ia' => [
        'url' => env('FEGGU_IA_URL'),               // ex. http://127.0.0.1:11434/v1
        'modele' => env('FEGGU_IA_MODELE', 'llama3.1'),
        'cle' => env('FEGGU_IA_CLE'),
        'transcription_url' => env('FEGGU_TRANSCRIPTION_URL'), // service Whisper compatible OpenAI
        'timeout' => (int) env('FEGGU_IA_TIMEOUT', 60),
        'max_tokens' => (int) env('FEGGU_IA_MAX_TOKENS', 400), // l'analyse tourne après la réponse : un modèle sur processeur peut prendre du temps
    ],

    // Assistante conversationnelle (chatbot IA) : comprend les messages libres et remplit le signalement.
    // Nécessite l'IA ci-dessus. Jamais en USSD. En SMS, à n'activer qu'avec l'envoi LAM configuré (réponse envoyée après coup).
    'assistant' => [
        'actif' => (bool) env('FEGGU_ASSISTANT', true),
        'canaux' => array_values(array_filter(explode(',', (string) env('FEGGU_ASSISTANT_CANAUX', 'web,whatsapp')))),
        'timeout' => (int) env('FEGGU_ASSISTANT_TIMEOUT', 45),
        // false : réponses = textes validés (recommandé avec un petit modèle). true : ajoute une phrase d'écoute rédigée par l'IA (modèle 7B ou plus).
        'redaction' => (bool) env('FEGGU_ASSISTANT_REDACTION', false),
        'historique' => 12,
    ],

    // Numéros d'urgence affichés aux victimes. À faire valider par GESTES avant mise en production.
    'numeros_urgence' => [
        ['nom' => 'Police secours', 'nom_wo' => 'Polis', 'numero' => '17'],
        ['nom' => 'Gendarmerie', 'nom_wo' => 'Sàndarmëri', 'numero' => '800 00 20 20'],
        ['nom' => 'SAMU', 'nom_wo' => 'SAMU', 'numero' => '1515'],
        ['nom' => 'Protection de l\'enfance', 'nom_wo' => 'Aar xale yi', 'numero' => '116'],
    ],

    // 2FA obligatoire pour ces rôles.
    'deux_facteurs_obligatoire' => array_values(array_filter(explode(',', (string) env('FEGGU_2FA_ROLES', 'admin')))),
];
