#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
ask_domain
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
bash deploy/create-admin.sh
log "Panel + Agent installed. Agent status: WAITING FOR CONFIGURATION."
echo "Log in to Nodexa → Admin → Nodes, create the Node, then run its generated command."
