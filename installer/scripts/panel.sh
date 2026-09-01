#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
setup_wizard

log "Installing Nodexa Panel..."
cd "$SOURCE_ROOT"
bash deploy/install-runtime.sh

# Node identity must be created from Nodexa Panel. Never leave the bootstrap
# Agent token active after a panel-only installation.
if [[ -f /etc/nodexa.env ]]; then
 sed -i 's/^NODEXA_TOKEN=.*/NODEXA_TOKEN=/' /etc/nodexa.env
fi
systemctl disable --now nodexa-agent 2>/dev/null || true

bash deploy/setup-database-tools.sh
bash deploy/setup-updater.sh
NODEXA_OPEN_AGENT_PORT=0 bash deploy/setup-firewall.sh
# The storefront is derived automatically from panel.example.com -> example.com.
# Configure routing before SSL, then run it once more after SSL so an existing
# certificate can be expanded to the storefront hostnames.
bash deploy/setup-storefront.sh
bash deploy/setup-ssl.sh
bash deploy/setup-storefront.sh
bash deploy/create-admin.sh

PANEL_URL="$(sed -n 's/^APP_URL=//p' /var/www/nodexa/panel/.env 2>/dev/null | tail -n1)"
PANEL_URL="${PANEL_URL:-http://${NODEXA_DOMAIN}}"
STORE_DOMAIN="$(sed -n 's/^NODEXA_STOREFRONT_DOMAIN=//p' /var/www/nodexa/panel/.env 2>/dev/null | tail -n1)"

log "Nodexa Panel installation complete."
echo ""
echo "  Storefront:     $([[ -n "$STORE_DOMAIN" ]] && echo "https://${STORE_DOMAIN}" || echo 'not configured')"
echo "  Panel:          ${PANEL_URL}"
echo "  Administrator:  ${NODEXA_ADMIN_USERNAME} <${NODEXA_ADMIN_EMAIL}>"
echo "  Account info:   /root/nodexa-admin.txt"
echo "  Updates:        Admin → Opdateringer"
echo "  SSL status:     /var/lib/nodexa/ssl.json"
echo ""
echo "Nodexa Agent is WAITING FOR CONFIGURATION."
echo "Create/configure Nodes from Admin → Nodes inside Nodexa."
