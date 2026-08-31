#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] setup-ssl.sh must run as root." >&2; exit 1; }

SELF="$(readlink -f "${BASH_SOURCE[0]}")"
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
DOMAIN="${NODEXA_DOMAIN:-_}"
ENABLE_SSL="${NODEXA_ENABLE_SSL:-0}"
SSL_EMAIL="${NODEXA_SSL_EMAIL:-${NODEXA_ADMIN_EMAIL:-}}"
STATE_DIR="/var/lib/nodexa"

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }
warn(){ printf '\n\033[1;33m[Nodexa WARN]\033[0m %s\n' "$*" >&2; }

mkdir -p "$STATE_DIR"
if [[ "$SELF" != "/usr/local/sbin/nodexa-ssl-setup" ]]; then
 install -m 0755 "$SELF" /usr/local/sbin/nodexa-ssl-setup
fi

set_app_url(){
 local url="$1"
 [[ -f "$PANEL_DIR/.env" ]] || return 0
 sed -i '/^APP_URL=/d' "$PANEL_DIR/.env"
 printf '\nAPP_URL=%s\n' "$url" >> "$PANEL_DIR/.env"
 cd "$PANEL_DIR"
 php artisan optimize:clear >/dev/null 2>&1 || true
 php artisan config:cache >/dev/null 2>&1 || true
}

if [[ "$DOMAIN" == "_" || -z "$DOMAIN" ]]; then
 SERVER_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
 [[ -n "$SERVER_IP" ]] && set_app_url "http://${SERVER_IP}"
 printf '{"enabled":false,"status":"ip_only","updated_at":"%s"}\n' "$(date --iso-8601=seconds)" > "$STATE_DIR/ssl.json"
 chmod 0644 "$STATE_DIR/ssl.json"
 log "No panel domain configured; HTTPS setup skipped."
 exit 0
fi

set_app_url "http://${DOMAIN}"

if [[ "$ENABLE_SSL" != "1" ]]; then
 printf '{"enabled":false,"status":"disabled","domain":"%s","updated_at":"%s"}\n' "$DOMAIN" "$(date --iso-8601=seconds)" > "$STATE_DIR/ssl.json"
 chmod 0644 "$STATE_DIR/ssl.json"
 log "Let's Encrypt was not enabled. Panel remains available over HTTP."
 exit 0
fi

if [[ -z "$SSL_EMAIL" || "$SSL_EMAIL" != *@*.* ]]; then
 warn "Let's Encrypt email is missing or invalid. HTTPS setup skipped."
 printf '{"enabled":false,"status":"invalid_email","domain":"%s","updated_at":"%s"}\n' "$DOMAIN" "$(date --iso-8601=seconds)" > "$STATE_DIR/ssl.json"
 chmod 0644 "$STATE_DIR/ssl.json"
 exit 0
fi

log "Checking DNS for ${DOMAIN}..."
RESOLVED="$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk '{print $1}' | sort -u | tr '\n' ' ' | sed 's/[[:space:]]*$//' || true)"
if [[ -z "$RESOLVED" ]]; then
 warn "${DOMAIN} does not resolve yet. Nodexa installation will finish over HTTP."
 warn "After DNS has propagated, run: NODEXA_DOMAIN=${DOMAIN} NODEXA_ENABLE_SSL=1 NODEXA_SSL_EMAIL=${SSL_EMAIL} nodexa-ssl-setup"
 printf '{"enabled":false,"status":"waiting_for_dns","domain":"%s","updated_at":"%s"}\n' "$DOMAIN" "$(date --iso-8601=seconds)" > "$STATE_DIR/ssl.json"
 chmod 0644 "$STATE_DIR/ssl.json"
 exit 0
fi

PUBLIC_IP="$(curl -4 -fsSL --max-time 5 https://api.ipify.org 2>/dev/null || true)"
if [[ -n "$PUBLIC_IP" ]] && ! grep -qw "$PUBLIC_IP" <<<"$RESOLVED"; then
 warn "DNS resolves to: ${RESOLVED}; this server's detected public IP is ${PUBLIC_IP}."
 warn "This may be expected when using a proxy/CDN. Let's Encrypt will still be attempted."
fi

log "Installing Certbot and requesting a Let's Encrypt certificate..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y certbot python3-certbot-nginx

if certbot --nginx \
  --non-interactive \
  --agree-tos \
  --email "$SSL_EMAIL" \
  --redirect \
  --no-eff-email \
  -d "$DOMAIN"; then
 set_app_url "https://${DOMAIN}"
 systemctl enable --now certbot.timer >/dev/null 2>&1 || true
 nginx -t
 systemctl reload nginx
 printf '{"enabled":true,"status":"active","domain":"%s","url":"https://%s","updated_at":"%s"}\n' "$DOMAIN" "$DOMAIN" "$(date --iso-8601=seconds)" > "$STATE_DIR/ssl.json"
 chmod 0644 "$STATE_DIR/ssl.json"
 log "HTTPS is active: https://${DOMAIN}"
else
 warn "Let's Encrypt could not issue the certificate. Nodexa remains available at http://${DOMAIN}."
 warn "Check DNS and ports 80/443, then run: NODEXA_DOMAIN=${DOMAIN} NODEXA_ENABLE_SSL=1 NODEXA_SSL_EMAIL=${SSL_EMAIL} nodexa-ssl-setup"
 printf '{"enabled":false,"status":"certbot_failed","domain":"%s","updated_at":"%s"}\n' "$DOMAIN" "$(date --iso-8601=seconds)" > "$STATE_DIR/ssl.json"
 chmod 0644 "$STATE_DIR/ssl.json"
 exit 0
fi
