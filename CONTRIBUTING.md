# Contribuer à FEGGU

FEGGU est un logiciel libre (AGPL-3.0). Il sert à signaler des violences : chaque modification doit d'abord protéger les personnes qui l'utilisent.

## Installer en local

```bash
git clone <dépôt> feggu && cd feggu
composer install && npm ci
cp .env.example .env && php artisan key:generate
touch database/database.sqlite          # base SQLite pour le développement
php artisan migrate --seed              # référentiels + compte admin (mot de passe affiché)
echo "FEGGU_DEMO=true" >> .env && echo "FEGGU_2FA_ROLES=" >> .env
php artisan db:seed --class=DemoSeeder  # données fictives, compte demo@feggu.org / Demo-Feggu-2026
composer run dev                        # ou : php artisan serve + npm run dev
```

Pour tester les canaux sans téléphone : **Espace structures › Simulateur des canaux**.

## Règles

- **Jamais de données réelles** dans le dépôt, les tickets ou les captures : ni signalement, ni numéro, ni export de base. Utiliser les données de démonstration.
- **Jamais de secret** dans le code : identifiants SMS, WhatsApp, jetons, mots de passe vont dans `.env`.
- Les textes affichés aux victimes restent **neutres** (aucun SMS, nom d'expéditeur ou titre de page qui évoque une violence).
- Tout nouveau texte public existe en **français et en wolof** (`resources/js/lib/i18n.js`, `app/Services/Contenu/Textes.php`) ; le wolof est relu par une locutrice.
- Un écran USSD ne dépasse pas **182 caractères** (les tests le vérifient).
- Chaque correction ou fonctionnalité vient avec son test : `php artisan test` doit passer avant toute fusion.

## Organisation du code

| Dossier | Rôle |
| --- | --- |
| `app/Services/Canaux/Conversation.php` | Moteur de conversation commun (SMS, USSD, WhatsApp, chat web) |
| `app/Services/Triage.php` | Tri des urgences (règles + IA locale facultative) |
| `app/Services/Orientation.php` | Choix de la structure d'aide |
| `app/Http/Controllers/Webhooks` | Entrées des canaux |
| `resources/js/Pages` | Interface (React + Inertia) |
| `deploiement/serveur.sh` | Installation et mise à jour sur le serveur |

## Signaler une faille de sécurité

Ne pas ouvrir de ticket public : écrire à contact@mlouma.com.
