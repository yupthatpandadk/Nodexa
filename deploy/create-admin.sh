#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
ADMIN_EMAIL="${NODEXA_ADMIN_EMAIL:-}"
ADMIN_USERNAME="${NODEXA_ADMIN_USERNAME:-}"
ADMIN_FIRST_NAME="${NODEXA_ADMIN_FIRST_NAME:-}"
ADMIN_LAST_NAME="${NODEXA_ADMIN_LAST_NAME:-}"
ADMIN_PASSWORD="${NODEXA_ADMIN_PASSWORD:-}"

if [[ -z "$ADMIN_FIRST_NAME" ]]; then read -rp "First name: " ADMIN_FIRST_NAME; fi
if [[ -z "$ADMIN_LAST_NAME" ]]; then read -rp "Last name: " ADMIN_LAST_NAME; fi
if [[ -z "$ADMIN_USERNAME" ]]; then read -rp "Username: " ADMIN_USERNAME; fi
if [[ -z "$ADMIN_EMAIL" ]]; then read -rp "Email: " ADMIN_EMAIL; fi

[[ -n "$ADMIN_FIRST_NAME" ]] || { echo "First name is required." >&2; exit 1; }
[[ -n "$ADMIN_LAST_NAME" ]] || { echo "Last name is required." >&2; exit 1; }
[[ "$ADMIN_USERNAME" =~ ^[A-Za-z0-9._-]{3,64}$ ]] || { echo "Username must be 3-64 characters using letters, numbers, dot, underscore or dash." >&2; exit 1; }
[[ "$ADMIN_EMAIL" == *@*.* ]] || { echo "Invalid administrator email." >&2; exit 1; }

if [[ -z "$ADMIN_PASSWORD" ]]; then
 while true; do
  read -srp "Password (minimum 12 characters): " ADMIN_PASSWORD; echo
  read -srp "Confirm password: " ADMIN_PASSWORD_CONFIRM; echo
  if [[ ${#ADMIN_PASSWORD} -lt 12 ]]; then
   echo "Password must be at least 12 characters."
   ADMIN_PASSWORD=""
   continue
  fi
  if [[ "$ADMIN_PASSWORD" != "$ADMIN_PASSWORD_CONFIRM" ]]; then
   echo "Passwords do not match. Try again."
   ADMIN_PASSWORD=""
   continue
  fi
  break
 done
fi
[[ ${#ADMIN_PASSWORD} -ge 12 ]] || { echo "Administrator password must be at least 12 characters." >&2; exit 1; }

cd "$PANEL_DIR"
NODEXA_ADMIN_EMAIL="$ADMIN_EMAIL" \
NODEXA_ADMIN_USERNAME="$ADMIN_USERNAME" \
NODEXA_ADMIN_FIRST_NAME="$ADMIN_FIRST_NAME" \
NODEXA_ADMIN_LAST_NAME="$ADMIN_LAST_NAME" \
NODEXA_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
php bin/create-admin.php

PANEL_URL="$(sed -n 's/^APP_URL=//p' .env 2>/dev/null | tail -n1)"
PANEL_URL="${PANEL_URL:-check your Nodexa domain}"

cat >/root/nodexa-admin.txt <<EOF
Nodexa administrator
Panel: ${PANEL_URL}
Name: ${ADMIN_FIRST_NAME} ${ADMIN_LAST_NAME}
Username: ${ADMIN_USERNAME}
Email: ${ADMIN_EMAIL}
Password: chosen by administrator during installation (not stored here)
EOF
chmod 600 /root/nodexa-admin.txt

echo "[Nodexa] Administrator created: ${ADMIN_EMAIL} (${ADMIN_USERNAME})"
echo "[Nodexa] Account information saved to /root/nodexa-admin.txt; your password is not stored in plaintext."
