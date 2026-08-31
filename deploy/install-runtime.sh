#!/usr/bin/env bash
set -Eeuo pipefail

# Nodexa Linux Installer
# Supported: Ubuntu 22.04 / 24.04, amd64 / arm64

if [[ $EUID -ne 0 ]]; then
  echo "[ERROR] Run this installer as root: sudo bash install.sh"
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "[ERROR] This installer currently supports Ubuntu/Debian (apt) systems."
  exit 1
fi

source /etc/os-release || true
if [[ "${ID:-}" != "ubuntu" && "${ID:-}" != "debian" ]]; then
  echo "[WARN] Detected ${PRETTY_NAME:-unknown}. Ubuntu 22.04/24.04 is recommended."
fi

INSTALL_DIR="${NODEXA_DIR:-/var/www/nodexa}"
PANEL_DIR="$INSTALL_DIR/panel"
AGENT_DIR="$INSTALL_DIR/agent"
DATA_DIR="${NODEXA_DATA:-/var/lib/nodexa}"
BACKUP_DIR="${NODEXA_BACKUPS:-/var/lib/nodexa/backups}"
AGENT_PORT="${NODEXA_AGENT_PORT:-8080}"
DOMAIN="${NODEXA_DOMAIN:-_}"
DB_NAME="${NODEXA_DB_NAME:-nodexa}"
DB_USER="${NODEXA_DB_USER:-nodexa}"
DB_PASS="${NODEXA_DB_PASS:-$(openssl rand -hex 18)}"
AGENT_TOKEN="${NODEXA_AGENT_TOKEN:-$(openssl rand -hex 32)}"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }
fail(){ printf '\n\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

log "Installing system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y ca-certificates curl gnupg unzip tar git nginx mariadb-server redis-server \
  php-cli php-fpm php-mysql php-mbstring php-xml php-curl php-zip php-bcmath php-gd php-intl \
  composer nodejs npm build-essential pkg-config docker.io

systemctl enable --now mariadb redis-server docker nginx

log "Creating database..."
mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

log "Preparing Laravel panel..."
mkdir -p "$INSTALL_DIR"
TMP_LARAVEL="$(mktemp -d)"
trap 'rm -rf "$TMP_LARAVEL"' EXIT

composer create-project laravel/laravel:^11.0 "$TMP_LARAVEL/panel" --no-interaction --prefer-dist
rm -rf "$PANEL_DIR"
mkdir -p "$PANEL_DIR"
cp -a "$TMP_LARAVEL/panel/." "$PANEL_DIR/"
cp -a "$SOURCE_DIR/panel/." "$PANEL_DIR/"

cd "$PANEL_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
cp .env.example .env
php artisan key:generate --force

cat >> .env <<ENV

APP_NAME=Nodexa
APP_ENV=production
APP_DEBUG=false
APP_URL=http://${DOMAIN/_/localhost}

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

NODEXA_AGENT_URL=http://127.0.0.1:${AGENT_PORT}
NODEXA_AGENT_TOKEN=${AGENT_TOKEN}
ENV

for kv in \
  "DB_CONNECTION=mysql" "DB_HOST=127.0.0.1" "DB_PORT=3306" "DB_DATABASE=$DB_NAME" \
  "DB_USERNAME=$DB_USER" "DB_PASSWORD=$DB_PASS" "CACHE_STORE=redis" \
  "QUEUE_CONNECTION=redis" "SESSION_DRIVER=redis"; do
  key="${kv%%=*}"; val="${kv#*=}"
  if grep -q "^${key}=" .env; then sed -i "0,/^${key}=.*/s||${key}=${val}|" .env; fi
done

php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

log "Building React frontend..."
npm install
npm run build

log "Installing Go toolchain and Nodexa Agent..."
ARCH="$(dpkg --print-architecture)"
case "$ARCH" in
  amd64) GOARCH=amd64 ;;
  arm64) GOARCH=arm64 ;;
  *) fail "Unsupported CPU architecture: $ARCH" ;;
esac

NEED_GO=1
if command -v go >/dev/null 2>&1; then
  GOV="$(go version | awk '{print $3}' | sed 's/^go//')"
  if printf '%s\n%s\n' "1.23" "$GOV" | sort -V -C 2>/dev/null; then NEED_GO=0; fi
