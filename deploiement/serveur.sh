#!/usr/bin/env bash
# =============================================================================
# FEGGU V2 : installation et mise à jour SUR LE VPS.
# Lancé par ./deploy.sh depuis le Mac (avec sudo). Ne pas lancer à la main
# sauf : sudo MODE=retour bash serveur.sh   (revenir à la v1)
#
# Variables (fournies par deploy.sh) :
#   MODE         prod | demo | retour
#   DOMAINE      feggu.org (prod) ou demo.feggu.org (demo)
#   DOSSIER      dossier de l'application sur le serveur
#   UTILISATEUR  compte SSH propriétaire du code (celui qui lance deploy.sh)
#   ADMIN_EMAIL  e-mail du premier compte administrateur
#   V1_DOSSIER   code de la v1 (lecture de sa configuration pour reprendre les données)
# =============================================================================
set -Eeuo pipefail

MODE="${MODE:-prod}"
DOMAINE="${DOMAINE:-feggu.org}"
DOSSIER="${DOSSIER:-/var/www/html/feggu-v2}"
UTILISATEUR="${UTILISATEUR:-${SUDO_USER:-}}"
[ -n "$UTILISATEUR" ] || { echo "UTILISATEUR manquant (compte SSH propriétaire du code)."; exit 1; }
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@feggu.org}"
V1_DOSSIER="${V1_DOSSIER:-/var/www/html/feggu}"
SAUVEGARDES="/root/feggu-v2-sauvegardes"
NOM="feggu-v2-${DOMAINE}"
HORODATAGE="$(date +%Y%m%d-%H%M%S)"

vert() { printf '\033[32m%s\033[0m\n' "$*"; }
jaune() { printf '\033[33m%s\033[0m\n' "$*"; }
rouge() { printf '\033[31m%s\033[0m\n' "$*"; }
etape() { printf '\n\033[1m▶ %s\033[0m\n' "$*"; }
trap 'rouge "Échec à la ligne $LINENO. Rien n est basculé tant que la configuration web n a pas été validée."' ERR

[ "$(id -u)" -eq 0 ] || { rouge "Ce script doit être lancé avec sudo."; exit 1; }
mkdir -p "$SAUVEGARDES" && chmod 700 "$SAUVEGARDES"

# ---------------------------------------------------------------------------
# Serveur web utilisé (Nginx ou Apache)
# ---------------------------------------------------------------------------
if ss -ltnp 2>/dev/null | grep -E ':(80|443)\s' | grep -q nginx; then
    WEB=nginx
elif ss -ltnp 2>/dev/null | grep -E ':(80|443)\s' | grep -qE 'apache2|httpd'; then
    WEB=apache
elif [ "$MODE" != ia ]; then
    rouge "Ni Nginx ni Apache n'écoute sur le port 80/443."; exit 1
fi

