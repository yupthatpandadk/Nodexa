#!/usr/bin/env bash
set -Eeuo pipefail
log(){ printf '\033[1;32m[Nodexa]\033[0m %s\n' "$*"; }
warn(){ printf '\033[1;33m[Nodexa]\033[0m %s\n' "$*"; }
die(){ printf '\033[1;31m[Nodexa]\033[0m %s\n' "$*" >&2; exit 1; }
[[ $EUID -eq 0 ]] || die "Kør installeren som root."
export DEBIAN_FRONTEND=noninteractive
log "Installerer phpMyAdmin og nødvendige PHP-moduler..."
apt-get update
apt-get install -y phpmyadmin php-mbstring php-zip php-gd php-json php-curl
PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
command -v phpenmod >/dev/null 2>&1 && phpenmod mbstring || true
systemctl restart "php${PHPV}-fpm" 2>/dev/null || true
PMA_DIR="/usr/share/phpmyadmin"
[[ -f "$PMA_DIR/index.php" ]] || die "phpMyAdmin blev ikke fundet i $PMA_DIR efter installation."
NGINX_SNIPPET="/etc/nginx/snippets/nodexa-phpmyadmin.conf"
mkdir -p /etc/nginx/snippets
cat > "$NGINX_SNIPPET" <<EOF
location = /phpmyadmin { return 301 /phpmyadmin/; }
location /phpmyadmin/ {
    alias /usr/share/phpmyadmin/;
    index index.php;
    location ~ ^/phpmyadmin/(.+\.php)$ {
        alias /usr/share/phpmyadmin/\$1;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /usr/share/phpmyadmin/\$1;
        fastcgi_param DOCUMENT_ROOT /usr/share/phpmyadmin;
        fastcgi_pass unix:/run/php/php${PHPV}-fpm.sock;
    }
    location ~* ^/phpmyadmin/(.+\.(?:css|js|jpg|jpeg|gif|png|ico|svg|woff|woff2|ttf|map))$ {
        alias /usr/share/phpmyadmin/\$1;
        expires 7d;
        access_log off;
    }
}
EOF
SITE=""
for f in /etc/nginx/sites-enabled/*; do
    [[ -f "$f" ]] || continue
    if grep -Eq 'server_name[[:space:]].*(panel\.)?nordicnode\.org|root[[:space:]]+/var/www/pterodactyl/public' "$f"; then
        SITE="$(readlink -f "$f")"
        break
    fi
done
if [[ -z "$SITE" ]]; then
    warn "phpMyAdmin er installeret, men Nodexa Nginx-vhosten kunne ikke findes automatisk."
    warn "Tilføj: include $NGINX_SNIPPET; inde i den relevante serverblok."
    exit 0
fi
if ! grep -Fq "include $NGINX_SNIPPET;" "$SITE"; then
    cp -a "$SITE" "${SITE}.nodexa-pma.bak"
    sed -i "\$i\    include $NGINX_SNIPPET;" "$SITE"
fi
nginx -t || {
    cp -f "${SITE}.nodexa-pma.bak" "$SITE" 2>/dev/null || true
    die "Nginx-konfigurationen fejlede validering. Ændringen er rullet tilbage."
}
systemctl reload nginx
log "phpMyAdmin er installeret."
log "Åbn /phpmyadmin på dit Nodexa panel-domæne."
