#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
ask_domain
log "Installing Nodexa Panel + Agent..."
cd "$SOURCE_ROOT"
NODEXA_DOMAIN="${NODEXA_DOMAIN:-_}" bash deploy/install-runtime.sh
bash deploy/setup-database-tools.sh
log "Panel + Agent installed. Register/configure the Node from Admin → Nodes inside Nodexa."
