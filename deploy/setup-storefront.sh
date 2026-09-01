#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] setup-storefront.sh must run as root." >&2; exit 1; }

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
ENV_FILE="$PANEL_DIR/.env"
NGINX_SITE="/etc/nginx/sites-available/nodexa"

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }
warn(){ printf '\n\033[1;33m[Nodexa WARN]\033[0m %s\n' "$*" >&2; }

normalize_domain(){
 local v="$1"
 v="${v#http://}"; v="${v#https://}"; v="${v%%/*}"; v="${v%%:*}"; v="${v%.}"
 printf '%s' "$v"
}
valid_domain(){ [[ "$1" =~ ^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$ ]]; }

[[ -f "$ENV_FILE" ]] || { warn "Panel .env not found; storefront setup skipped."; exit 0; }

APP_URL="$(sed -n 's/^APP_URL=//p' "$ENV_FILE" | tail -n1 | tr -d '\"' | tr -d "'" || true)"
PANEL_DOMAIN="${NODEXA_DOMAIN:-$(normalize_domain "$APP_URL")}" 
PANEL_DOMAIN="$(normalize_domain "$PANEL_DOMAIN")"

CURRENT_STORE="$(sed -n 's/^NODEXA_STOREFRONT_DOMAIN=//p' "$ENV_FILE" | tail -n1 | tr -d '\"' | tr -d "'" || true)"
STORE_DOMAIN="${NODEXA_STOREFRONT_DOMAIN:-$CURRENT_STORE}"
STORE_DOMAIN="$(normalize_domain "$STORE_DOMAIN")"
if [[ -z "$STORE_DOMAIN" && "$PANEL_DOMAIN" == panel.* ]]; then
 STORE_DOMAIN="${PANEL_DOMAIN#panel.}"
fi

if [[ -z "$STORE_DOMAIN" || "$STORE_DOMAIN" == "$PANEL_DOMAIN" ]] || ! valid_domain "$STORE_DOMAIN"; then
 warn "Could not derive a separate storefront domain from panel domain '${PANEL_DOMAIN}'."
 warn "Set NODEXA_STOREFRONT_DOMAIN=example.com and rerun the updater if needed."
 exit 0
fi

# Persist the storefront hostname for Laravel host-aware routing.
sed -i '/^NODEXA_STOREFRONT_DOMAIN=/d' "$ENV_FILE"
printf '\nNODEXA_STOREFRONT_DOMAIN=%s\n' "$STORE_DOMAIN" >> "$ENV_FILE"

if [[ -f "$NGINX_SITE" ]]; then
 python3 - "$NGINX_SITE" "$PANEL_DOMAIN" "$STORE_DOMAIN" <<'PY'
import re, sys
from pathlib import Path
p=Path(sys.argv[1]); panel=sys.argv[2]; store=sys.argv[3]
text=p.read_text()
domains=[panel,store,'www.'+store]
def repl(m):
    existing=m.group(1).split()
    merged=[]
    for d in existing+domains:
        if d and d!='_' and d not in merged: merged.append(d)
    return 'server_name ' + ' '.join(merged) + ';'
text=re.sub(r'server_name\s+([^;]+);', repl, text)
p.write_text(text)
PY
 nginx -t
 systemctl reload nginx
fi

cd "$PANEL_DIR"
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache >/dev/null 2>&1 || true
php artisan view:cache >/dev/null 2>&1 || true

log "Storefront routing active for ${STORE_DOMAIN}."

# If the panel already uses HTTPS, expand the certificate to the storefront
# domains when DNS is ready. Missing www DNS is harmless; it is simply skipped.
if [[ "$APP_URL" == https://* ]]; then
 EMAIL="$(sed -n 's/^MAIL_FROM_ADDRESS=//p' "$ENV_FILE" | tail -n1 | tr -d '\"' | tr -d "'" || true)"
 [[ "$EMAIL" == *@*.* ]] || EMAIL="admin@${STORE_DOMAIN}"
 CERT_DOMAINS=("$PANEL_DOMAIN")
 for d in "$STORE_DOMAIN" "www.$STORE_DOMAIN"; do
  if getent ahostsv4 "$d" >/dev/null 2>&1; then CERT_DOMAINS+=("$d"); else warn "DNS for ${d} is not ready; HTTPS for this hostname will be skipped for now."; fi
 done
 if ((${#CERT_DOMAINS[@]} > 1)); then
  export DEBIAN_FRONTEND=noninteractive
  command -v certbot >/dev/null 2>&1 || { apt-get update -y >/dev/null && apt-get install -y certbot python3-certbot-nginx >/dev/null; }
  ARGS=()
  for d in "${CERT_DOMAINS[@]}"; do ARGS+=( -d "$d" ); done
  if certbot --nginx --non-interactive --agree-tos --redirect --no-eff-email --expand --email "$EMAIL" "${ARGS[@]}"; then
   nginx -t && systemctl reload nginx
   log "HTTPS certificate includes: ${CERT_DOMAINS[*]}"
  else
   warn "Could not expand the Let's Encrypt certificate automatically. Storefront remains configured in Nginx; check DNS and rerun update later."
  fi
 fi
fi
