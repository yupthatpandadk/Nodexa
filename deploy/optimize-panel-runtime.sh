#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] optimize-panel-runtime.sh must run as root." >&2; exit 1; }

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
ENV_FILE="$PANEL_DIR/.env"
NGINX_SITE="/etc/nginx/sites-available/nodexa"

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }

set_env(){
 local key="$1" value="$2"
 [[ -f "$ENV_FILE" ]] || return 0
 sed -i "/^${key}=/d" "$ENV_FILE"
 printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
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

# Do not let a broken PHP request spin forever in the browser. These limits are
# deliberately generous for normal Nodexa requests, while still turning an
# actual PHP-FPM stall into a visible gateway error that can be diagnosed.
if [[ -f "$NGINX_SITE" ]]; then
 python3 - "$NGINX_SITE" <<'PY'
from pathlib import Path
import re, sys
p = Path(sys.argv[1])
text = p.read_text()
if 'fastcgi_read_timeout 20s;' not in text:
    text = re.sub(
        r'(fastcgi_pass\s+unix:[^;]+;)',
        r'\1\n        fastcgi_connect_timeout 5s;\n        fastcgi_send_timeout 20s;\n        fastcgi_read_timeout 20s;',
        text,
        count=1,
    )
p.write_text(text)
PY
 nginx -t >/dev/null
 systemctl reload nginx
fi

# Flush PHP-FPM/OPcache after changing session/cache configuration.
while read -r svc; do
 [[ -n "$svc" ]] && systemctl restart "$svc" >/dev/null 2>&1 || true
done < <(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}')

log "Panel request path optimized (file sessions/cache, Redis queue isolated)."
