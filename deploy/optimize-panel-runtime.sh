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

repair_nginx_site(){
 local file="$1"
 [[ -f "$file" ]] || return 0
 python3 - "$file" <<'PY'
from pathlib import Path
import re, sys
p = Path(sys.argv[1])
text = p.read_text()

# Remove every Nodexa FastCGI timeout directive regardless of indentation or
# whether an older updater placed more than one on the same line/context.
text = re.sub(r'fastcgi_(?:connect|send|read)_timeout\s+[^;]+;\s*', '', text)

# Re-add one canonical timeout set after every FastCGI pass in this site file.
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

# Keep formatting stable after removing inline duplicates.
text = re.sub(r'[ \t]+\n', '\n', text)
text = re.sub(r'\n{3,}', '\n\n', text)
p.write_text(text)
PY
}

[[ -f "$ENV_FILE" ]] || { echo "[Nodexa] Panel .env was not found." >&2; exit 0; }

log "Optimizing panel request path..."

# Authentication uses Sanctum bearer tokens, so normal panel page requests do
# not need Redis-backed HTTP sessions. Keeping sessions/cache on local disk
# prevents a stopped, busy or misconfigured Redis service from delaying the
# HTML shell and leaving browsers on a blank/loading page.
set_env SESSION_DRIVER file
set_env CACHE_STORE file

# Redis remains available for asynchronous queues, where a temporary Redis
# problem must never block the initial panel HTML response.
systemctl enable --now redis-server >/dev/null 2>&1 || true

mkdir -p \
 "$PANEL_DIR/storage/framework/sessions" \
 "$PANEL_DIR/storage/framework/views" \
 "$PANEL_DIR/storage/framework/cache/data" \
 "$PANEL_DIR/bootstrap/cache"
chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

cd "$PANEL_DIR"
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache >/dev/null 2>&1 || true
php artisan view:cache >/dev/null 2>&1 || true

# Normal browser bootstrap requests have their own short client timeout. Some
# administrator operations, especially first-time server provisioning, may need
# up to three minutes while a Node pulls a Docker image. Repair BOTH the
# sites-available source and the active sites-enabled file: older installs could
# have sites-enabled/nodexa as a copied file instead of a symlink, which is why
# repairing only sites-available did not clear duplicate directives.
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

# Flush PHP-FPM/OPcache after changing session/cache configuration.
while read -r svc; do
 [[ -n "$svc" ]] && systemctl restart "$svc" >/dev/null 2>&1 || true
done < <(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}')

log "Panel request path optimized (file sessions/cache, Redis queue isolated)."
