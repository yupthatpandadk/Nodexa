#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
setup_wizard

log "Installing Nodexa Panel + Agent..."
cd "$SOURCE_ROOT"
bash deploy/install-runtime.sh

if [[ -f /etc/nodexa.env ]]; then
 sed -i 's/^NODEXA_TOKEN=.*/NODEXA_TOKEN=/' /etc/nodexa.env
fi
systemctl disable --now nodexa-agent 2>/dev/null || true

bash deploy/setup-database-tools.sh
bash deploy/setup-updater.sh
NODEXA_OPEN_AGENT_PORT=1 bash deploy/setup-firewall.sh
bash deploy/setup-storefront.sh
bash deploy/setup-ssl.sh
bash deploy/setup-storefront.sh
bash deploy/setup-storefront-sync.sh
bash deploy/optimize-panel-runtime.sh
bash deploy/optimize-frontend-source.sh
bash deploy/enable-managed-server-templates.sh
bash deploy/enable-runtime-modules.sh
bash deploy/optimize-frontend-delivery-source.sh
(cd /var/www/nodexa/panel && npm run build)
bash deploy/optimize-web-assets.sh
bash deploy/setup-upload-limits.sh
bash deploy/setup-scheduler.sh
bash deploy/create-admin.sh

PANEL_URL="$(sed -n 's/^APP_URL=//p' /var/www/nodexa/panel/.env 2>/dev/null | tail -n1)"
PANEL_URL="${PANEL_URL:-http://${NODEXA_DOMAIN}}"
STORE_DOMAIN="$(sed -n 's/^NODEXA_STOREFRONT_DOMAIN=//p' /var/www/nodexa/panel/.env 2>/dev/null | tail -n1)"

log "Nodexa Panel + Agent installation complete."
echo ""
echo "  Storefront:     $([[ -n "$STORE_DOMAIN" ]] && echo "https://${STORE_DOMAIN}" || echo 'configure in Admin → Storefronts')"
echo "  Multisite:      Admin → Storefronts"
echo "  Panel:          ${PANEL_URL}"
echo "  Administrator:  ${NODEXA_ADMIN_USERNAME} <${NODEXA_ADMIN_EMAIL}>"
echo "  Account info:   /root/nodexa-admin.txt"
echo "  Updates:        Admin → Opdateringer"
echo "  SSL status:     /var/lib/nodexa/ssl.json"
echo ""
echo "Agent status: WAITING FOR CONFIGURATION."
echo "Log in to Nodexa → Admin → Nodes, create the Node, then run its generated command."
