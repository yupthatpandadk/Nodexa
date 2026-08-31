#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source

PANEL_DIR="${NODEXA_DIR:-/var/www/nodexa}/panel"
AGENT_DIR="${NODEXA_DIR:-/var/www/nodexa}/agent"

log "Updating Nodexa..."
apt-get install -y rsync curl sudo >/dev/null
export COMPOSER_ALLOW_SUPERUSER=1

if [[ -d "$PANEL_DIR" ]]; then
  log "Updating panel files without touching local configuration or data..."
  rsync -a --delete \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='bootstrap/cache/' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='public/build/' \
    "$SOURCE_ROOT/panel/" "$PANEL_DIR/"

  cd "$PANEL_DIR"
  rm -f composer.lock
  composer update --no-dev --optimize-autoloader --no-interaction --prefer-dist
  php artisan migrate --force
  php artisan optimize:clear

  npm install
  npm run build

  php artisan config:cache
  php artisan route:cache || true
  php artisan view:cache || true
  chown -R www-data:www-data storage bootstrap/cache
  chmod -R 775 storage bootstrap/cache
fi

if [[ -d "$AGENT_DIR" ]]; then
  log "Updating Nodexa Agent..."
  rsync -a --delete "$SOURCE_ROOT/agent/" "$AGENT_DIR/"
  cd "$AGENT_DIR"
  go mod tidy
  go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad
  systemctl restart nodexa-agent 2>/dev/null || true
fi

# Refresh the dedicated updater helper/service itself and record the installed commit.
bash "$SOURCE_ROOT/deploy/setup-updater.sh"

systemctl restart nodexa-queue 2>/dev/null || true
systemctl restart nodexa-monitor.timer 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true
log "Update complete. Local .env, storage and server data were preserved."
