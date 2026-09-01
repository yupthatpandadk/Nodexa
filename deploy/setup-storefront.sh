#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] setup-storefront.sh must run as root." >&2; exit 1; }

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
ENV_FILE="$PANEL_DIR/.env"
NGINX_SITE="/etc/nginx/sites-available/nodexa"

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }
warn(){ printf '\n\033[1;33m[Nodexa WARN]\033[0m %s\n' "$*" >&2; }
normalize_domain(){ local v="$1"; v="${v#http://}"; v="${v#https://}"; v="${v%%/*}"; v="${v%%:*}"; v="${v%.}"; printf '%s' "$v"; }
valid_domain(){ [[ "$1" =~ ^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$ ]]; }

[[ -f "$ENV_FILE" ]] || { warn "Panel .env not found; storefront setup skipped."; exit 0; }
APP_URL="$(sed -n 's/^APP_URL=//p' "$ENV_FILE" | tail -n1 | tr -d '\"' | tr -d "'" || true)"
PANEL_DOMAIN="${NODEXA_DOMAIN:-$(normalize_domain "$APP_URL")}"; PANEL_DOMAIN="$(normalize_domain "$PANEL_DOMAIN")"

# Keep the legacy default domain because it is useful during first install and
# for migration from pre-multisite versions.
CURRENT_STORE="$(sed -n 's/^NODEXA_STOREFRONT_DOMAIN=//p' "$ENV_FILE" | tail -n1 | tr -d '\"' | tr -d "'" || true)"
STORE_DOMAIN="${NODEXA_STOREFRONT_DOMAIN:-$CURRENT_STORE}"; STORE_DOMAIN="$(normalize_domain "$STORE_DOMAIN")"
if [[ -z "$STORE_DOMAIN" && "$PANEL_DOMAIN" == panel.* ]]; then STORE_DOMAIN="${PANEL_DOMAIN#panel.}"; fi
if [[ -n "$STORE_DOMAIN" && "$STORE_DOMAIN" != "$PANEL_DOMAIN" ]] && valid_domain "$STORE_DOMAIN"; then
 sed -i '/^NODEXA_STOREFRONT_DOMAIN=/d' "$ENV_FILE"
 printf '\nNODEXA_STOREFRONT_DOMAIN=%s\n' "$STORE_DOMAIN" >> "$ENV_FILE"
fi

cd "$PANEL_DIR"
# After migration, the database is the source of truth for multisite domains.
DB_DOMAINS="$(php artisan nodexa:storefront-domains --plain 2>/dev/null | tail -n1 || true)"
DOMAINS=()
add_domain(){
 local d; d="$(normalize_domain "$1")"
 [[ -n "$d" && "$d" != "$PANEL_DOMAIN" ]] || return 0
 valid_domain "$d" || { warn "Ignoring invalid storefront domain: $d"; return 0; }
 for old in "${DOMAINS[@]:-}"; do [[ "$old" == "$d" ]] && return 0; done
 DOMAINS+=("$d")
}
for d in $DB_DOMAINS; do add_domain "$d"; done
if ((${#DOMAINS[@]} == 0)) && [[ -n "$STORE_DOMAIN" ]]; then add_domain "$STORE_DOMAIN"; add_domain "www.$STORE_DOMAIN"; fi

if [[ -f "$NGINX_SITE" ]]; then
 python3 - "$NGINX_SITE" "$PANEL_DOMAIN" "${DOMAINS[*]}" <<'PY'
import re, sys
from pathlib import Path
p=Path(sys.argv[1]); panel=sys.argv[2]; stores=sys.argv[3].split()
text=p.read_text()
domains=[panel,*stores]
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

php artisan optimize:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache >/dev/null 2>&1 || true
php artisan view:cache >/dev/null 2>&1 || true

if ((${#DOMAINS[@]})); then
 log "Multisite storefront routing active for: ${DOMAINS[*]}"
else
 warn "No active storefront domains found yet. Add one in Admin → Storefronts."
fi

# Expand the panel certificate with every active storefront domain that already
# resolves in DNS. Let's Encrypt currently allows up to 100 names/certificate;
# keep headroom for the panel and future aliases.
if [[ "$APP_URL" == https://* ]] && ((${#DOMAINS[@]})); then
 EMAIL="$(sed -n 's/^MAIL_FROM_ADDRESS=//p' "$ENV_FILE" | tail -n1 | tr -d '\"' | tr -d "'" || true)"
 [[ "$EMAIL" == *@*.* ]] || EMAIL="admin@${DOMAINS[0]}"
 CERT_DOMAINS=("$PANEL_DOMAIN")
 for d in "${DOMAINS[@]}"; do
  ((${#CERT_DOMAINS[@]} < 90)) || { warn "Certificate domain limit reached; remaining storefront domains need a separate certificate."; break; }
  if getent ahostsv4 "$d" >/dev/null 2>&1; then CERT_DOMAINS+=("$d"); else warn "DNS for ${d} is not ready; HTTPS skipped for this hostname for now."; fi
 done
 if ((${#CERT_DOMAINS[@]} > 1)); then
  export DEBIAN_FRONTEND=noninteractive
  command -v certbot >/dev/null 2>&1 || { apt-get update -y >/dev/null && apt-get install -y certbot python3-certbot-nginx >/dev/null; }
  ARGS=(); for d in "${CERT_DOMAINS[@]}"; do ARGS+=( -d "$d" ); done
  if certbot --nginx --non-interactive --agree-tos --redirect --no-eff-email --expand --email "$EMAIL" "${ARGS[@]}"; then
   nginx -t && systemctl reload nginx
   log "HTTPS certificate includes: ${CERT_DOMAINS[*]}"
  else
   warn "Could not expand Let's Encrypt automatically. Check DNS and rerun the Nodexa updater."
  fi
 fi
fi
