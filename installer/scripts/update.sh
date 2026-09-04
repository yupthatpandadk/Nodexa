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
 rsync -a --exclude='.env' --exclude='storage/' --exclude='bootstrap/cache/' --exclude='vendor/' --exclude='node_modules/' --exclude='public/build/' "$tmp/panel/" "$PANEL_DIR/"
 rm -rf "$tmp"
}

resolve_source_commit(){
 local candidate="${NODEXA_SOURCE_COMMIT:-}" repo branch
 if [[ "$candidate" =~ ^[0-9a-fA-F]{40}$ ]]; then printf '%s' "${candidate,,}"; return 0; fi
 repo="${NODEXA_UPDATE_REPOSITORY:-${NODEXA_REPOSITORY:-yupthatpandadk/Nodexa}}"
 branch="${NODEXA_UPDATE_BRANCH:-${NODEXA_BRANCH:-pterodactyl-core}}"
 candidate="$(curl -fsSL -H 'Accept: application/vnd.github+json' -H 'User-Agent: Nodexa-Updater' "https://api.github.com/repos/${repo}/commits/${branch}" 2>/dev/null | grep -oE '"sha"[[:space:]]*:[[:space:]]*"[0-9a-fA-F]{40}"' | head -n1 | cut -d'"' -f4 | tr 'A-F' 'a-f' || true)"
 if [[ "$candidate" =~ ^[0-9a-f]{40}$ ]]; then printf '%s' "$candidate"; return 0; fi
 printf '%s' unknown
}

ensure_panel_app_key(){
 local env_file="$PANEL_DIR/.env" current
 [[ -f "$env_file" ]] || fail "Panel .env is missing; refusing to update without local configuration."
 current="$(sed -n 's/^APP_KEY=//p' "$env_file" | tail -n1 | tr -d '\r' || true)"
 if [[ -z "$current" || "$current" == "null" || "$current" == '""' ]]; then
  current="base64:$(openssl rand -base64 32 | tr -d '\r\n')"
  sed -i '/^APP_KEY=/d' "$env_file"
  printf '\nAPP_KEY=%s\n' "$current" >> "$env_file"
  log "Recovered missing Laravel APP_KEY before update."
 fi
 chown root:www-data "$env_file"
 chmod 0640 "$env_file"
 rm -f "$PANEL_DIR/bootstrap/cache/config.php"
}

