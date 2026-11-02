# FEGGU V2

> **English summary.** FEGGU ("to protect oneself, to prevent" in Wolof) is an open-source platform to report gender-based violence in Senegal and refer each case to the nearest support service. It is developed by Mlouma with the GESTES gender research lab (Gaston Berger University) and runs in production at [feggu.org](https://feggu.org).
>
> - **Alert from any phone, without an account or a name:** SMS short code, USSD, WhatsApp, missed call, voice note, and a web form that works offline. French and Wolof. A code word (SOS) raises a critical alert at any step.
> - **AI assistant** (web and WhatsApp): people write freely; a small open-weight model (Qwen2.5 3B, hosted on our own server with Ollama) extracts danger, type of violence, place and age, and the report is recorded at once. Replies are validated texts, never generated, and deterministic rules catch Wolof danger words first. USSD never waits for the AI, and if the AI is down no message is lost.
> - **Triage and referral:** urgency scoring (the AI can raise it, never lower it), automatic referral to support structures by place and service, case follow-up, anonymised dashboard.
> - **Safety by design:** quick-exit button, no trace on the phone, neutral SMS wording, encrypted personal fields, role-based access with TOTP two-factor authentication, audit log, webhook tokens.
> - **Stack:** Laravel 12, MySQL, React (Inertia), Tailwind. Tests: `php artisan test`. Licence: AGPL-3.0-or-later.
>
> The rest of this document is in French.

FEGGU (« se protéger, prévenir » en wolof) est la plateforme de signalement des violences basées sur le genre portée par le laboratoire GESTES (UGB) et Mlouma. Cette V2 remplace l'application PHP de 2014 par une plateforme Laravel 12 + MySQL, avec une interface React (Inertia) bilingue français / wolof.

## Ce que fait la V2

**Alerter sans internet ni compte, par 7 canaux**, tous reliés au même moteur de conversation :

| Canal | Point d'entrée | Statut |
| --- | --- | --- |
| SMS au 21211 (LAM) | `GET/POST /webhooks/sms` (même format que la v1 : `FROM`, `MESSAGE`) | prêt, à rebrancher chez LAM |
| USSD | `POST /webhooks/ussd` (`sessionId`, `phoneNumber`, `text`, réponse `CON`/`END`) | prêt, code USSD à obtenir |
| WhatsApp (texte + notes vocales) | `GET/POST /webhooks/whatsapp` (API Cloud de Meta) | prêt, compte WhatsApp Business à créer |
| Message vocal | `POST /webhooks/voix/message` (`from`, `recording_url`) | prêt, serveur vocal à brancher |
| Appel « bip » (appel manqué → rappel) | `POST /webhooks/voix/appel-manque` (`from`) | prêt, numéro à obtenir |
| Site web : formulaire en 3 étapes + chatbot | `/signaler`, `/parler` | prêt |
| Formulaire hors ligne | service worker + file d'attente locale | prêt |

- Un **SMS libre** (« MBOUR ma voisine est battue ») crée directement un signalement localisé, comme en v1.
- Le mot-code **SOS** déclenche une alerte critique à n'importe quelle étape, sur tous les canaux.
- Si la personne répond qu'elle est **en danger**, l'alerte est créée tout de suite, avant la fin des questions.
- Les noms de lieux en wolof sont reconnus (Mbuur, Ndakaaru, Tiis, Ndar…).

**Tri automatique des urgences** (`app/Services/Triage.php`) : règles (danger immédiat, victime mineure, gravité du type de violence, indices dans le récit en français et en wolof, mot-code SOS). Un modèle d'IA hébergé localement peut compléter l'analyse du récit ; il ne peut jamais faire baisser une urgence.

**Orientation automatique** (`app/Services/Orientation.php`) vers la structure la plus adaptée : proximité (département, puis région) et correspondance entre besoins et services (juridique, médical, psychologique, hébergement, protection de l'enfant…). Les agents sont prévenus par un SMS **qui ne contient aucun détail** sur la victime.

**Back-office des structures** (`/espace`) : tableau de bord, file des signalements triés par urgence, dossier avec raisons du tri, orientation, prise de contact, suivi de la prise en charge, historique ; simulateur des canaux SMS / USSD / WhatsApp pour les démonstrations ; gestion des comptes ; journal d'accès.

## Sécurité : ce qui change par rapport à la v1

| v1 | V2 |
| --- | --- |
| Mots de passe en MD5 | bcrypt ; les comptes v1 sont convertis automatiquement à la 1re connexion |
| Identifiants du fournisseur SMS écrits dans le code | dans `.env` uniquement |
| Webhook SMS ouvert à tous | jeton partagé ou liste d'IP obligatoire, refus par défaut en production |
| Message brut de la victime renvoyé par SMS à tous les administrateurs | SMS neutre avec la seule référence |
| Erreurs PHP affichées publiquement | désactivées en production (`APP_DEBUG=false`) |
| Données en clair | récits, numéros et conversations chiffrés en base |
| Accès par simple connexion | rôles (admin, superviseur régional, agent de structure), double authentification (TOTP) obligatoire pour les admins, journal de chaque consultation |
| Ethnie et nationalité demandées | supprimées (minimisation) |

Côté victime : bouton « Quitter vite » (et double Échap), pages sensibles non mises en cache ni indexées, référence jamais dans l'URL, application installée sous le nom neutre « Infos », SMS signés d'un expéditeur neutre.

## Mettre en ligne (depuis le Mac)

Une seule commande, lancée depuis le dossier du projet :

```bash
./deploy.sh            # met en ligne ou met à jour https://feggu.org
./deploy.sh demo       # démonstration avec données fictives sur https://demo.feggu.org
./deploy.sh retour     # remet la v1 en ligne sur feggu.org (la V2 reste installée)
```

Le script envoie le code par rsync dans `/var/www/html/feggu-v2`, puis lance `deploiement/serveur.sh` avec sudo (mot de passe demandé une fois ; le coller plutôt que le taper). **Au premier lancement**, il :

1. prépare la base : une base dédiée `feggu_v2` si MySQL est administrable, sinon **la base de la v1**, avec son compte (les tables de la V2 n'ont aucun nom en commun avec celles de la v1, qui ne sont pas modifiées ; la démo y ajoute le préfixe `demo_`). Ne jamais lancer `migrate:fresh` dans ce cas : cela effacerait aussi les tables de la v1 ;
2. écrit le `.env` de production (`APP_DEBUG=false`, cookies sécurisés, jeton des webhooks, clé de chiffrement) ;
3. lit les accès de l'ancienne base dans `/var/www/html/feggu/core/config.php` et **reprend les données de la v1** (comptes, cas, alertes, suivis) ;
4. retrouve dans les journaux du serveur l'adresse IP de l'agrégateur SMS, pour que l'ancienne adresse `alert-info` du 21211 continue de fonctionner sans rien changer chez LAM ;
5. installe les dépendances, crée les tables, importe les référentiels, crée votre compte administrateur ;
6. sauvegarde la configuration web de la v1, installe celle de la V2 (Nginx ou Apache, détecté automatiquement), la **teste avant de basculer** et revient en arrière si elle est invalide ;
7. réutilise le certificat HTTPS existant (ou en demande un avec certbot) ;
8. programme une sauvegarde quotidienne de la base (14 jours, dans `/var/backups`).

Il affiche à la fin le mot de passe provisoire de l'administrateur (double authentification demandée à la première connexion), l'adresse du webhook SMS et la clé `APP_KEY`. **Copiez cette clé dans un coffre-fort** : sans elle, les récits chiffrés sont illisibles.

**Les fois suivantes**, le même `./deploy.sh` envoie le nouveau code, met à jour les dépendances et la base, sans toucher au `.env` ni aux données. Une installation interrompue reprend là où elle s'était arrêtée.

Prérequis sur le serveur : PHP 8.2+ avec PHP-FPM, Composer, MySQL/MariaDB, Nginx ou Apache (testé avec les deux, avec et sans HTTPS, avec et sans accès administrateur à MySQL). Sur le Mac : `ssh` et `rsync`, et Node seulement pour recompiler l'interface (la version compilée est fournie dans `public/build`).

### Démonstration

`./deploy.sh demo` installe une copie séparée (dossier `/var/www/html/feggu-demo`, base `feggu_demo`) avec 34 antennes et 90 signalements **fictifs**, et le compte `demo@feggu.org` / `Demo-Feggu-2026` (sur la démo, la gestion des comptes, des structures et de la double authentification est bloquée, et les adresses IP du journal sont masquées). C'est l'adresse à donner aux évaluateurs : ils ne voient jamais de données réelles. Il faut d'abord créer l'enregistrement DNS `A demo` vers l'adresse IP du serveur.

### Installation à la main (développement)

```bash
cp .env.example .env && composer install && php artisan key:generate
php artisan migrate --seed && php artisan db:seed --class=DemoSeeder   # avec FEGGU_DEMO=true
```

## Brancher les canaux

- **Jeton** : `FEGGU_WEBHOOK_TOKEN=$(openssl rand -hex 32)`. Chaque URL donnée à un fournisseur se termine par `?token=…`. Si un fournisseur ne peut pas ajouter de paramètre, mettre son IP dans `FEGGU_WEBHOOK_IPS`.
- **SMS 21211 (LAM)** : les anciennes adresses de la v1 (`/alert-info…` et `index.php?p=alert-info`) restent actives pour l'IP de LAM. À terme, demander à LAM de pointer le numéro court vers `https://feggu.org/webhooks/sms?token=…`. L'ancien format (`id`, `FROM`, `MESSAGE`) est accepté tel quel. Un appel refusé est noté dans `storage/logs` avec son IP. Pour les réponses, `FEGGU_SMS_DRIVER=lam` et les identifiants `LAM_SMS_*`. Faire valider par LAM le format exact de leur API d'envoi (`app/Services/Canaux/EnvoiSms.php`).
- **USSD** : le contrôleur suit le format le plus courant des agrégateurs (`sessionId`, `phoneNumber`, `text` cumulé `1*2*3`). Un écran est limité à ~180 caractères ; les tests le vérifient.
- **WhatsApp** : dans Meta for Developers, URL de rappel `https://feggu.org/webhooks/whatsapp?token=…`, jeton de vérification = `WHATSAPP_VERIFY_TOKEN`, puis `FEGGU_WHATSAPP_DRIVER=meta`. Utiliser un nom de compte neutre et activer les messages éphémères.
- **Voix et appel « bip »** : le serveur vocal (LAM ou autre) appelle les deux URL `/webhooks/voix/*`. La gratuité pour l'appelant se négocie avec les opérateurs.

## IA locale (facultatif)

Sans IA, tout fonctionne avec les règles. Avec un modèle local, les données ne quittent pas le serveur. Installation en une commande depuis le Mac : `./deploy.sh ia` (Ollama + `qwen2.5:3b` si le serveur a 8 Go de mémoire, `qwen2.5:1.5b` à partir de 4 Go).

L'IA sert à deux choses :

1. **Tri** : elle relit chaque récit après la réponse à la victime et peut relever l'urgence, jamais la baisser.
2. **Assistante conversationnelle** (site et WhatsApp par défaut) : la personne écrit librement, en français ou en wolof ; l'assistante comprend, enregistre le signalement dès qu'une violence est décrite, puis ne pose que les questions dont la réponse manque (danger, qui, lieu, âge, rappel).

Choix de conception de l'assistante, issus d'essais réels avec `qwen2.5:3b` :

- **L'IA comprend, le serveur répond.** Laissé libre, un petit modèle écrit un wolof incompréhensible, se répète et peut inventer des numéros. Il est en revanche correct pour extraire des informations d'un message. Les réponses sont donc des textes validés (français et wolof, à faire relire par GESTES) et l'IA ne fait que lire.
- **Les règles passent d'abord** : mots wolof du danger (*léegi*, *paaka*...), âge (« 13 at »), proche concerné (*sama doom*, « ma voisine »). Le modèle les rate souvent en wolof.
- **Un « danger » vu par l'IA seule est vérifié** en posant la question à la personne, pour ne pas classer en critique des faits passés.
- **Le récit enregistré, ce sont les mots de la personne**, jamais un résumé de l'IA. Les numéros de téléphone en sont retirés.
- **Jamais en USSD**, et si l'IA ne répond pas, le message devient quand même un signalement.

Réglages dans `.env` : `FEGGU_ASSISTANT_CANAUX=web,whatsapp` (ajouter `sms` quand l'envoi LAM est en place ; la réponse part alors après coup), `FEGGU_ASSISTANT_TIMEOUT=45`. Avec un modèle de 7B ou plus, `FEGGU_ASSISTANT_REDACTION=true` ajoute une phrase d'écoute rédigée par l'IA.

Temps de réponse mesuré sur 2 processeurs sans carte graphique : 10 à 15 s quand l'IA lit le message, immédiat pour les réponses simples (« 1 », « waaw », un nom de ville).

La transcription des notes vocales utilise un service compatible Whisper (`FEGGU_TRANSCRIPTION_URL`). La reconnaissance du wolof existe mais reste imparfaite (et encore moins mûre pour le sérère ou le diola) : c'est un chantier du projet financé. En attendant, chaque message vocal crée une alerte qu'un agent écoute.

## Reprendre les données de la v1

Sur le serveur, avec l'ancienne base accessible (`V1_DB_*` dans `.env`) :

```bash
php artisan feggu:import-v1 --simulation   # compte ce qui serait importé
php artisan feggu:import-v1                # comptes, 192 cas, 49 alertes SMS, suivis
```

Les données ne sortent pas du serveur ; récits et numéros sont chiffrés à l'écriture. Les anciens cas sont importés comme « clos » (option `--ouverts` pour les garder ouverts). Les numéros v1 sont conservés mais jamais utilisés pour un SMS, faute de consentement connu. Relancer la commande ne crée pas de doublons.

## Avant la mise en production

- [ ] Faire relire tous les textes wolof (`resources/js/lib/i18n.js`, `app/Services/Contenu/Textes.php`) par GESTES et des locutrices.
- [ ] Valider les numéros d'urgence (`config/feggu.php`) et la page « Aide et droits ».
- [ ] Fixer avec GESTES la durée de conservation et faire la déclaration auprès de la CDP.
- [ ] Changer le mot de passe SMS exposé dans la v1.
- [ ] Sauvegardes chiffrées de la base, hors du serveur.

## Tests

```bash
php artisan test    # 35 tests : canaux, tri, orientation, droits d'accès, 2FA, reprise v1, confidentialité
```

## Organisation du code

| Dossier | Contenu |
| --- | --- |
| `app/Services/Canaux/Conversation.php` | moteur de conversation commun à tous les canaux |
| `app/Services/Triage.php`, `Orientation.php`, `Signalements.php` | tri, orientation, création des signalements |
| `app/Services/Contenu/Textes.php` | textes du chatbot FR / wolof |
| `app/Http/Controllers/Webhooks` | adaptateurs SMS, USSD, WhatsApp, voix |
| `app/Console/Commands` | import des référentiels et des données v1, purge de la démo |
| `resources/js/Pages/Public` | site public (accueil, signalement, chat, suivi, aide) |
| `resources/js/Pages/Admin` | back-office |

## Licence

FEGGU est un logiciel libre sous licence **GNU Affero General Public License v3.0 ou ultérieure** (fichier `LICENSE`). Toute version modifiée mise en ligne doit publier son code source : renseigner `FEGGU_CODE_SOURCE_URL` (lien affiché en bas du site).

Le code est public ; **les données ne le sont jamais** (signalements, comptes, journaux, `.env`). Avant de publier le dépôt, vérifier qu'aucun secret n'y figure (`git log -p | grep -iE "password|token|secret"`).

Titulaires des droits : Mlouma, et le laboratoire GESTES (UGB) selon l'accord écrit à signer entre les deux partenaires.