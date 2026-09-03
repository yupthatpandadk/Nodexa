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
TIMEZONE="${NODEXA_TIMEZONE:-Europe/Copenhagen}"
APP_LOCALE="${NODEXA_APP_LOCALE:-da}"
DB_HOST="${NODEXA_DB_HOST:-127.0.0.1}"
DB_PORT="${NODEXA_DB_PORT:-3306}"
DB_NAME="${NODEXA_DB_NAME:-nodexa}"
DB_USER="${NODEXA_DB_USER:-nodexa}"
DB_PASS="${NODEXA_DB_PASS:-$(openssl rand -hex 24)}"
CACHE_STORE="${NODEXA_CACHE_STORE:-redis}"
SESSION_DRIVER="${NODEXA_SESSION_DRIVER:-redis}"
QUEUE_CONNECTION="${NODEXA_QUEUE_CONNECTION:-redis}"
REDIS_HOST="${NODEXA_REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${NODEXA_REDIS_PORT:-6379}"
REDIS_PASSWORD="${NODEXA_REDIS_PASSWORD:-}"
MAIL_MAILER="${NODEXA_MAIL_MAILER:-log}"
MAIL_HOST="${NODEXA_MAIL_HOST:-127.0.0.1}"
MAIL_PORT="${NODEXA_MAIL_PORT:-587}"
MAIL_USERNAME="${NODEXA_MAIL_USERNAME:-}"
MAIL_PASSWORD="${NODEXA_MAIL_PASSWORD:-}"
MAIL_ENCRYPTION="${NODEXA_MAIL_ENCRYPTION:-}"
MAIL_FROM_ADDRESS="${NODEXA_MAIL_FROM_ADDRESS:-admin@localhost}"
MAIL_FROM_NAME="${NODEXA_MAIL_FROM_NAME:-Nodexa}"
AGENT_TOKEN="${NODEXA_AGENT_TOKEN:-$(openssl rand -hex 32)}"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }
fail(){ printf '\n\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

dotenv_quote(){
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  value="${value//\$/\\$}"
  value="${value//\`/\\\`}"
  printf '"%s"' "$value"
}

sql_escape(){ printf '%s' "$1" | sed "s/'/''/g"; }

export COMPOSER_ALLOW_SUPERUSER=1
log "Installing system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y ca-certificates curl gnupg unzip tar git nginx mariadb-server redis-server \
  php-cli php-fpm php-mysql php-sqlite3 php-redis php-mbstring php-xml php-curl php-zip php-bcmath php-gd php-intl \
  composer build-essential pkg-config docker.io

log "Installing Node.js 22..."
install -m 0755 -d /etc/apt/keyrings
rm -f /etc/apt/keyrings/nodesource.gpg
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
chmod a+r /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
apt-get update -y
apt-get install -y nodejs
NODE_VERSION="$(node -p 'process.versions.node')"
NODE_MAJOR="${NODE_VERSION%%.*}"
if [[ "$NODE_MAJOR" -lt 22 ]]; then
  fail "Node.js 22+ is required, but ${NODE_VERSION} was installed."
fi
log "Node.js ${NODE_VERSION} and npm $(npm --version) ready."

systemctl enable --now mariadb redis-server docker nginx

if [[ "$DB_HOST" == "127.0.0.1" || "$DB_HOST" == "localhost" ]]; then
  log "Creating local database..."
  DB_PASS_SQL="$(sql_escape "$DB_PASS")"
  mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS_SQL}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS_SQL}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
else
  log "Using remote database ${DB_HOST}:${DB_PORT}; database/user creation is skipped."
fi

log "Preparing Laravel panel..."
mkdir -p "$INSTALL_DIR"
TMP_LARAVEL="$(mktemp -d)"
trap 'rm -rf "$TMP_LARAVEL"' EXIT

# Build the base application without Composer scripts. Some package-discovery
# hooks boot Laravel and require APP_KEY, so those hooks must run only after
# .env exists and a key has been generated.
composer create-project laravel/laravel:^12.0 "$TMP_LARAVEL/panel" --no-interaction --prefer-dist --no-scripts
rm -rf "$PANEL_DIR"
mkdir -p "$PANEL_DIR"
cp -a "$TMP_LARAVEL/panel/." "$PANEL_DIR/"
cp -a "$SOURCE_DIR/panel/." "$PANEL_DIR/"

