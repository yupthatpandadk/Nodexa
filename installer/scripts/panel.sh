#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
ask_domain
log "Installing Nodexa Panel. The current preview installer also installs a local Agent required by the panel runtime."
cd "$SOURCE_ROOT"
NODEXA_DOMAIN="${NODEXA_DOMAIN:-_}" bash deploy/install-runtime.sh