if [[ -d "$PANEL_DIR" ]]; then
 if [[ ! -f "$PANEL_DIR/public/index.php" || ! -f "$PANEL_DIR/artisan" || ! -f "$PANEL_DIR/bootstrap/app.php" ]]; then
  repair_laravel_skeleton
 fi

 log "Updating panel files without touching local configuration or data..."
 rsync -a --exclude='.env' --exclude='storage/' --exclude='bootstrap/cache/' --exclude='vendor/' --exclude='node_modules/' --exclude='public/build/' "$SOURCE_ROOT/panel/" "$PANEL_DIR/"
 cd "$PANEL_DIR"

 install -d -o www-data -g www-data storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
 chown -R www-data:www-data storage bootstrap/cache
 chmod -R 775 storage bootstrap/cache
 ensure_panel_app_key

 log "Installing locked PHP dependencies..."
 composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts
 sudo -u www-data php artisan package:discover --ansi
 sudo -u www-data php artisan migrate --force
 sudo -u www-data php artisan storage:link 2>/dev/null || true
 rm -f public/hot
 sudo -u www-data php artisan optimize:clear
 rm -f storage/framework/views/*.php 2>/dev/null || true

 bash "$SOURCE_ROOT/deploy/optimize-frontend-source.sh"
 bash "$SOURCE_ROOT/deploy/enable-managed-server-templates.sh"
 bash "$SOURCE_ROOT/deploy/enable-runtime-modules.sh"
 bash "$SOURCE_ROOT/deploy/enable-server-configuration-modules.sh"
 bash "$SOURCE_ROOT/deploy/enable-realtime-console.sh"
 bash "$SOURCE_ROOT/deploy/fix-installer-ready-ui.sh"
 bash "$SOURCE_ROOT/deploy/enable-power-feedback.sh"
 bash "$SOURCE_ROOT/deploy/optimize-frontend-delivery-source.sh"

 npm install
 npm run build
 sudo -u www-data php artisan config:cache
 sudo -u www-data php artisan route:cache || true
 sudo -u www-data php artisan view:cache || true

 chmod 755 /var/www /var/www/nodexa "$PANEL_DIR" "$PANEL_DIR/public" 2>/dev/null || true
 find "$PANEL_DIR/public" -type d -exec chmod 755 {} + 2>/dev/null || true
 find "$PANEL_DIR/public" -type f -exec chmod 644 {} + 2>/dev/null || true
 chown -R www-data:www-data storage bootstrap/cache
 chmod -R 775 storage bootstrap/cache

 while read -r svc; do
  [[ -n "$svc" ]] && systemctl restart "$svc" 2>/dev/null || true
 done < <(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}')
fi

# A normal Nodexa Panel update must never reconfigure, replace, stop, or restart
# a healthy Wings installation. Node configuration is panel-issued state and is
# intentionally preserved across panel updates.
WINGS_CONFIG="/etc/pterodactyl/config.yml"
if [[ -s "$WINGS_CONFIG" ]]; then
 log "Existing Nodexa Agent/Wings detected; preserving Node configuration and binary."
 install -d /etc/nodexa
 ln -sfn "$WINGS_CONFIG" /etc/nodexa/config.yml

 if [[ -f /etc/systemd/system/nodexa-agent.service ]]; then
  ln -sfn /etc/systemd/system/nodexa-agent.service /etc/systemd/system/wings.service
  systemctl daemon-reload
 fi

 if systemctl is-active --quiet nodexa-agent.service 2>/dev/null || systemctl is-active --quiet wings.service 2>/dev/null; then
  log "Nodexa Agent/Wings is healthy; leaving it running untouched."
 else
  log "Nodexa Agent/Wings is currently offline; attempting a non-destructive start..."
  systemctl reset-failed nodexa-agent.service wings.service 2>/dev/null || true
  if systemctl cat nodexa-agent.service >/dev/null 2>&1; then
   systemctl start nodexa-agent.service 2>/dev/null || true
  elif systemctl cat wings.service >/dev/null 2>&1; then
   systemctl start wings.service 2>/dev/null || true
  fi

  sleep 2
  if systemctl is-active --quiet nodexa-agent.service 2>/dev/null || systemctl is-active --quiet wings.service 2>/dev/null; then
   log "Nodexa Agent/Wings recovered successfully without changing its configuration."
  else
   log "WARNING: Nodexa Agent/Wings is still offline. Panel update will continue; use Admin -> Nodes -> Configuration only if the Node configuration itself needs repair."
  fi
 fi
elif [[ -x /usr/local/bin/nodexad || -f /etc/nodexa.env ]]; then
 log "Legacy Nodexa Go agent detected without a Wings configuration. Leaving it unchanged. Generate a fresh Node Auto-Deploy command in Admin -> Nodes -> Configuration to migrate this node safely."
fi

if [[ -f /etc/nginx/sites-available/nodexa-agent ]]; then
 python3 - /etc/nginx/sites-available/nodexa-agent <<'PY'
from pathlib import Path
import re,sys
p=Path(sys.argv[1]);text=p.read_text()
if re.search(r'proxy_read_timeout\s+[^;]+;',text):text=re.sub(r'proxy_read_timeout\s+[^;]+;','proxy_read_timeout 3600s;',text)
else:text=re.sub(r'(proxy_pass\s+[^;]+;)',r'\1\n        proxy_read_timeout 3600s;',text,count=1)
if re.search(r'proxy_send_timeout\s+[^;]+;',text):text=re.sub(r'proxy_send_timeout\s+[^;]+;','proxy_send_timeout 3600s;',text)
else:text=re.sub(r'(proxy_read_timeout\s+3600s;)',r'\1\n        proxy_send_timeout 3600s;',text,count=1)
if 'proxy_buffering off;' not in text:text=re.sub(r'(proxy_pass\s+[^;]+;)',r'\1\n        proxy_buffering off;',text,count=1)
p.write_text(text)
PY
fi

bash "$SOURCE_ROOT/deploy/setup-updater.sh"
if [[ -d "$PANEL_DIR" ]]; then
 bash "$SOURCE_ROOT/deploy/setup-diagnostics.sh"
 bash "$SOURCE_ROOT/deploy/setup-scheduler.sh"
 bash "$SOURCE_ROOT/deploy/setup-upload-limits.sh"
 bash "$SOURCE_ROOT/deploy/optimize-panel-runtime.sh"
 bash "$SOURCE_ROOT/deploy/setup-storefront.sh"
 bash "$SOURCE_ROOT/deploy/setup-storefront-sync.sh"
 bash "$SOURCE_ROOT/deploy/optimize-web-assets.sh"
fi

systemctl restart nodexa-queue 2>/dev/null || true
systemctl restart nodexa-monitor.timer 2>/dev/null || true
nginx -t
systemctl reload nginx
log "Update complete. Local .env, storage, Node configuration and server data were preserved."
