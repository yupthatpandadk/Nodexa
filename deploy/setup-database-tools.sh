#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
DB_ADMIN_USER="${NODEXA_DB_ADMIN_USER:-nodexa_dbadmin}"
DB_ADMIN_PASS="${NODEXA_DB_ADMIN_PASSWORD:-$(openssl rand -hex 24)}"

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
# Avoid phpMyAdmin asking which webserver/dbconfig to manage; Nodexa configures both itself.
echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect' | debconf-set-selections || true
echo 'phpmyadmin phpmyadmin/dbconfig-install boolean false' | debconf-set-selections || true
apt-get install -y phpmyadmin php-mbstring php-zip php-gd php-curl python3

mysql -uroot <<SQL
CREATE USER IF NOT EXISTS '${DB_ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_ADMIN_PASS}';
ALTER USER '${DB_ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_ADMIN_PASS}';
GRANT CREATE USER ON *.* TO '${DB_ADMIN_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`s%\`.* TO '${DB_ADMIN_USER}'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

if [[ -f "$PANEL_DIR/.env" ]]; then
  set_env(){ local key="$1" val="$2"; if grep -q "^${key}=" "$PANEL_DIR/.env"; then sed -i "s|^${key}=.*|${key}=${val}|" "$PANEL_DIR/.env"; else printf '\n%s=%s\n' "$key" "$val" >> "$PANEL_DIR/.env"; fi; }
  set_env NODEXA_DB_ADMIN_HOST 127.0.0.1
  set_env NODEXA_DB_ADMIN_PORT 3306
  set_env NODEXA_DB_ADMIN_USER "$DB_ADMIN_USER"
  set_env NODEXA_DB_ADMIN_PASSWORD "$DB_ADMIN_PASS"
  set_env NODEXA_DATABASE_HOST 127.0.0.1
  set_env NODEXA_DATABASE_PORT 3306
  set_env NODEXA_PHPMYADMIN_URL /phpmyadmin/
  set_env NODEXA_PHPMYADMIN_SIGNON_SESSION NodexaSignon
  chmod 640 "$PANEL_DIR/.env"
fi

mkdir -p /etc/phpmyadmin/conf.d
cat >/etc/phpmyadmin/conf.d/nodexa.php <<'PHP'
<?php
/* Nodexa server-isolated phpMyAdmin SSO. */
if (isset($cfg['Servers'][1])) {
    $cfg['Servers'][1]['auth_type'] = 'signon';
    $cfg['Servers'][1]['SignonSession'] = 'NodexaSignon';
    $cfg['Servers'][1]['SignonURL'] = '/';
    $cfg['Servers'][1]['LogoutURL'] = '/';
    $cfg['Servers'][1]['AllowNoPassword'] = false;
}
PHP

if [[ -f /etc/nginx/sites-available/nodexa ]] && ! grep -q 'location /phpmyadmin' /etc/nginx/sites-available/nodexa; then
  python3 - <<'PY'
from pathlib import Path
import glob
p=Path('/etc/nginx/sites-available/nodexa')
s=p.read_text()
socks=sorted(glob.glob('/run/php/php*-fpm.sock'))
if not socks: raise SystemExit('No PHP-FPM socket found')
block=r'''
    location /phpmyadmin/ {
        alias /usr/share/phpmyadmin/;
        index index.php;
        try_files $uri $uri/ /phpmyadmin/index.php?$query_string;
    }

    location ~ ^/phpmyadmin/(.+\.php)$ {
        alias /usr/share/phpmyadmin/$1;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /usr/share/phpmyadmin/$1;
        fastcgi_param DOCUMENT_ROOT /usr/share/phpmyadmin;
        fastcgi_pass unix:__PHP_FPM_SOCK__;
    }
'''.replace('__PHP_FPM_SOCK__', socks[-1])
pos=s.rfind('}')
if pos<0: raise SystemExit('Invalid Nodexa nginx config')
p.write_text(s[:pos]+block+s[pos:])
PY
fi

nginx -t
systemctl reload nginx
cd "$PANEL_DIR"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache || true

cat >/root/nodexa-database-admin.txt <<EOF
Nodexa database provisioner
Username: ${DB_ADMIN_USER}
Password: ${DB_ADMIN_PASS}
Host: 127.0.0.1
EOF
chmod 600 /root/nodexa-database-admin.txt

echo "[Nodexa] Database provisioning and phpMyAdmin SSO configured."
echo "[Nodexa] Provisioner credentials saved to /root/nodexa-database-admin.txt"
