#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] optimize-panel-runtime.sh must run as root." >&2; exit 1; }

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
ENV_FILE="$PANEL_DIR/.env"
NGINX_AVAILABLE="/etc/nginx/sites-available/nodexa"
NGINX_ENABLED="/etc/nginx/sites-enabled/nodexa"

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }

set_env(){
 local key="$1" value="$2"
 [[ -f "$ENV_FILE" ]] || return 0
 sed -i "/^${key}=/d" "$ENV_FILE"
 printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
}

ensure_app_key(){
 [[ -f "$ENV_FILE" ]] || return 1
 local current
 current="$(sed -n 's/^APP_KEY=//p' "$ENV_FILE" | tail -n1 | tr -d '\r' || true)"
 if [[ -z "$current" || "$current" == "null" || "$current" == '""' ]]; then
  current="base64:$(openssl rand -base64 32 | tr -d '\r\n')"
  sed -i '/^APP_KEY=/d' "$ENV_FILE"
  printf '\nAPP_KEY=%s\n' "$current" >> "$ENV_FILE"
  log "Recovered missing Laravel APP_KEY before runtime optimization."
 fi
 chown root:www-data "$ENV_FILE"
 chmod 0640 "$ENV_FILE"
}

repair_nginx_site(){
 local file="$1"
 [[ -f "$file" ]] || return 0
 python3 - "$file" <<'PY'
from pathlib import Path
import re, sys
p = Path(sys.argv[1])
text = p.read_text()

text = re.sub(r'fastcgi_(?:connect|send|read)_timeout\s+[^;]+;\s*', '', text)

def add_timeouts(match):
    indent = match.group('indent')
    return (
        f"{match.group(0)}\n"
        f"{indent}fastcgi_connect_timeout 5s;\n"
        f"{indent}fastcgi_send_timeout 210s;\n"
        f"{indent}fastcgi_read_timeout 210s;"
    )

text = re.sub(
    r'(?P<indent>^[ \t]*)(?:fastcgi_pass\s+[^;]+;)',
    add_timeouts,
    text,
    flags=re.M,
)

text = re.sub(r'[ \t]+\n', '\n', text)
text = re.sub(r'\n{3,}', '\n\n', text)
p.write_text(text)
PY
}

[[ -f "$ENV_FILE" ]] || { echo "[Nodexa] Panel .env was not found." >&2; exit 0; }

log "Optimizing panel request path..."

# Ensure the web user can read the environment before any Artisan command is
# executed. Never rotate an existing APP_KEY; only recover a genuinely missing
# key so encrypted application data remains valid across updates.
ensure_app_key

set_env SESSION_DRIVER file
set_env CACHE_STORE file
chown root:www-data "$ENV_FILE"
chmod 0640 "$ENV_FILE"

systemctl enable --now redis-server >/dev/null 2>&1 || true

mkdir -p \
 "$PANEL_DIR/storage/framework/sessions" \
 "$PANEL_DIR/storage/framework/views" \
 "$PANEL_DIR/storage/framework/cache/data" \
 "$PANEL_DIR/storage/logs" \
 "$PANEL_DIR/bootstrap/cache"
chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

cd "$PANEL_DIR"
# A stale root-created config cache can contain an empty APP_KEY. Remove only
# the compiled config before booting Laravel, then rebuild all runtime caches as
# the same user PHP-FPM runs under.
rm -f bootstrap/cache/config.php
sudo -u www-data php artisan optimize:clear >/dev/null
sudo -u www-data php artisan config:cache >/dev/null
sudo -u www-data php artisan route:cache >/dev/null 2>&1 || true
sudo -u www-data php artisan view:cache >/dev/null 2>&1 || true

# Re-assert ownership because Laravel may create new cache/view files above.
chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
find "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" -type d -exec chmod 775 {} + 2>/dev/null || true
find "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" -type f -exec chmod 664 {} + 2>/dev/null || true

repair_nginx_site "$NGINX_AVAILABLE"

if [[ -e "$NGINX_ENABLED" || -L "$NGINX_ENABLED" ]]; then
 if [[ -L "$NGINX_ENABLED" ]]; then
  enabled_target="$(readlink -f "$NGINX_ENABLED" 2>/dev/null || true)"
  available_target="$(readlink -f "$NGINX_AVAILABLE" 2>/dev/null || true)"
  if [[ -z "$enabled_target" || "$enabled_target" != "$available_target" ]]; then
   repair_nginx_site "$NGINX_ENABLED"
  fi
 else
  repair_nginx_site "$NGINX_ENABLED"
 fi
fi

if [[ -f "$NGINX_AVAILABLE" || -f "$NGINX_ENABLED" ]]; then
 nginx -t >/dev/null
 systemctl reload nginx
fi

while read -r svc; do
 [[ -n "$svc" ]] && systemctl restart "$svc" >/dev/null 2>&1 || true
done < <(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}')

log "Panel request path optimized (file sessions/cache, Redis queue isolated)."
