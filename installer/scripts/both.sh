#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
setup_wizard

log "Installing Nodexa Panel + Agent..."
cd "$SOURCE_ROOT"
NODEXA_DOMAIN="${NODEXA_DOMAIN:-_}" bash deploy/install-runtime.sh

# The Agent binary/service is installed, but its identity must come from
# Admin → Nodes in Nodexa Panel.
if [[ -f /etc/nodexa.env ]]; then
 sed -i 's/^NODEXA_TOKEN=.*/NODEXA_TOKEN=/' /etc/nodexa.env
fi
systemctl disable --now nodexa-agent 2>/dev/null || true

bash deploy/setup-database-tools.sh
bash deploy/setup-updater.sh
NODEXA_DOMAIN="$NODEXA_DOMAIN" NODEXA_ENABLE_SSL="${NODEXA_ENABLE_SSL:-0}" NODEXA_SSL_EMAIL="${NODEXA_SSL_EMAIL:-}" NODEXA_ADMIN_EMAIL="$NODEXA_ADMIN_EMAIL" bash deploy/setup-ssl.sh
NODEXA_ADMIN_EMAIL="$NODEXA_ADMIN_EMAIL" NODEXA_ADMIN_NAME="$NODEXA_ADMIN_NAME" NODEXA_ADMIN_PASSWORD="${NODEXA_ADMIN_PASSWORD:-}" bash deploy/create-admin.sh

PANEL_URL="$(sed -n 's/^APP_URL=//p' /var/www/nodexa/panel/.env 2>/dev/null | tail -n1)"
PANEL_URL="${PANEL_URL:-http://${NODEXA_DOMAIN}}"

log "Nodexa Panel + Agent installation complete."
echo ""
echo "  Panel:        ${PANEL_URL}"
echo "  Administrator: ${NODEXA_ADMIN_EMAIL}"
echo "  Credentials:  /root/nodexa-admin.txt"
echo "  Updates:      Admin → Opdateringer"
echo "  SSL status:   /var/lib/nodexa/ssl.json"
echo ""
echo "Agent status: WAITING FOR CONFIGURATION."
echo "Log in to Nodexa → Admin → Nodes, create the Node, then run its generated command."
