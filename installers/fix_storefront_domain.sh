#!/usr/bin/env bash
# Route nordicnode.org and www.nordicnode.org to Nodexa's Laravel storefront.
# Leaves panel.nordicnode.org and pma.nordicnode.org untouched.
set -Eeuo pipefail

say()  { printf '[Nodexa Storefront] %s\n' "$*"; }
warn() { printf '[Nodexa Storefront] ADVARSEL: %s\n' "$*" >&2; }
die()  { printf '[Nodexa Storefront] FEJL: %s\n' "$*" >&2; exit 1; }

[[ "$EUID" -eq 0 ]] || die "Kør scriptet med sudo/root."
command -v nginx >/dev/null || die "Nginx er ikke installeret."
nginx -t >/dev/null 2>&1 || die "Din eksisterende Nginx-konfiguration har fejl. Ret dem før du fortsætter."

DOMAIN="${NODEXA_STOREFRONT_DOMAIN:-nordicnode.org}"
DOMAIN="${DOMAIN#www.}"
[[ "$DOMAIN" =~ ^[a-zA-Z0-9.-]+$ ]] || die "Ugyldigt storefront-domæne."
WWW="www.$DOMAIN"

PANEL_DIR=""
for candidate in "${NODEXA_PANEL_DIR:-}" /var/www/pterodactyl /var/www/nodexa/panel; do
    [[ -n "$candidate" ]] || continue
    if [[ -f "$candidate/routes/storefront.php" && -f "$candidate/public/index.php" ]]; then
        PANEL_DIR="$candidate"
        break
    fi
done
[[ -n "$PANEL_DIR" ]] || die "Kan ikke finde Nodexa. Brug NODEXA_PANEL_DIR=/sti/til/panel."
say "Storefront: $PANEL_DIR/public"

PHP_SOCK=""
for socket in /run/php/php8.3-fpm.sock /run/php/php*-fpm.sock; do
    if [[ -S "$socket" ]]; then
        PHP_SOCK="$socket"
        break
    fi
done
[[ -n "$PHP_SOCK" ]] || die "Ingen aktiv PHP-FPM socket i /run/php. Start PHP-FPM først."

CONF=/etc/nginx/sites-available/nodexa-storefront.conf
LINK=/etc/nginx/sites-enabled/nodexa-storefront.conf
BACKUP="/etc/nginx/nodexa-backups/storefront-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"

# Never silently remove another vhost. A dedicated domain must not be shared
# with the panel or phpMyAdmin (except their intentionally separate subdomains).
CONFLICT=0
for f in /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*.conf; do
    [[ -f "$f" ]] || continue
    [[ "$(readlink -f "$f")" == "$CONF" ]] && continue
    if awk -v a="$DOMAIN" -v b="$WWW" '
        /^[[:space:]]*server_name[[:space:]]/ {
            line=$0; sub(/#.*/, "", line); gsub(/;/, " ", line)
            n=split(line, fields, /[[:space:]]+/)
            for (i=1; i<=n; ++i) if (fields[i]==a || fields[i]==b) found=1
        }
        END { exit(found ? 0 : 1) }
    ' "$f"; then
        warn "Et eksisterende site bruger allerede $DOMAIN eller $WWW: $f"
        CONFLICT=1
    fi
done
if [[ "$CONFLICT" -eq 1 ]]; then
    die "Ret det viste sites server_name til dets eget domæne, eller deaktiver den forældede vhost, og kør scriptet igen. Intet er ændret."
fi

OLD_CONF=0
OLD_LINK=""
if [[ -f "$CONF" ]]; then
    cp -a "$CONF" "$BACKUP/nodexa-storefront.conf"
    OLD_CONF=1
fi
if [[ -e "$LINK" || -L "$LINK" ]]; then
    OLD_LINK="$(readlink "$LINK" || true)"
    [[ -L "$LINK" ]] || die "$LINK er ikke et symlink. Ret det manuelt før fortsættelse."
fi

restore() {
    warn "Gendanner Nginx-konfigurationen fra før forsøget."
    rm -f "$LINK"
    if [[ -n "$OLD_LINK" ]]; then ln -s "$OLD_LINK" "$LINK"; fi
    if [[ "$OLD_CONF" -eq 1 ]]; then
        cp -a "$BACKUP/nodexa-storefront.conf" "$CONF"
    else
        rm -f "$CONF"
    fi
}
cat > "$CONF" <<NGINX
# Managed by Nodexa's fix_storefront_domain.sh.
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN $WWW;

    root $PANEL_DIR/public;
    index index.php;
    charset utf-8;

    access_log /var/log/nginx/nodexa-storefront.access.log;
    error_log /var/log/nginx/nodexa-storefront.error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sfn "$CONF" "$LINK"

# nginx -t can exit successfully while ignoring a duplicate host. Check warnings too.
if ! nginx -t > "$BACKUP/nginx-test.txt" 2>&1 ||
   grep -Eq "conflicting server name.*($DOMAIN|$WWW)" "$BACKUP/nginx-test.txt"; then
    cat "$BACKUP/nginx-test.txt" >&2
    restore
    die "Nginx-validering fejlede; eksisterende opsætning er gendannet."
fi
if ! systemctl reload nginx; then
    restore
    nginx -t && systemctl reload nginx || true
    die "Nginx kunne ikke genindlæses; konfigurationen er gendannet."
fi

say "HTTP for $DOMAIN og $WWW peger nu på Nodexa Storefront."
say "panel.$DOMAIN og pma.$DOMAIN er ikke ændret."
say "Backupmappe: $BACKUP"

# Certbot upgrades BOTH domains to HTTPS when present. Do not silently
# claim HTTPS works if no certificate or DNS is ready.
if [[ "${NODEXA_SETUP_SSL:-1}" == "1" ]]; then
    if command -v certbot >/dev/null; then
        say "Konfigurerer HTTPS til begge storefront-domæner via Certbot..."
        if certbot --nginx --redirect -d "$DOMAIN" -d "$WWW"; then
            nginx -t && systemctl reload nginx
            say "Færdig: https://$DOMAIN og https://$WWW viser Storefront."
        else
            warn "HTTPS-opsætning mislykkedes. HTTP-vhost virker, men HTTPS skal rettes."
            warn "Kontroller DNS, port 80/443 og certifikater; kør: certbot --nginx --redirect -d $DOMAIN -d $WWW"
            exit 2
        fi
    else
        warn "Certbot findes ikke. Installer certbot/python3-certbot-nginx og kør:"
        warn "certbot --nginx --redirect -d $DOMAIN -d $WWW"
        exit 2
    fi
else
    say "HTTPS-trinnet blev fravalgt med NODEXA_SETUP_SSL=0."
fi
