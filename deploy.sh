#!/usr/bin/env bash
# =============================================================================
# Déploiement de FEGGU V2 depuis le Mac, en une commande.
#
#   ./deploy.sh            met en ligne (ou à jour) https://feggu.org
#   ./deploy.sh demo       met en ligne la démonstration (données fictives) sur https://demo.feggu.org
#   ./deploy.sh retour     remet la v1 en ligne sur feggu.org (la V2 reste installée)
#   ./deploy.sh ia         installe l'IA locale (Ollama + petit modèle) sur le serveur de feggu.org
#
# Le script envoie le code, puis fait tout le nécessaire sur le serveur :
# base de données, .env, dépendances, tables, reprise des données v1, Nginx/Apache,
# HTTPS, sauvegardes. Le mot de passe sudo du serveur est demandé une fois.
# =============================================================================
set -euo pipefail

cd "$(dirname "$0")"

# Accès au serveur : lus dans .deploy.env (sur le Mac uniquement, jamais publié ni envoyé sur le serveur).
# À la première utilisation, le script les demande et crée ce fichier.
if [ -f .deploy.env ]; then
    # shellcheck disable=SC1091
    . ./.deploy.env
fi
if [ -z "${SERVEUR:-}" ]; then
    read -r -p "Connexion SSH au serveur (utilisateur@adresse) : " SERVEUR
    read -r -p "E-mail du premier administrateur : " ADMIN_EMAIL
    printf 'SERVEUR="%s"\nADMIN_EMAIL="%s"\n' "$SERVEUR" "$ADMIN_EMAIL" > .deploy.env
    chmod 600 .deploy.env
    echo "Enregistré dans .deploy.env (fichier local, ignoré par Git)."
fi
UTILISATEUR="${SERVEUR%@*}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@feggu.org}"
USSD_CODE="${USSD_CODE:-#2001#}"   # FEGGU est l'entrée 8 du menu USSD #2001#
USSD_CHOIX="${USSD_CHOIX:-8}"
MODE="${1:-prod}"

case "$MODE" in
    prod)   DOMAINE="${DOMAINE:-feggu.org}";      DOSSIER="/var/www/html/feggu-v2" ;;
    demo)   DOMAINE="${DOMAINE:-demo.feggu.org}"; DOSSIER="/var/www/html/feggu-demo" ;;
    retour) DOMAINE="${DOMAINE:-feggu.org}";      DOSSIER="/var/www/html/feggu-v2" ;;
    ia)     DOMAINE="${DOMAINE:-feggu.org}";      DOSSIER="/var/www/html/feggu-v2" ;;
    *) echo "Usage : ./deploy.sh [prod|demo|retour|ia]"; exit 1 ;;
esac

if [ "$MODE" = retour ]; then
    printf 'Remettre la v1 en ligne sur %s ? [o/N] ' "$DOMAINE"; read -r ok
    [ "$ok" = o ] || [ "$ok" = O ] || exit 0
    ssh -t "$SERVEUR" "sudo MODE=retour DOMAINE='$DOMAINE' DOSSIER='$DOSSIER' bash '$DOSSIER/deploiement/serveur.sh'"
    exit 0
fi

if [ "$MODE" = ia ]; then
    ssh -t "$SERVEUR" "sudo MODE=ia DOMAINE='$DOMAINE' DOSSIER='$DOSSIER' IA_MODELE='${IA_MODELE:-}' bash '$DOSSIER/deploiement/serveur.sh'"
    exit 0
fi

if [ "$MODE" = prod ]; then
    echo "Vous allez mettre la V2 en ligne sur https://$DOMAINE (la v1 sera remplacée, ses données reprises)."
    printf 'Continuer ? [o/N] '; read -r ok
    [ "$ok" = o ] || [ "$ok" = O ] || exit 0
fi

# 1. Interface : recompilée si Node est installé sur le Mac, sinon on envoie celle qui est déjà compilée.
if command -v npm >/dev/null 2>&1; then
    echo "▶ Compilation de l'interface"
    npm ci --no-audit --no-fund --silent && npm run build --silent
elif [ ! -f public/build/manifest.json ]; then
    echo "Interface non compilée et Node absent : installez Node (brew install node) puis relancez."; exit 1
fi

# 2. Dossier sur le serveur (création avec sudo si /var/www/html n'est pas modifiable par $UTILISATEUR).
echo "▶ Préparation de $DOSSIER sur le serveur"
if ! ssh "$SERVEUR" "mkdir -p '$DOSSIER' 2>/dev/null && test -w '$DOSSIER'"; then
    ssh -t "$SERVEUR" "sudo install -d -o '$UTILISATEUR' -g www-data -m 2775 '$DOSSIER'"
fi

# 3. Envoi du code (jamais le .env, les dépendances, les journaux ni les fichiers générés sur le serveur).
echo "▶ Envoi du code"
rsync -az --delete \
    --exclude '.git/' --exclude 'node_modules/' --exclude 'vendor/' \
    --exclude '.env' --exclude '.deploy.env' --exclude '.env.*.local' --exclude 'database/database.sqlite' \
    --exclude 'storage/' --exclude 'bootstrap/cache/' --exclude '.phpunit.*' --exclude '.DS_Store' \
    ./ "$SERVEUR:$DOSSIER/"

# 4. Installation / mise à jour sur le serveur.
echo "▶ Installation sur le serveur (mot de passe sudo demandé)"
ssh -t "$SERVEUR" "sudo MODE='$MODE' DOMAINE='$DOMAINE' DOSSIER='$DOSSIER' UTILISATEUR='$UTILISATEUR' ADMIN_EMAIL='$ADMIN_EMAIL' USSD_CODE='$USSD_CODE' USSD_CHOIX='$USSD_CHOIX' bash '$DOSSIER/deploiement/serveur.sh'"
