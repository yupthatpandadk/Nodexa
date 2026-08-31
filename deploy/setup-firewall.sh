#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] setup-firewall.sh must run as root." >&2; exit 1; }

CONFIGURE="${NODEXA_CONFIGURE_UFW:-0}"
OPEN_AGENT_PORT="${NODEXA_OPEN_AGENT_PORT:-0}"
AGENT_PORT="${NODEXA_AGENT_PORT:-8080}"

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }

if [[ "$CONFIGURE" != "1" ]]; then
 log "UFW firewall configuration skipped."
 exit 0
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y ufw

log "Configuring UFW firewall..."
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null

if [[ "$OPEN_AGENT_PORT" == "1" ]]; then
 ufw allow "${AGENT_PORT}/tcp" >/dev/null
fi

ufw --force enable >/dev/null
ufw status verbose

log "UFW is enabled. SSH, HTTP and HTTPS are allowed.$([[ "$OPEN_AGENT_PORT" == "1" ]] && printf ' Nodexa Agent port %s/tcp is also allowed.' "$AGENT_PORT")"
