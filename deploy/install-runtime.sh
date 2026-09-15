#!/usr/bin/env bash
set -Eeuo pipefail

# Nodexa Linux Installer
# Supported: Ubuntu 22.04 / 24.04, amd64 / arm64

if [[ $EUID -ne 0 ]]; then echo "[ERROR] Run this installer as root: sudo bash install.sh"; exit 1; fi
if ! command -v apt-get >/dev/null 2>&1; then echo "[ERROR] This installer currently supports Ubuntu/Debian (apt) systems."; exit 1; fi
source /etc/os-release || true
if [[ "${ID:-}" != "ubuntu" && "${ID:-}" != "debian" ]]; then echo "[WARN] Detected ${PRETTY_NAME:-unknown}. Ubuntu 22.04/24.04 is recommended."; fi

INSTALL_DIR="${NODEXA_DIR:-/var/www/nodexa}"; PANEL_DIR="$INSTALL_DIR/panel"; AGENT_DIR="$INSTALL_DIR/agent"; DATA_DIR="${NODEXA_DATA:-/var/lib/nodexa}"; BACKUP_DIR="${NODEXA_BACKUPS:-/var/lib/nodexa/backups}"; AGENT_PORT="${NODEXA_AGENT_PORT:-8080}"; DOMAIN="${NODEXA_DOMAIN:-_}"; TIMEZONE="${NODEXA_TIMEZONE:-Europe/Copenhagen}"; APP_LOCALE="${NODEXA_APP_LOCALE:-da}"; DB_HOST="${NODEXA_DB_HOST:-127.0.0.1}"; DB_PORT="${NODEXA_DB_PORT:-3306}"; DB_NAME="${NODEXA_DB_NAME:-nodexa}"; DB_USER="${NODEXA_DB_USER:-nodexa}"; DB_PASS="${NODEXA_DB_PASS:-$(openssl rand -hex 24)}"; CACHE_STORE="${NODEXA_CACHE_STORE:-redis}"; SESSION_DRIVER="${NODEXA_SESSION_DRIVER:-redis}"; QUEUE_CONNECTION="${NODEXA_QUEUE_CONNECTION:-redis}"; REDIS_HOST="${NODEXA_REDIS_HOST:-127.0.0.1}"; REDIS_PORT="${NODEXA_REDIS_PORT:-6379}"; REDIS_PASSWORD="${NODEXA_REDIS_PASSWORD:-}"; MAIL_MAILER="${NODEXA_MAIL_MAILER:-log}"; MAIL_HOST="${NODEXA_MAIL_HOST:-127.0.0.1}"; MAIL_PORT="${NODEXA_MAIL_PORT:-587}"; MAIL_USERNAME="${NODEXA_MAIL_USERNAME:-}"; MAIL_PASSWORD="${NODEXA_MAIL_PASSWORD:-}"; MAIL_ENCRYPTION="${NODEXA_MAIL_ENCRYPTION:-}"; MAIL_FROM_ADDRESS="${NODEXA_MAIL_FROM_ADDRESS:-admin@localhost}"; MAIL_FROM_NAME="${NODEXA_MAIL_FROM_NAME:-Nodexa}"; AGENT_TOKEN="${NODEXA_AGENT_TOKEN:-$(openssl rand -hex 32)}"; SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }; fail(){ printf '\n\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }; dotenv_quote(){ local value="$1"; value="${value//\\/\\\\}"; value="${value//\"/\\\"}"; value="${value//\$/\\$}"; value="${value//\`/\\\`}"; printf '"%s"' "$value"; }; sql_escape(){ printf '%s' "$1"|sed "s/'/''/g"; }

export COMPOSER_ALLOW_SUPERUSER=1
log "Installing system packages..."; export DEBIAN_FRONTEND=noninteractive; apt-get update -y; apt-get install -y ca-certificates curl gnupg unzip tar git nginx mariadb-server redis-server php-cli php-fpm php-mysql php-sqlite3 php-redis php-mbstring php-xml php-curl php-zip php-bcmath php-gd php-intl composer build-essential pkg-config docker.io
log "Installing Node.js 22..."; install -m 0755 -d /etc/apt/keyrings; rm -f /etc/apt/keyrings/nodesource.gpg; curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key|gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg; chmod a+r /etc/apt/keyrings/nodesource.gpg; echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" >/etc/apt/sources.list.d/nodesource.list; apt-get update -y; apt-get install -y nodejs; NODE_VERSION="$(node -p 'process.versions.node')"; NODE_MAJOR="${NODE_VERSION%%.*}"; [[ "$NODE_MAJOR" -ge 22 ]]||fail "Node.js 22+ is required, but ${NODE_VERSION} was installed."; log "Node.js ${NODE_VERSION} and npm $(npm --version) ready."
systemctl enable --now mariadb redis-server docker nginx

# Nodexa nodes can be registered as database hosts from the panel. MariaDB must
# therefore listen beyond localhost. Authentication and the firewall still
# control which remote clients are actually allowed to connect.
MARIADB_SERVER_CNF="/etc/mysql/mariadb.conf.d/50-server.cnf"
if [[ -f "$MARIADB_SERVER_CNF" ]]; then
 log "Configuring MariaDB database-host listener on 0.0.0.0:3306..."
 if grep -Eq '^[[:space:]]*bind-address[[:space:]]*=' "$MARIADB_SERVER_CNF"; then
  sed -i -E 's/^[[:space:]]*bind-address[[:space:]]*=.*/bind-address = 0.0.0.0/' "$MARIADB_SERVER_CNF"
 else
  printf '\n[mysqld]\nbind-address = 0.0.0.0\n' >> "$MARIADB_SERVER_CNF"
 fi
 systemctl restart mariadb
 if ss -lnt 2>/dev/null | grep -Eq '(^|[[:space:]])0\.0\.0\.0:3306([[:space:]]|$)|(^|[[:space:]])\*:3306([[:space:]]|$)'; then log "MariaDB is listening on 0.0.0.0:3306."; else echo "[WARN] MariaDB did not report an external 3306 listener. Check $MARIADB_SERVER_CNF." >&2; fi
fi

if [[ "$DB_HOST" == "127.0.0.1" || "$DB_HOST" == "localhost" ]]; then log "Creating local database..."; DB_PASS_SQL="$(sql_escape "$DB_PASS")"; mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS_SQL}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS_SQL}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
else log "Using remote database ${DB_HOST}:${DB_PORT}; database/user creation is skipped."; fi