fi
if [[ "$NEED_GO" -eq 1 ]]; then
  GO_VERSION="${NODEXA_GO_VERSION:-1.23.12}"
  curl -fL "https://go.dev/dl/go${GO_VERSION}.linux-${GOARCH}.tar.gz" -o /tmp/nodexa-go.tgz
  rm -rf /usr/local/go
  tar -C /usr/local -xzf /tmp/nodexa-go.tgz
  ln -sf /usr/local/go/bin/go /usr/local/bin/go
fi

rm -rf "$AGENT_DIR"
mkdir -p "$AGENT_DIR"
cp -a "$SOURCE_DIR/agent/." "$AGENT_DIR/"
cd "$AGENT_DIR"
/usr/local/bin/go mod tidy || go mod tidy
/usr/local/bin/go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad || \
  go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad

mkdir -p "$DATA_DIR" "$BACKUP_DIR"
cat > /etc/nodexa.env <<ENV
NODEXA_ADDR=0.0.0.0:${AGENT_PORT}
NODEXA_TOKEN=${AGENT_TOKEN}
NODEXA_DATA=${DATA_DIR}
NODEXA_BACKUPS=${BACKUP_DIR}
ENV
chmod 600 /etc/nodexa.env

cat > /etc/systemd/system/nodexa-agent.service <<'UNIT'
[Unit]
Description=Nodexa Agent
After=network-online.target docker.service
Wants=network-online.target
Requires=docker.service

[Service]
Type=simple
EnvironmentFile=/etc/nodexa.env
ExecStart=/usr/local/bin/nodexad
Restart=always
RestartSec=3
LimitNOFILE=1048576

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now nodexa-agent

log "Configuring permissions and queue worker..."
chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

cat > /etc/systemd/system/nodexa-queue.service <<UNIT
[Unit]
Description=Nodexa Laravel Queue Worker
After=redis-server.service mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php ${PANEL_DIR}/artisan queue:work --sleep=1 --tries=3 --timeout=120
WorkingDirectory=${PANEL_DIR}

[Install]
WantedBy=multi-user.target
UNIT

cat > /etc/systemd/system/nodexa-monitor.service <<UNIT
[Unit]
Description=Nodexa Node Health Monitor
After=network-online.target mariadb.service
Wants=network-online.target

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=${PANEL_DIR}
ExecStart=/usr/bin/php ${PANEL_DIR}/bin/monitor-nodes.php
UNIT

cat > /etc/systemd/system/nodexa-monitor.timer <<'UNIT'
[Unit]
Description=Run Nodexa Node Health Monitor every minute

[Timer]
OnBootSec=30s
OnUnitActiveSec=60s
AccuracySec=5s
Persistent=true
Unit=nodexa-monitor.service

[Install]
WantedBy=timers.target
UNIT

systemctl daemon-reload
systemctl enable --now nodexa-queue
systemctl enable --now nodexa-monitor.timer

log "Configuring Nginx..."
PHP_FPM_SOCK="$(find /run/php -maxdepth 1 -name 'php*-fpm.sock' | sort -V | tail -n1)"
[[ -n "$PHP_FPM_SOCK" ]] || fail "Could not find PHP-FPM socket."

cat > /etc/nginx/sites-available/nodexa <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${PANEL_DIR}/public;
    index index.php;

    client_max_body_size 100m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/nodexa /etc/nginx/sites-enabled/nodexa
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

SERVER_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"

log "Installation complete."
echo ""
echo "Panel:       http://${DOMAIN/_/${SERVER_IP:-SERVER-IP}}"
echo "Install dir: $INSTALL_DIR"
echo "Agent:       systemctl status nodexa-agent"
echo "Queue:       systemctl status nodexa-queue"
echo "Monitor:     systemctl status nodexa-monitor.timer"
echo "Database:    $DB_NAME"
echo "DB user:     $DB_USER"
echo "DB password: $DB_PASS"
echo ""
echo "IMPORTANT: Save the database password above."
echo "For SSL, point your domain to this server and install certbot afterwards."
