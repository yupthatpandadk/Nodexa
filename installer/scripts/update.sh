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
  if [[ ! -f "$PANEL_DIR/public/index.php" || ! -f "$PANEL_DIR/artisan" || ! -f "$PANEL_DIR/bootstrap/app.php" ]]; then
    repair_laravel_skeleton
  fi

  log "Updating panel files without touching local configuration or data..."
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

  # Never leave Laravel/Vite in development-mode or serve stale compiled Blade
  # templates after an update. A leftover public/hot file makes @vite point at
  # a non-existent dev server; stale compiled views can keep an old blank shell.
  rm -f public/hot
  php artisan optimize:clear
  rm -f storage/framework/views/*.php 2>/dev/null || true

  npm install
  npm run build

  php artisan config:cache
  php artisan route:cache || true
  php artisan view:cache || true

  chmod 755 /var/www /var/www/nodexa "$PANEL_DIR" "$PANEL_DIR/public" 2>/dev/null || true
  find "$PANEL_DIR/public" -type d -exec chmod 755 {} + 2>/dev/null || true
  find "$PANEL_DIR/public" -type f -exec chmod 644 {} + 2>/dev/null || true
  chown -R www-data:www-data storage bootstrap/cache
  chmod -R 775 storage bootstrap/cache

  # Flush PHP OPcache/process memory so the first request after an update is
  # guaranteed to execute the newly deployed Blade/PHP code.
  while read -r svc; do
    [[ -n "$svc" ]] && systemctl restart "$svc" 2>/dev/null || true
  done < <(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}')
fi

if [[ -d "$AGENT_DIR" ]]; then
  log "Updating Nodexa Agent..."
  rsync -a --delete "$SOURCE_ROOT/agent/" "$AGENT_DIR/"
  cd "$AGENT_DIR"
  go mod tidy
  go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad
  systemctl restart nodexa-agent 2>/dev/null || true
fi

bash "$SOURCE_ROOT/deploy/setup-updater.sh"

# Existing installs created before the storefront was introduced only had the
# panel FQDN in Nginx. Derive panel.example.com -> example.com, persist it in
# .env, add Nginx hostnames and expand HTTPS when DNS is ready.
if [[ -d "$PANEL_DIR" ]]; then
  bash "$SOURCE_ROOT/deploy/setup-storefront.sh"
fi

systemctl restart nodexa-queue 2>/dev/null || true
systemctl restart nodexa-monitor.timer 2>/dev/null || true
nginx -t
systemctl reload nginx
log "Update complete. Local .env, storage and server data were preserved."
