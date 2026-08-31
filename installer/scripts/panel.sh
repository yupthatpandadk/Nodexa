#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
ask_domain
log "Installing Nodexa Panel..."
cd "$SOURCE_ROOT"
NODEXA_DOMAIN="${NODEXA_DOMAIN:-_}" bash deploy/install-runtime.sh

# Node identity must be created from Nodexa Panel. Never leave the legacy
# bootstrap token active after a panel-only installation.
if [[ -f /etc/nodexa.env ]]; then
  sed -i 's/^NODEXA_TOKEN=.*/NODEXA_TOKEN=/' /etc/nodexa.env
fi
systemctl disable --now nodexa-agent 2>/dev/null || true

bash deploy/setup-database-tools.sh
bash deploy/setup-updater.sh
bash deploy/create-admin.sh
log "Panel installed. Nodexa Agent is WAITING FOR CONFIGURATION."
echo "Create/configure Nodes from Admin → Nodes inside Nodexa."
echo "Future updates can be installed from Admin → Opdateringer."
