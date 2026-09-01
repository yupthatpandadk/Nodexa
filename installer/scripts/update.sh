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
  composer create-project laravel/laravel:^12.0 "$tmp/panel" --no-interaction --prefer-dist --no-scripts >/dev/null

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

  rm -f public/hot
  php artisan optimize:clear
  rm -f storage/framework/views/*.php 2>/dev/null || true

  bash "$SOURCE_ROOT/deploy/optimize-frontend-source.sh"
  bash "$SOURCE_ROOT/deploy/enable-managed-server-templates.sh"
  bash "$SOURCE_ROOT/deploy/optimize-frontend-delivery-source.sh"
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

  while read -r svc; do
    [[ -n "$svc" ]] && systemctl restart "$svc" 2>/dev/null || true
  done < <(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}')
fi

if [[ -d "$AGENT_DIR" ]]; then
  log "Updating Nodexa Agent files..."
  rsync -a --delete "$SOURCE_ROOT/agent/" "$AGENT_DIR/"
  cd "$AGENT_DIR"
  go mod tidy
  go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad

  AGENT_TOKEN=""
  if [[ -f /etc/nodexa.env ]]; then
    AGENT_TOKEN="$(sed -n 's/^NODEXA_TOKEN=//p' /etc/nodexa.env | tail -n1)"
    AGENT_TOKEN="${AGENT_TOKEN%\"}"
    AGENT_TOKEN="${AGENT_TOKEN#\"}"
    AGENT_TOKEN="${AGENT_TOKEN%\'}"
    AGENT_TOKEN="${AGENT_TOKEN#\'}"
  fi

  if [[ -n "$AGENT_TOKEN" ]]; then
    log "Restarting configured Nodexa Agent..."
    systemctl enable nodexa-agent >/dev/null 2>&1 || true
    systemctl restart nodexa-agent
  else
    log "Agent is not configured on this host; leaving nodexa-agent stopped."
    systemctl disable --now nodexa-agent >/dev/null 2>&1 || true
  fi
fi

if [[ -f /etc/nginx/sites-available/nodexa-agent ]]; then
  python3 - /etc/nginx/sites-available/nodexa-agent <<'PY'
from pathlib import Path
import re, sys
p = Path(sys.argv[1])
text = p.read_text()
if re.search(r'proxy_read_timeout\s+[^;]+;', text):
    text = re.sub(r'proxy_read_timeout\s+[^;]+;', 'proxy_read_timeout 210s;', text)
else:
    text = re.sub(r'(proxy_pass\s+[^;]+;)', r'\1\n        proxy_read_timeout 210s;', text, count=1)
if re.search(r'proxy_send_timeout\s+[^;]+;', text):
    text = re.sub(r'proxy_send_timeout\s+[^;]+;', 'proxy_send_timeout 210s;', text)
else:
    text = re.sub(r'(proxy_read_timeout\s+210s;)', r'\1\n        proxy_send_timeout 210s;', text, count=1)
p.write_text(text)
PY
fi

bash "$SOURCE_ROOT/deploy/setup-updater.sh"

if [[ -d "$PANEL_DIR" ]]; then
  bash "$SOURCE_ROOT/deploy/optimize-panel-runtime.sh"
  bash "$SOURCE_ROOT/deploy/setup-storefront.sh"
  bash "$SOURCE_ROOT/deploy/setup-storefront-sync.sh"
  bash "$SOURCE_ROOT/deploy/optimize-web-assets.sh"
fi

systemctl restart nodexa-queue 2>/dev/null || true
systemctl restart nodexa-monitor.timer 2>/dev/null || true
nginx -t
systemctl reload nginx
log "Update complete. Local .env, storage and server data were preserved."