cd "$PANEL_DIR"
rm -f composer.lock
log "Installing PHP dependencies without Laravel scripts..."
composer update --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Create .env and put a valid APP_KEY in it without booting Laravel. Calling
# `php artisan key:generate` is not safe here because custom providers or a
# stale cached config can resolve the encrypter before that command runs.
[[ -f .env.example ]] || fail "Missing .env.example in panel source."
cp .env.example .env
rm -f bootstrap/cache/*.php 2>/dev/null || true
APP_KEY_VALUE="base64:$(openssl rand -base64 32 | tr -d '\r\n')"
if grep -q '^APP_KEY=' .env; then
  sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" .env
else
  printf '\nAPP_KEY=%s\n' "$APP_KEY_VALUE" >> .env
fi
export APP_KEY="$APP_KEY_VALUE"
log "Laravel application key created before framework bootstrap."

# Only now is it safe to boot Artisan and rebuild package/cache manifests.
log "Discovering Laravel packages..."
php artisan package:discover --ansi

APP_URL="http://${DOMAIN/_/localhost}"
REDIS_PASSWORD_ENV="null"
[[ -n "$REDIS_PASSWORD" ]] && REDIS_PASSWORD_ENV="$(dotenv_quote "$REDIS_PASSWORD")"
MAIL_USERNAME_ENV="null"
[[ -n "$MAIL_USERNAME" ]] && MAIL_USERNAME_ENV="$(dotenv_quote "$MAIL_USERNAME")"
MAIL_PASSWORD_ENV="null"
[[ -n "$MAIL_PASSWORD" ]] && MAIL_PASSWORD_ENV="$(dotenv_quote "$MAIL_PASSWORD")"
MAIL_ENCRYPTION_ENV="null"
[[ -n "$MAIL_ENCRYPTION" ]] && MAIL_ENCRYPTION_ENV="$(dotenv_quote "$MAIL_ENCRYPTION")"

cat >> .env <<ENV

# Nodexa installation settings
APP_NAME=Nodexa
APP_ENV=production
APP_DEBUG=false
APP_URL=$(dotenv_quote "$APP_URL")
APP_TIMEZONE=$(dotenv_quote "$TIMEZONE")
APP_LOCALE=$(dotenv_quote "$APP_LOCALE")
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=$(dotenv_quote "$DB_HOST")
DB_PORT=${DB_PORT}
DB_DATABASE=$(dotenv_quote "$DB_NAME")
DB_USERNAME=$(dotenv_quote "$DB_USER")
DB_PASSWORD=$(dotenv_quote "$DB_PASS")

CACHE_STORE=${CACHE_STORE}
QUEUE_CONNECTION=${QUEUE_CONNECTION}
SESSION_DRIVER=${SESSION_DRIVER}
REDIS_CLIENT=predis
REDIS_HOST=$(dotenv_quote "$REDIS_HOST")
REDIS_PASSWORD=${REDIS_PASSWORD_ENV}
REDIS_PORT=${REDIS_PORT}

MAIL_MAILER=${MAIL_MAILER}
MAIL_HOST=$(dotenv_quote "$MAIL_HOST")
MAIL_PORT=${MAIL_PORT}
MAIL_USERNAME=${MAIL_USERNAME_ENV}
MAIL_PASSWORD=${MAIL_PASSWORD_ENV}
MAIL_ENCRYPTION=${MAIL_ENCRYPTION_ENV}
MAIL_FROM_ADDRESS=$(dotenv_quote "$MAIL_FROM_ADDRESS")
MAIL_FROM_NAME=$(dotenv_quote "$MAIL_FROM_NAME")

NODEXA_AGENT_URL=http://127.0.0.1:${AGENT_PORT}
NODEXA_AGENT_TOKEN=${AGENT_TOKEN}
ENV

# Remove earlier duplicate keys from the Laravel template, keeping the final
# Nodexa values above as the authoritative production configuration.
python3 - "$PANEL_DIR/.env" <<'PY'
import sys
from pathlib import Path
p = Path(sys.argv[1])
lines = p.read_text().splitlines()
keys = {
    'APP_NAME','APP_ENV','APP_DEBUG','APP_URL','APP_TIMEZONE','APP_LOCALE','APP_FALLBACK_LOCALE',
    'DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD',
    'CACHE_STORE','QUEUE_CONNECTION','SESSION_DRIVER','REDIS_CLIENT','REDIS_HOST','REDIS_PASSWORD','REDIS_PORT',
    'MAIL_MAILER','MAIL_HOST','MAIL_PORT','MAIL_USERNAME','MAIL_PASSWORD','MAIL_ENCRYPTION','MAIL_FROM_ADDRESS','MAIL_FROM_NAME',
    'NODEXA_AGENT_URL','NODEXA_AGENT_TOKEN'
}
last = {}
for i,line in enumerate(lines):
    if '=' in line and not line.lstrip().startswith('#'):
        k = line.split('=',1)[0]
        if k in keys: last[k] = i
out=[]
for i,line in enumerate(lines):
    if '=' in line and not line.lstrip().startswith('#'):
        k=line.split('=',1)[0]
        if k in keys and last.get(k) != i:
            continue
    out.append(line)
p.write_text('\n'.join(out)+'\n')
PY

log "Running database migrations..."
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

log "Runtime installation complete."
echo ""
echo "Panel:       http://${DOMAIN/_/${SERVER_IP:-SERVER-IP}}"
echo "Install dir: $INSTALL_DIR"
echo "Agent:       systemctl status nodexa-agent"
echo "Queue:       systemctl status nodexa-queue"
echo "Monitor:     systemctl status nodexa-monitor.timer"
echo "Database:    ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
if [[ "${NODEXA_DB_PASSWORD_GENERATED:-0}" == "1" ]]; then
 echo "DB password: $DB_PASS"
 echo "IMPORTANT: Save the generated database password above."
else
 echo "DB password: chosen during setup"
fi
