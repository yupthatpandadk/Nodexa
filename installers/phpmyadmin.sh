#!/usr/bin/env bash
set -Eeuo pipefail

log(){ printf '\033[1;32m[Nodexa]\033[0m %s\n' "$*"; }
warn(){ printf '\033[1;33m[Nodexa]\033[0m %s\n' "$*"; }
die(){ printf '\033[1;31m[Nodexa]\033[0m %s\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Kør installeren som root."
export DEBIAN_FRONTEND=noninteractive

log "Installerer phpMyAdmin..."
apt-get update
apt-get install -y phpmyadmin php-mbstring php-zip php-gd php-curl

# Pterodactyl/Nodexa runs on PHP 8.3. Do not use the system default PHP version:
# newer CLI packages may be installed alongside it.
PHPV="8.3"
FPM_SOCK="/run/php/php8.3-fpm.sock"
[[ -S "$FPM_SOCK" ]] || die "PHP 8.3 FPM socket blev ikke fundet: $FPM_SOCK"

phpenmod -v "$PHPV" mbstring 2>/dev/null || true
systemctl restart php8.3-fpm

PMA_DIR="/usr/share/phpmyadmin"
[[ -f "$PMA_DIR/index.php" ]] || die "phpMyAdmin blev ikke fundet efter installation."

SNIPPET="/etc/nginx/snippets/nodexa-phpmyadmin.conf"
mkdir -p /etc/nginx/snippets
cat > "$SNIPPET" <<'EOF'
location = /phpmyadmin {
    return 301 /phpmyadmin/;
}

location ^~ /phpmyadmin/ {
    alias /usr/share/phpmyadmin/;
    index index.php;

    location ~ ^/phpmyadmin/(.+\.php)$ {
        alias /usr/share/phpmyadmin/$1;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /usr/share/phpmyadmin/$1;
        fastcgi_param SCRIPT_NAME /phpmyadmin/$1;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
EOF

SITE=""
for link in /etc/nginx/sites-enabled/*; do
    [[ -e "$link" ]] || continue
    target="$(readlink -f "$link" 2>/dev/null || printf '%s' "$link")"
    [[ -f "$target" ]] || continue
    if grep -Eq 'server_name[[:space:]].*(panel\.)?nordicnode\.org|root[[:space:]]+/var/www/pterodactyl/public' "$target"; then
        SITE="$target"
        break
    fi
done
[[ -n "$SITE" ]] || die "Kunne ikke finde Nodexa/Pterodactyl Nginx-vhost."

# Clean up the broken include accidentally inserted into a previous backup file.
BROKEN="${SITE}.nodexa-pma.bak"
if [[ -f "$BROKEN" ]]; then
    sed -i '/^[[:space:]]*include \/etc\/nginx\/snippets\/nodexa-phpmyadmin\.conf;[[:space:]]*$/d' "$BROKEN" || true
fi

# Make a clean backup before editing.
BACKUP="${SITE}.before-phpmyadmin"
cp -a "$SITE" "$BACKUP"

if ! grep -Fq "include $SNIPPET;" "$SITE"; then
    # Insert in the active server block immediately before its final closing brace.
    last_line="$(grep -n '^[[:space:]]*}' "$SITE" | tail -1 | cut -d: -f1)"
    [[ -n "$last_line" ]] || die "Kunne ikke finde slutningen på Nginx serverblokken."
    sed -i "${last_line}i\    include $SNIPPET;" "$SITE"
fi

if ! nginx -t; then
    cp -f "$BACKUP" "$SITE"
    nginx -t >/dev/null 2>&1 || true
    die "Nginx-validering fejlede. Den aktive vhost er rullet tilbage."
fi

systemctl reload nginx
log "phpMyAdmin er installeret og Nginx er valideret."
log "Åbn https://panel.nordicnode.org/phpmyadmin/"