log "Preparing Laravel panel..."; mkdir -p "$INSTALL_DIR"; TMP_LARAVEL="$(mktemp -d)"; trap 'rm -rf "$TMP_LARAVEL"' EXIT; composer create-project laravel/laravel:^12.0 "$TMP_LARAVEL/panel" --no-interaction --prefer-dist --no-scripts; rm -rf "$PANEL_DIR"; mkdir -p "$PANEL_DIR"; cp -a "$TMP_LARAVEL/panel/." "$PANEL_DIR/"; cp -a "$SOURCE_DIR/panel/." "$PANEL_DIR/"; cd "$PANEL_DIR"; rm -f composer.lock; log "Installing PHP dependencies without Laravel scripts..."; composer update --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts
[[ -f .env.example ]]||fail "Missing .env.example in panel source."; cp .env.example .env; rm -f bootstrap/cache/*.php 2>/dev/null||true; APP_KEY_VALUE="base64:$(openssl rand -base64 32|tr -d '\r\n')"; if grep -q '^APP_KEY=' .env; then sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" .env; else printf '\nAPP_KEY=%s\n' "$APP_KEY_VALUE">>.env; fi; export APP_KEY="$APP_KEY_VALUE"; log "Laravel application key created before framework bootstrap."; log "Discovering Laravel packages..."; php artisan package:discover --ansi
APP_URL="http://${DOMAIN/_/localhost}"; REDIS_PASSWORD_ENV="null"; [[ -n "$REDIS_PASSWORD" ]]&&REDIS_PASSWORD_ENV="$(dotenv_quote "$REDIS_PASSWORD")"; MAIL_USERNAME_ENV="null"; [[ -n "$MAIL_USERNAME" ]]&&MAIL_USERNAME_ENV="$(dotenv_quote "$MAIL_USERNAME")"; MAIL_PASSWORD_ENV="null"; [[ -n "$MAIL_PASSWORD" ]]&&MAIL_PASSWORD_ENV="$(dotenv_quote "$MAIL_PASSWORD")"; MAIL_ENCRYPTION_ENV="null"; [[ -n "$MAIL_ENCRYPTION" ]]&&MAIL_ENCRYPTION_ENV="$(dotenv_quote "$MAIL_ENCRYPTION")"
cat >>.env <<ENV

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
ENV
chown root:www-data .env; chmod 0640 .env; php artisan migrate --force; php artisan optimize:clear; chown -R www-data:www-data storage bootstrap/cache; chmod -R 775 storage bootstrap/cache
log "Nodexa runtime installation completed."
