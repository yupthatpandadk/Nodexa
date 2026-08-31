#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
ADMIN_EMAIL="${NODEXA_ADMIN_EMAIL:-}"
ADMIN_NAME="${NODEXA_ADMIN_NAME:-Administrator}"
ADMIN_PASSWORD="${NODEXA_ADMIN_PASSWORD:-}"

if [[ -z "$ADMIN_EMAIL" ]]; then
  read -rp "Nodexa administrator email: " ADMIN_EMAIL
fi
[[ "$ADMIN_EMAIL" == *@*.* ]] || { echo "Invalid administrator email." >&2; exit 1; }

if [[ -z "$ADMIN_PASSWORD" ]]; then
  ADMIN_PASSWORD="$(openssl rand -base64 24 | tr -d '\n/=+' | cut -c1-24)"
fi
[[ ${#ADMIN_PASSWORD} -ge 12 ]] || { echo "Administrator password must be at least 12 characters." >&2; exit 1; }

cd "$PANEL_DIR"
NODEXA_ADMIN_EMAIL="$ADMIN_EMAIL" NODEXA_ADMIN_NAME="$ADMIN_NAME" NODEXA_ADMIN_PASSWORD="$ADMIN_PASSWORD" php bin/create-admin.php

cat >/root/nodexa-admin.txt <<EOF
Nodexa administrator
Panel: ${APP_URL:-check your Nodexa domain}
Email: ${ADMIN_EMAIL}
Password: ${ADMIN_PASSWORD}
EOF
chmod 600 /root/nodexa-admin.txt

echo "[Nodexa] Administrator created: ${ADMIN_EMAIL}"
echo "[Nodexa] Initial credentials saved to /root/nodexa-admin.txt"
