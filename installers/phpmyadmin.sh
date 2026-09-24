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

while true; do
    read -rp "SQL admin-brugernavn [nodexa_sql]: " SQL_USER
    SQL_USER="${SQL_USER:-nodexa_sql}"
    [[ "$SQL_USER" =~ ^[A-Za-z0-9_]{1,32}$ ]] && break
    warn "Brug kun bogstaver, tal og underscore (maks. 32 tegn)."
done

while true; do
    read -rsp "SQL adgangskode (min. 12 tegn): " SQL_PASSWORD
    echo
    [[ ${#SQL_PASSWORD} -ge 12 ]] && break
    warn "Adgangskoden skal være mindst 12 tegn."
done

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

log "Installerer phpMyAdmin, MariaDB og PHP 8.3 moduler..."
apt-get update
apt-get install -y nginx curl ca-certificates tar mariadb-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd
phpenmod -v 8.3 mbstring 2>/dev/null || true
systemctl enable --now php8.3-fpm
systemctl enable --now mariadb

log "Opretter SQL-kontoen $SQL_USER..."
SQL_USER_ESCAPED="${SQL_USER//\'/\'\'}"
SQL_PASSWORD_ESCAPED="${SQL_PASSWORD//\'/\'\'}"
mariadb --protocol=socket -u root <<SQL
CREATE USER IF NOT EXISTS '${SQL_USER_ESCAPED}'@'localhost' IDENTIFIED BY '${SQL_PASSWORD_ESCAPED}';
ALTER USER '${SQL_USER_ESCAPED}'@'localhost' IDENTIFIED BY '${SQL_PASSWORD_ESCAPED}';
GRANT ALL PRIVILEGES ON *.* TO '${SQL_USER_ESCAPED}'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL
unset SQL_PASSWORD SQL_PASSWORD_ESCAPED

PMA_DIR="/var/www/phpmyadmin"
PMA_VERSION="5.2.2"
log "Installerer en ren phpMyAdmin ${PMA_VERSION}..."
rm -rf "$PMA_DIR"
mkdir -p "$PMA_DIR"
TMP_PMA="$(mktemp -d)"
trap 'rm -rf "$TMP_PMA"' EXIT
curl -fL --retry 3 -o "$TMP_PMA/phpmyadmin.tar.gz" "https://files.phpmyadmin.net/phpMyAdmin/${PMA_VERSION}/phpMyAdmin-${PMA_VERSION}-all-languages.tar.gz"
tar -xzf "$TMP_PMA/phpmyadmin.tar.gz" -C "$TMP_PMA"
cp -a "$TMP_PMA/phpMyAdmin-${PMA_VERSION}-all-languages/." "$PMA_DIR/"
mkdir -p "$PMA_DIR/tmp"
chown -R root:www-data "$PMA_DIR"
chown -R www-data:www-data "$PMA_DIR/tmp"
chmod 750 "$PMA_DIR/tmp"
BLOWFISH="$(openssl rand -base64 48 | tr -d '\n' | cut -c1-32)"
cat > "$PMA_DIR/config.inc.php" <<PMA_CONFIG
<?php
\$cfg['blowfish_secret'] = '${BLOWFISH}';
\$i = 0;
++\$i;
\$cfg['Servers'][\$i]['auth_type'] = 'cookie';
\$cfg['Servers'][\$i]['host'] = 'localhost';
\$cfg['Servers'][\$i]['compress'] = false;
\$cfg['Servers'][\$i]['AllowNoPassword'] = false;
\$cfg['TempDir'] = '/var/www/phpmyadmin/tmp';
PMA_CONFIG
[[ -f "$PMA_DIR/index.php" ]] || die "Den rene phpMyAdmin-installation mislykkedes."
[[ -S /run/php/php8.3-fpm.sock ]] || die "PHP 8.3 FPM socket mangler."

CONF="/etc/nginx/sites-available/nodexa-phpmyadmin.conf"
cat > "$CONF" <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name __FQDN__;

    root /var/www/phpmyadmin;
    index index.php;

    client_max_body_size 100m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param PHP_VALUE "upload_max_filesize=100M; post_max_size=100M;";
    }

    location ~ /\. {
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
log "SQL-konto oprettet: $SQL_USER"
log "Brug denne konto til at logge ind i phpMyAdmin."
