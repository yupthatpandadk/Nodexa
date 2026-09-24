#!/usr/bin/env bash
set -Eeuo pipefail

log(){ printf '\033[1;32m[Nodexa]\033[0m %s\n' "$*"; }
warn(){ printf '\033[1;33m[Nodexa]\033[0m %s\n' "$*"; }
die(){ printf '\033[1;31m[Nodexa]\033[0m %s\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Kør installeren som root."
export DEBIAN_FRONTEND=noninteractive

# Nodexa phpMyAdmin installer.
# Based on the proven standalone-vhost approach used by guldkage/Pterodactyl-Installer.
# It deliberately does NOT edit the Pterodactyl vhost and never stores backup files
# in sites-enabled, because nginx includes every file in that directory.

read -rp "phpMyAdmin domæne [pma.nordicnode.org]: " FQDN
FQDN="${FQDN:-pma.nordicnode.org}"
FQDN="$(printf '%s' "$FQDN" | tr '[:upper:]' '[:lower:]')"
[[ "$FQDN" =~ ^[a-z0-9.-]+$ ]] || die "Ugyldigt domæne."

read -rp "Brug HTTPS/Let's Encrypt? [Y/n]: " USE_SSL
USE_SSL="${USE_SSL:-Y}"

if [[ "$USE_SSL" =~ ^[Yy]$ ]]; then
    read -rp "E-mail til Let's Encrypt: " LE_EMAIL
    [[ "$LE_EMAIL" == *@*.* ]] || die "Ugyldig e-mail."
fi

log "Rydder gamle Nodexa/phpMyAdmin Nginx-rester..."
mkdir -p /etc/nginx/nodexa-backups
find /etc/nginx/sites-enabled -maxdepth 1 -type f \( -name '*.nodexa-pma.bak' -o -name '*.before-phpmyadmin' -o -name '*.bak' \) -exec mv -t /etc/nginx/nodexa-backups/ {} + 2>/dev/null || true
rm -f /etc/nginx/sites-enabled/phpmyadmin.conf /etc/nginx/sites-available/phpmyadmin.conf

log "Installerer phpMyAdmin og PHP 8.3 moduler..."
apt-get update
apt-get install -y nginx curl ca-certificates phpmyadmin php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd
phpenmod -v 8.3 mbstring 2>/dev/null || true
systemctl enable --now php8.3-fpm

PMA_DIR="/usr/share/phpmyadmin"
[[ -f "$PMA_DIR/index.php" ]] || die "phpMyAdmin blev ikke fundet i $PMA_DIR."
[[ -S /run/php/php8.3-fpm.sock ]] || die "PHP 8.3 FPM socket mangler."

CONF="/etc/nginx/sites-available/nodexa-phpmyadmin.conf"
cat > "$CONF" <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name __FQDN__;

    root /usr/share/phpmyadmin;
    index index.php;

    client_max_body_size 100m;

    location / {
        try_files \\$uri \\$uri/ /index.php?\\$query_string;
    }

    location ~ \\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param PHP_VALUE "upload_max_filesize=100M \\n post_max_size=100M";
    }

    location ~ /\\. {
        deny all;
    }
}
NGINX
sed -i "s/__FQDN__/$FQDN/g" "$CONF"

ln -sfn "$CONF" /etc/nginx/sites-enabled/nodexa-phpmyadmin.conf

log "Validerer Nginx før ændringer aktiveres..."
if ! nginx -t; then
    rm -f /etc/nginx/sites-enabled/nodexa-phpmyadmin.conf
    die "Nginx-konfigurationen fejlede. phpMyAdmin-vhost blev fjernet igen."
fi
systemctl reload nginx

if [[ "$USE_SSL" =~ ^[Yy]$ ]]; then
    log "Installerer certbot og opretter SSL..."
    apt-get install -y certbot python3-certbot-nginx
    certbot --nginx --redirect --non-interactive --agree-tos --no-eff-email --email "$LE_EMAIL" -d "$FQDN" || {
        warn "SSL kunne ikke oprettes. HTTP-konfigurationen er bevaret."
        warn "Kontrollér at $FQDN peger på denne servers offentlige IP, og at port 80/443 er åbne."
        exit 1
    }
fi

nginx -t
systemctl reload nginx
systemctl restart php8.3-fpm

SCHEME="http"
[[ "$USE_SSL" =~ ^[Yy]$ ]] && SCHEME="https"

log "phpMyAdmin er installeret."
log "Adresse: ${SCHEME}://$FQDN"
log "Log ind med en eksisterende MariaDB/MySQL-bruger."