# ---------------------------------------------------------------------------
# Retour à la v1
# ---------------------------------------------------------------------------
if [ "$MODE" = "retour" ]; then
    etape "Retour à la v1 sur $DOMAINE ($WEB)"
    DERNIERE="$(ls -1dt "$SAUVEGARDES"/vhost-v1-* 2>/dev/null | head -1 || true)"
    [ -n "$DERNIERE" ] || { rouge "Aucune sauvegarde de la configuration v1 trouvée dans $SAUVEGARDES."; exit 1; }
    if [ "$WEB" = nginx ]; then
        rm -f "/etc/nginx/sites-enabled/$NOM"
        for f in "$DERNIERE"/*; do ln -sf "/etc/nginx/sites-available/$(basename "$f")" "/etc/nginx/sites-enabled/$(basename "$f")"; done
        nginx -t && systemctl reload nginx
    else
        a2dissite "$NOM" >/dev/null || true
        for f in "$DERNIERE"/*; do a2ensite "$(basename "$f" .conf)" >/dev/null; done
        apache2ctl configtest && systemctl reload apache2
    fi
    vert "La v1 est de nouveau en ligne sur $DOMAINE. La V2 reste installée dans $DOSSIER."
    exit 0
fi

regler_env() { # regler_env CLE valeur : remplace la ligne (même commentée) ou l'ajoute
    local cle="$1" val="$2"
    if grep -qE "^#?\s*$cle=" .env; then
        sed -i "s|^#\?\s*$cle=.*|$cle=$(printf '%s' "$val" | sed 's/[&|\\]/\\&/g')|" .env
    else
        printf '%s=%s\n' "$cle" "$val" >> .env
    fi
}

# ---------------------------------------------------------------------------
# IA locale : installe Ollama et un petit modèle, adapté à la mémoire du serveur
# ---------------------------------------------------------------------------
if [ "$MODE" = ia ]; then
    [ -f "$DOSSIER/.env" ] || { rouge "Installez d'abord FEGGU (./deploy.sh), puis l'IA."; exit 1; }
    cd "$DOSSIER"
    etape "Mémoire et disque du serveur"
    MEM_GO="$(awk '/MemTotal/ {printf "%d", $2/1024/1024 + 0.5}' /proc/meminfo)"
    DISQUE_GO="$(df -BG --output=avail / | tail -1 | tr -dc '0-9')"
    echo "Mémoire : ${MEM_GO} Go · disque libre : ${DISQUE_GO} Go"
    if [ -n "${IA_MODELE:-}" ]; then
        MODELE="$IA_MODELE"
    elif [ "$MEM_GO" -ge 8 ]; then
        MODELE="qwen2.5:3b"      # ~2 Go, bon multilingue pour sa taille
    elif [ "$MEM_GO" -ge 4 ]; then
        MODELE="qwen2.5:1.5b"    # ~1 Go
    else
        rouge "Mémoire insuffisante (${MEM_GO} Go) pour un modèle local : FEGGU continue avec le tri par règles."; exit 1
    fi
    [ "$DISQUE_GO" -ge 5 ] || { rouge "Il faut au moins 5 Go libres sur le disque."; exit 1; }

    if ! command -v ollama >/dev/null; then
        etape "Installation d'Ollama"
        command -v zstd >/dev/null || apt-get install -y -qq zstd >/dev/null   # exigé par l'installateur d'Ollama
        curl -fsSL https://ollama.com/install.sh | sh
    fi
    # Un seul modèle chargé, une requête à la fois : le serveur garde de la mémoire pour les autres sites.
    mkdir -p /etc/systemd/system/ollama.service.d
    cat > /etc/systemd/system/ollama.service.d/feggu.conf <<'CONF'
[Service]
Environment="OLLAMA_HOST=127.0.0.1:11434"
Environment="OLLAMA_NUM_PARALLEL=1"
Environment="OLLAMA_MAX_LOADED_MODELS=1"
Environment="OLLAMA_KEEP_ALIVE=30m"
CONF
    systemctl daemon-reload
    systemctl enable ollama >/dev/null 2>&1 || true
    systemctl restart ollama
    for _ in $(seq 1 30); do curl -s -o /dev/null http://127.0.0.1:11434/ && break; sleep 1; done

    etape "Téléchargement du modèle $MODELE"
    ollama pull "$MODELE"

    etape "Essai sur un récit en wolof"
    DEBUT=$(date +%s)
    REPONSE="$(curl -s --max-time 180 http://127.0.0.1:11434/v1/chat/completions -H 'Content-Type: application/json' -d "{
        \"model\": \"$MODELE\", \"temperature\": 0, \"response_format\": {\"type\": \"json_object\"},
        \"messages\": [
          {\"role\": \"system\", \"content\": \"Réponds en JSON {\\\"danger_immediat\\\": bool, \\\"categorie_id\\\": 1-8, \\\"raison\\\": texte}. Catégorie 2 = violence physique.\"},
          {\"role\": \"user\", \"content\": \"Sama jëkkër mi ngi ci kër gi léegi, dafa may dóor ak paaka.\"}
        ]}" || true)"
    DUREE=$(( $(date +%s) - DEBUT ))
    echo "Réponse en ${DUREE} s : $(printf '%s' "$REPONSE" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["choices"][0]["message"]["content"] ?? "aucune réponse";')"

    regler_env FEGGU_IA_URL "http://127.0.0.1:11434/v1"
    regler_env FEGGU_IA_MODELE "$MODELE"
    regler_env FEGGU_IA_TIMEOUT 120
    sudo -u www-data -H php artisan optimize:clear >/dev/null
    sudo -u www-data -H php artisan optimize >/dev/null
    vert "IA locale active ($MODELE). Elle analyse chaque récit après la réponse à la victime ; sans elle, les règles continuent de fonctionner."
    echo "Pour la retirer : FEGGU_IA_URL= dans $DOSSIER/.env puis ./deploy.sh"
    exit 0
fi

# ---------------------------------------------------------------------------
# Vérifications
# ---------------------------------------------------------------------------
etape "Vérifications"
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || { rouge "PHP 8.2 minimum est requis (version actuelle : $(php -r 'echo PHP_VERSION;'))."; exit 1; }
for ext in pdo_mysql mbstring xml curl openssl tokenizer ctype fileinfo; do
    php -m | grep -qi "^$ext$" || { rouge "Extension PHP manquante : $ext (apt install php-${ext/pdo_/})"; exit 1; }
done
command -v composer >/dev/null || { rouge "Composer est introuvable."; exit 1; }
command -v mysql >/dev/null || { rouge "Client MySQL introuvable."; exit 1; }
[ -f "$DOSSIER/artisan" ] || { rouge "Code introuvable dans $DOSSIER (l'envoi rsync a-t-il échoué ?)."; exit 1; }
PHP_VERSION_COURTE="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
FPM_SOCKET="$(ls -1 /run/php/php"$PHP_VERSION_COURTE"-fpm.sock 2>/dev/null || ls -1 /run/php/php8.[2-9]-fpm.sock 2>/dev/null | sort -V | tail -1 || true)"
vert "PHP $PHP_VERSION_COURTE, serveur web : $WEB${FPM_SOCKET:+, PHP-FPM : $FPM_SOCKET}"
[ "$WEB" = apache ] || [ -n "$FPM_SOCKET" ] || { rouge "Aucun socket PHP-FPM ≥ 8.2 trouvé dans /run/php (apt install php$PHP_VERSION_COURTE-fpm)."; exit 1; }
if [ "$WEB" = apache ] && [ -z "$FPM_SOCKET" ]; then
    MOD_PHP="$(ls -1 /etc/apache2/mods-enabled/php*.load 2>/dev/null | sed -E 's/.*php([0-9.]+)\.load/\1/' | head -1 || true)"
    php -r 'exit(version_compare($argv[1], "8.2", ">=") ? 0 : 1);' "${MOD_PHP:-0}" \
        || { rouge "Apache utilise PHP ${MOD_PHP:-inconnu} ; il faut PHP-FPM 8.2+ (apt install php$PHP_VERSION_COURTE-fpm)."; exit 1; }
fi

cd "$DOSSIER"
en_tant_que_web() { sudo -u www-data -H php artisan "$@"; }

PREMIERE=0
[ -f .env ] || PREMIERE=1
# Installation terminée seulement quand ce marqueur existe (une installation interrompue reprend où elle s'était arrêtée).
INSTALLEE=0
[ -f storage/app/private/.feggu-installee ] && INSTALLEE=1

# ---------------------------------------------------------------------------
# Première installation : base de données et .env
# ---------------------------------------------------------------------------
if [ "$PREMIERE" = 1 ]; then
    # Accès à la base de la v1, lus dans sa configuration (reprise des données, et repli si MySQL n'est pas administrable).
    V1_HOTE=""; V1_BASE=""; V1_UTIL=""; V1_MDP=""
    if [ -f "$V1_DOSSIER/core/config.php" ]; then
        V1_LIGNE="$(php -r '
            $h=$b=$u=$p=null;
            $src = file_get_contents($argv[1]);
            foreach (["host"=>"h","baseSms"=>"b","userSms"=>"u","mdpSms"=>"p"] as $var=>$k) {
                if (preg_match_all("/^\s*\\$".$var."\s*=\s*[\x27\"]([^\x27\"]*)[\x27\"]/m", $src, $m)) { $$k = end($m[1]); }
            }
            echo implode("\t", [$h ?: "localhost", $b ?: "feggu", $u ?: "feggu", (string) $p]);
        ' "$V1_DOSSIER/core/config.php")"
        IFS=$'\t' read -r V1_HOTE V1_BASE V1_UTIL V1_MDP <<< "$V1_LIGNE"
    fi

    etape "Première installation ($MODE) : base de données"
    PREFIXE=""
    if mysql -e 'SELECT 1' >/dev/null 2>&1; then
        # MySQL administrable : une base dédiée à la V2.
        BASE=$([ "$MODE" = demo ] && echo feggu_demo || echo feggu_v2)
        UTIL_BASE="$BASE"
        MDP_BASE="$(openssl rand -hex 20)"
        mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$BASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$BASE'@'localhost' IDENTIFIED BY '$MDP_BASE';
ALTER USER '$BASE'@'localhost' IDENTIFIED BY '$MDP_BASE';
GRANT ALL PRIVILEGES ON \`$BASE\`.* TO '$BASE'@'localhost';
FLUSH PRIVILEGES;
SQL
        vert "Base dédiée $BASE prête."
    elif [ -n "$V1_MDP" ]; then
        # Pas d'accès administrateur à MySQL : la V2 s'installe dans la base de la v1, avec le même compte.
        # Aucun conflit : les tables v1 s'appellent feg_* et ml_*, celles de la V2 non. La démo utilise en plus le préfixe demo_.
        BASE="$V1_BASE"; UTIL_BASE="$V1_UTIL"; MDP_BASE="$V1_MDP"
        [ "$MODE" = demo ] && PREFIXE="demo_"
        if ! MYSQL_PWD="$MDP_BASE" mysql -u "$UTIL_BASE" "$BASE" -e "CREATE TABLE ${PREFIXE}feggu_test_droits (id INT); DROP TABLE ${PREFIXE}feggu_test_droits;" 2>/tmp/feggu-mysql.log; then
            rouge "Le compte MySQL « $UTIL_BASE » ne peut pas créer de tables dans « $BASE » :"; cat /tmp/feggu-mysql.log; exit 1
        fi
        vert "Base de la v1 utilisée : $BASE (compte $UTIL_BASE)${PREFIXE:+, tables préfixées $PREFIXE}. Les tables de la v1 ne sont pas modifiées."
    else
        rouge "Impossible de préparer la base : MySQL n'est pas accessible en administrateur et la configuration v1 est introuvable."
        exit 1
    fi

    etape "Fichier .env"
    cp .env.example .env
    MDP_ADMIN="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)Aa1"
    JETON="$(openssl rand -hex 32)"
    regler_env APP_ENV production
    regler_env APP_DEBUG false
    regler_env APP_URL "https://$DOMAINE"
    regler_env LOG_LEVEL warning
    regler_env DB_CONNECTION mysql
    regler_env DB_HOST localhost
    regler_env DB_PORT 3306
    regler_env DB_DATABASE "$BASE"
    regler_env DB_USERNAME "$UTIL_BASE"
    regler_env DB_PASSWORD "$MDP_BASE"
    regler_env DB_PREFIX "$PREFIXE"
    regler_env SESSION_SECURE_COOKIE true
    regler_env SESSION_ENCRYPT true
    regler_env FEGGU_WEBHOOK_TOKEN "$JETON"
    regler_env FEGGU_ADMIN_EMAIL "$ADMIN_EMAIL"
    regler_env FEGGU_ADMIN_PASSWORD "$MDP_ADMIN"

    if [ "$MODE" = demo ]; then
        regler_env FEGGU_DEMO true
        regler_env FEGGU_2FA_ROLES ""
    else
        regler_env FEGGU_DEMO false
        regler_env FEGGU_2FA_ROLES admin
        if [ -n "$V1_MDP" ]; then
            regler_env V1_DB_HOST "$V1_HOTE"
            regler_env V1_DB_DATABASE "$V1_BASE"
            regler_env V1_DB_USERNAME "$V1_UTIL"
            regler_env V1_DB_PASSWORD "$V1_MDP"
            vert "Accès à la base v1 repris de $V1_DOSSIER/core/config.php"
        else
            jaune "Configuration v1 introuvable : la reprise des données sera à faire plus tard (php artisan feggu:import-v1)."
        fi

    fi
    chmod 640 .env
fi

# Code USSD affiché sur le site (FEGGU est l'entrée USSD_CHOIX du menu USSD_CODE) : deploy.sh fait foi à chaque déploiement.
if [ -n "${USSD_CODE:-}" ]; then
    regler_env FEGGU_CODE_USSD "\"$USSD_CODE\""
    regler_env FEGGU_USSD_CHOIX "${USSD_CHOIX:-}"
fi

# IP de l'agrégateur SMS (production) : retrouvée dans les journaux des anciens appels « alert-info », s'il y en a.
if [ "$MODE" = prod ] && ! grep -q '^FEGGU_WEBHOOK_IPS=.\+' .env; then
    IPS_SMS="$( (zgrep -h 'alert-info' /var/log/nginx/access.log* /var/log/apache2/access.log* /var/log/apache2/*access*.log* 2>/dev/null || true) \
        | awk '{for (i = 1; i <= 3; i++) if ($i ~ /^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/ || $i ~ /^[0-9a-fA-F]*:[0-9a-fA-F:]+$/) { print $i; break }}' \
        | grep -vE '^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.|::1$)' | sort | uniq -c | sort -rn \
        | awk '$1>=2 {print $2}' | head -3 | paste -sd, - || true)"
    if [ -n "$IPS_SMS" ]; then
        regler_env FEGGU_WEBHOOK_IPS "$IPS_SMS"
        vert "Adresse(s) de l'agrégateur SMS autorisée(s) : $IPS_SMS"
    else
        jaune "Aucun appel récent du 21211 dans les journaux : donnez à LAM l'adresse du webhook affichée à la fin."
    fi
fi

# ---------------------------------------------------------------------------
# Droits, dépendances, base
# ---------------------------------------------------------------------------
etape "Droits sur les fichiers"
chown -R "$UTILISATEUR":www-data "$DOSSIER"
# Code lisible par le serveur web, jamais modifiable par lui ; rien pour les autres comptes.
chmod -R u+rwX,g+rX,g-w,o-rwx "$DOSSIER"
chmod 640 .env
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/private bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX,o-rwx storage bootstrap/cache

etape "Dépendances PHP (Composer)"
# Scripts Composer désactivés : la découverte des paquets écrit dans storage/, elle est lancée ensuite par le serveur web.
sudo -u "$UTILISATEUR" -H composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts --working-dir="$DOSSIER"
en_tant_que_web package:discover

if [ "$INSTALLEE" = 0 ]; then
    etape "Clé de chiffrement, tables, référentiels"
    # Clé générée ici (le serveur web n'a pas le droit d'écrire dans .env).
    grep -q '^APP_KEY=base64:' .env || regler_env APP_KEY "base64:$(openssl rand -base64 32)"
    en_tant_que_web migrate --force
    en_tant_que_web db:seed --force
    if [ "$MODE" = demo ]; then
        en_tant_que_web db:seed --class=DemoSeeder --force
    elif grep -q '^V1_DB_PASSWORD=.\+' .env; then
        etape "Reprise des données de la v1"
        en_tant_que_web feggu:import-v1 || jaune "Reprise v1 incomplète : relancer « php artisan feggu:import-v1 » (aucun doublon ne sera créé)."
    fi
else
    etape "Mise à jour de la base"
    en_tant_que_web migrate --force
fi

etape "Optimisation"
en_tant_que_web optimize:clear >/dev/null
en_tant_que_web optimize

# ---------------------------------------------------------------------------
# Configuration web (une seule fois) : sauvegarde de l'ancienne, test, puis bascule
# ---------------------------------------------------------------------------
CERT="/etc/letsencrypt/live/$DOMAINE"
[ -f "$CERT/fullchain.pem" ] || CERT="$( (grep -ls "$DOMAINE" /etc/letsencrypt/renewal/*.conf 2>/dev/null || true) | head -1 | xargs -r basename | sed 's/\.conf$//' | sed 's|^\(.\)|/etc/letsencrypt/live/\1|')"
[ -n "$CERT" ] && [ -f "$CERT/fullchain.pem" ] || CERT=""
NOMS="$DOMAINE"
[ "$MODE" = prod ] && NOMS="$DOMAINE www.$DOMAINE"

if [ "$WEB" = nginx ] && [ ! -e "/etc/nginx/sites-enabled/$NOM" ]; then
    etape "Configuration Nginx pour $DOMAINE"
    V6=""; [ -s /proc/net/if_inet6 ] && V6=1   # écouter en IPv6 seulement si le serveur en a
    ANCIENS="$(grep -lsE "server_name[^;]*\b$DOMAINE\b" /etc/nginx/sites-enabled/* | grep -v "/$NOM$" || true)"
    if [ -n "$ANCIENS" ]; then
        mkdir -p "$SAUVEGARDES/vhost-v1-$HORODATAGE"
        for f in $ANCIENS; do cp -L "$f" "$SAUVEGARDES/vhost-v1-$HORODATAGE/$(basename "$f")"; done
    fi
    {
        echo "# FEGGU V2 ($MODE) : généré par deploiement/serveur.sh le $HORODATAGE"
        if [ -n "$CERT" ]; then
            echo "server { listen 80;${V6:+ listen [::]:80;} server_name $NOMS; return 301 https://\$host\$request_uri; }"
            echo "server {"
            echo "    listen 443 ssl http2;${V6:+ listen [::]:443 ssl http2;}"
            echo "    ssl_certificate $CERT/fullchain.pem;"
            echo "    ssl_certificate_key $CERT/privkey.pem;"
            echo "    add_header Strict-Transport-Security \"max-age=31536000\" always;"
        else
            echo "server {"
            echo "    listen 80;${V6:+ listen [::]:80;}"
        fi
        cat <<NGINX
    server_name $NOMS;
    root $DOSSIER/public;
    index index.php;
    charset utf-8;
    server_tokens off;
    client_max_body_size 8m;

    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer" always;

    # Les anciennes adresses du 21211 (alert-info) sont traitées par l'application elle-même.
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /sw.js { add_header Cache-Control "no-cache"; try_files \$uri =404; }
    location ^~ /build/ { expires 1y; add_header Cache-Control "public, immutable"; try_files \$uri =404; }

    location ~ \.php\$ {
        fastcgi_pass unix:$FPM_SOCKET;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known) { deny all; }
}
NGINX
    } > "/etc/nginx/sites-available/$NOM"

    # Bascule : on désactive l'ancien site, on active la V2, et on revient en arrière si la configuration est invalide.
    for f in $ANCIENS; do rm -f "$f"; done
    ln -sf "/etc/nginx/sites-available/$NOM" "/etc/nginx/sites-enabled/$NOM"
    if ! nginx -t 2>/tmp/nginx-feggu.log; then
        rouge "Configuration Nginx invalide, retour à l'état précédent :"; cat /tmp/nginx-feggu.log
        rm -f "/etc/nginx/sites-enabled/$NOM"
        for f in $ANCIENS; do ln -sf "/etc/nginx/sites-available/$(basename "$f")" "$f"; done
        nginx -t && systemctl reload nginx; exit 1
    fi
    systemctl reload nginx
    vert "Nginx sert maintenant la V2 sur $DOMAINE.${ANCIENS:+ Ancienne configuration sauvegardée dans $SAUVEGARDES/vhost-v1-$HORODATAGE}"
elif [ "$WEB" = apache ] && [ ! -e "/etc/apache2/sites-enabled/$NOM.conf" ]; then
    etape "Configuration Apache pour $DOMAINE"
    a2enmod rewrite headers ssl proxy_fcgi setenvif >/dev/null
    ANCIENS="$(grep -lsiE "Server(Name|Alias).*\b$DOMAINE\b" /etc/apache2/sites-enabled/*.conf | grep -v "/$NOM.conf$" || true)"
    if [ -n "$ANCIENS" ]; then
        mkdir -p "$SAUVEGARDES/vhost-v1-$HORODATAGE"
        for f in $ANCIENS; do cp -L "$f" "$SAUVEGARDES/vhost-v1-$HORODATAGE/$(basename "$f")"; done
    fi
    GESTION_PHP=""
    [ -n "$FPM_SOCKET" ] && GESTION_PHP="<FilesMatch \\.php\$>
        SetHandler \"proxy:unix:$FPM_SOCKET|fcgi://localhost\"
    </FilesMatch>"
    BLOC="    ServerName $DOMAINE
    $([ "$MODE" = prod ] && echo "ServerAlias www.$DOMAINE")
    DocumentRoot $DOSSIER/public
    <Directory $DOSSIER/public>
        AllowOverride All
        Require all granted
    </Directory>
    $GESTION_PHP
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set Referrer-Policy no-referrer
    ServerSignature Off"
    {
        echo "# FEGGU V2 ($MODE) : généré par deploiement/serveur.sh le $HORODATAGE"
        if [ -n "$CERT" ]; then
            printf '<VirtualHost *:80>\n    ServerName %s\n    %s\n    Redirect permanent / https://%s/\n</VirtualHost>\n' "$DOMAINE" "$([ "$MODE" = prod ] && echo "ServerAlias www.$DOMAINE")" "$DOMAINE"
            printf '<VirtualHost *:443>\n%s\n    SSLEngine on\n    SSLCertificateFile %s/fullchain.pem\n    SSLCertificateKeyFile %s/privkey.pem\n    Header always set Strict-Transport-Security "max-age=31536000"\n</VirtualHost>\n' "$BLOC" "$CERT" "$CERT"
        else
            printf '<VirtualHost *:80>\n%s\n</VirtualHost>\n' "$BLOC"
        fi
    } > "/etc/apache2/sites-available/$NOM.conf"
    for f in $ANCIENS; do a2dissite "$(basename "$f" .conf)" >/dev/null; done
    a2ensite "$NOM" >/dev/null
    if ! apache2ctl configtest 2>/tmp/apache-feggu.log; then
        rouge "Configuration Apache invalide, retour à l'état précédent :"; cat /tmp/apache-feggu.log
        a2dissite "$NOM" >/dev/null
        for f in $ANCIENS; do a2ensite "$(basename "$f" .conf)" >/dev/null; done
        systemctl reload apache2; exit 1
    fi
    systemctl reload apache2
    vert "Apache sert maintenant la V2 sur $DOMAINE.${ANCIENS:+ Ancienne configuration sauvegardée dans $SAUVEGARDES/vhost-v1-$HORODATAGE}"
fi

# Certificat HTTPS s'il n'existait pas encore
if [ -z "$CERT" ] && command -v certbot >/dev/null; then
    etape "Certificat HTTPS"
    CERTBOT_DOMAINES="-d $DOMAINE"; [ "$MODE" = prod ] && CERTBOT_DOMAINES="$CERTBOT_DOMAINES -d www.$DOMAINE"
    # shellcheck disable=SC2086
    certbot --"$WEB" $CERTBOT_DOMAINES --redirect --non-interactive --agree-tos --register-unsafely-without-email \
        || jaune "Certificat non obtenu (DNS pas encore pointé ?). Plus tard : sudo certbot --$WEB $CERTBOT_DOMAINES --redirect"
fi

# ---------------------------------------------------------------------------
# Sauvegarde quotidienne de la base (sur le serveur ; à copier aussi ailleurs)
# ---------------------------------------------------------------------------
if [ ! -f "/etc/cron.d/$NOM" ]; then
    etape "Sauvegarde quotidienne de la base"
    BASE_ACTUELLE="$(grep '^DB_DATABASE=' .env | cut -d= -f2-)"
    UTIL_ACTUEL="$(grep '^DB_USERNAME=' .env | cut -d= -f2-)"
    MDP_ACTUEL="$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)"
    printf '[client]\nuser=%s\npassword=%s\n' "$UTIL_ACTUEL" "$MDP_ACTUEL" > "/root/.$NOM.cnf"; chmod 600 "/root/.$NOM.cnf"
    mkdir -p "/var/backups/$NOM" && chmod 700 "/var/backups/$NOM"
    cat > "/etc/cron.d/$NOM" <<CRON
# Sauvegarde FEGGU V2 ($DOMAINE) chaque nuit à 2 h 40, conservée 14 jours
40 2 * * * root mysqldump --defaults-extra-file=/root/.$NOM.cnf --single-transaction --no-tablespaces $BASE_ACTUELLE | gzip > /var/backups/$NOM/\$(date +\%F).sql.gz && find /var/backups/$NOM -name '*.sql.gz' -mtime +14 -delete
CRON
    vert "Sauvegardes dans /var/backups/$NOM (14 jours)."
fi

# ---------------------------------------------------------------------------
# Vérification finale
# ---------------------------------------------------------------------------
etape "Vérification"
CODE="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAINE" http://127.0.0.1/ -L --max-redirs 0 || true)"
if [ -n "$CERT" ]; then
    CODE_HTTPS="$(curl -sk -o /dev/null -w '%{http_code}' --resolve "$DOMAINE:443:127.0.0.1" "https://$DOMAINE/" || true)"
    echo "HTTP : $CODE (redirection attendue) · HTTPS : $CODE_HTTPS"
else
    echo "HTTP : $CODE · HTTPS : pas encore de certificat"
fi
grep -q '^APP_DEBUG=false' .env && vert "Mode production (erreurs masquées)." || jaune "APP_DEBUG n'est pas à false !"

echo
vert "════════════════ FEGGU V2 déployée sur https://$DOMAINE ════════════════"
if [ "$INSTALLEE" = 0 ]; then
    # Installation terminée : marqueur posé, mot de passe initial retiré du serveur.
    MDP_ADMIN="${MDP_ADMIN:-$(grep '^FEGGU_ADMIN_PASSWORD=' .env | cut -d= -f2- || true)}"
    sed -i '/^FEGGU_ADMIN_PASSWORD=/d' .env
    sudo -u www-data touch storage/app/private/.feggu-installee
    JETON_ACTUEL="$(grep '^FEGGU_WEBHOOK_TOKEN=' .env | cut -d= -f2-)"
    echo
    echo "  Espace structures : https://$DOMAINE/espace/connexion"
    if [ "$MODE" = demo ]; then
        echo "  Compte de démonstration : demo@feggu.org / Demo-Feggu-2026"
    else
        echo "  Administrateur : $ADMIN_EMAIL"
        echo "  Mot de passe provisoire : $MDP_ADMIN"
        echo "  (double authentification demandée à la première connexion ; le mot de passe n'est conservé nulle part)"
        echo
        echo "  Adresse du webhook SMS à donner à LAM :"
        echo "  https://$DOMAINE/webhooks/sms?token=$JETON_ACTUEL"
        echo "  (l'ancienne adresse alert-info continue de fonctionner${IPS_SMS:+ pour $IPS_SMS})"
        echo "  Pour que FEGGU réponde aux SMS : renseigner LAM_SMS_* et FEGGU_SMS_DRIVER=lam dans .env"
    fi
    echo
    jaune "  IMPORTANT : la clé APP_KEY chiffre les récits des victimes. Sans elle, ils sont illisibles."
    jaune "  Copiez maintenant le contenu de $DOSSIER/.env dans un coffre-fort (gestionnaire de mots de passe) :"
    echo "  $(grep '^APP_KEY=' .env)"
fi
echo
echo "  Retour à la v1 en cas de problème : ./deploy.sh retour"
