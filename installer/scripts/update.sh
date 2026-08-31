#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source

PANEL_DIR="${NODEXA_DIR:-/var/www/nodexa}/panel"
AGENT_DIR="${NODEXA_DIR:-/var/www/nodexa}/agent"

log "Updating Nodexa..."
apt-get install -y rsync curl sudo composer >/dev/null
export COMPOSER_ALLOW_SUPERUSER=1

repair_laravel_skeleton(){
  local tmp
  tmp="$(mktemp -d)"
  log "Repairing Laravel runtime files..."
  composer create-project laravel/laravel:^11.0 "$tmp/panel" --no-interaction --prefer-dist --no-scripts >/dev/null

  # Restore framework/runtime files that are not stored in the Nodexa source
  # repository. Never overwrite local configuration or persistent data.
  rsync -a \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='bootstrap/cache/' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='public/build/' \
    "$tmp/panel/" "$PANEL_DIR/"
  rm -rf "$tmp"
}

if [[ -d "$PANEL_DIR" ]]; then
  # Older Nodexa updaters used rsync --delete against the source overlay. Since
  # the repository intentionally contains only Nodexa-specific panel files,
  # that could remove Laravel's public/index.php, artisan and other framework
  # skeleton files and make Nginx return 403. Recover those files automatically.
  if [[ ! -f "$PANEL_DIR/public/index.php" || ! -f "$PANEL_DIR/artisan" || ! -f "$PANEL_DIR/bootstrap/app.php" ]]; then
    repair_laravel_skeleton
  fi

  log "Updating panel files without touching local configuration or data..."
  # IMPORTANT: do not use --delete here. Nodexa's repository is an overlay on
  # top of a Laravel installation, not a complete Laravel skeleton.
  rsync -a \
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

  # Nginx needs traverse/read access to the application and public directory;
  # only Laravel's writable directories are owned by www-data.
  chmod 755 /var/www /var/www/nodexa "$PANEL_DIR" "$PANEL_DIR/public" 2>/dev/null || true
  find "$PANEL_DIR/public" -type d -exec chmod 755 {} + 2>/dev/null || true
  find "$PANEL_DIR/public" -type f -exec chmod 644 {} + 2>/dev/null || true
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
nginx -t
systemctl reload nginx
log "Update complete. Local .env, storage and server data were preserved."
