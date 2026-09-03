#!/usr/bin/env bash
set -Eeuo pipefail

log(){ echo "[Nodexa] $*"; }
fail(){ echo "[Nodexa] ERROR: $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || fail "Run as root (sudo -i)."

PANEL_URL="${NODEXA_PANEL_URL:-}"
TOKEN="${NODEXA_AGENT_TOKEN:-}"
NODE_ID="${NODEXA_NODE_ID:-}"
FQDN="${NODEXA_AGENT_FQDN:-}"
DAEMON_PORT="${NODEXA_AGENT_PORT:-8080}"
SFTP_PORT="${NODEXA_SFTP_PORT:-2022}"
SSL_EMAIL="${NODEXA_SSL_EMAIL:-}"

[[ -n "$PANEL_URL" ]] || fail "NODEXA_PANEL_URL is required. Generate the command in Admin -> Nodes -> Configuration."
[[ -n "$TOKEN" ]] || fail "NODEXA_AGENT_TOKEN is required. Generate a new Node token in Nodexa Panel."
[[ "$NODE_ID" =~ ^[0-9]+$ ]] || fail "NODEXA_NODE_ID is required. Generate a fresh Auto-Deploy command from Nodexa Panel."

export DEBIAN_FRONTEND=noninteractive

log "Installing Nodexa Agent (Wings-compatible engine)..."
apt-get update -y
apt-get install -y ca-certificates curl docker.io openssl
systemctl enable --now docker

ARCH="$(dpkg --print-architecture)"
case "$ARCH" in
  amd64) WINGS_ARCH="amd64" ;;
  arm64) WINGS_ARCH="arm64" ;;
  *) fail "Unsupported architecture: $ARCH" ;;
esac

# Stop the old custom Nodexa Go agent so it cannot occupy the daemon port.
systemctl disable --now nodexa-agent.service 2>/dev/null || true
rm -f /etc/systemd/system/nodexa-agent.service
systemctl daemon-reload

log "Downloading current Wings engine..."
curl -fL --retry 5 --retry-delay 2 --retry-all-errors \
  "https://github.com/pterodactyl/wings/releases/latest/download/wings_linux_${WINGS_ARCH}" \
  -o /usr/local/bin/wings
chmod 0755 /usr/local/bin/wings

mkdir -p /etc/pterodactyl /etc/nodexa /var/lib/pterodactyl/volumes

# Configure using the panel-issued Pterodactyl/Wings deployment token. This keeps
# the complete Pterodactyl API protocol while presenting the service as Nodexa.
log "Downloading Node configuration from ${PANEL_URL}..."
/usr/local/bin/wings configure \
  --panel-url "$PANEL_URL" \
  --token "$TOKEN" \
  --node "$NODE_ID"

[[ -s /etc/pterodactyl/config.yml ]] || fail "Wings did not create /etc/pterodactyl/config.yml."

# Nodexa-branded path pointing at the protocol-compatible configuration.
ln -sfn /etc/pterodactyl/config.yml /etc/nodexa/config.yml

# If the panel expects TLS directly on Wings and the certificate does not exist,
# attempt a Let's Encrypt certificate for the Node FQDN.
if grep -Eq '^[[:space:]]*enabled:[[:space:]]*true[[:space:]]*$' /etc/pterodactyl/config.yml; then
  CERT_PATH="$(awk '/^[[:space:]]*cert:[[:space:]]*/{print $2; exit}' /etc/pterodactyl/config.yml | tr -d "'\"")"
  KEY_PATH="$(awk '/^[[:space:]]*key:[[:space:]]*/{print $2; exit}' /etc/pterodactyl/config.yml | tr -d "'\"")"
  if [[ ( -n "$CERT_PATH" && ! -s "$CERT_PATH" ) || ( -n "$KEY_PATH" && ! -s "$KEY_PATH" ) ]]; then
    if [[ -z "$FQDN" ]]; then
      FQDN="$(awk '/^[[:space:]]*remote:[[:space:]]*/{next} END{}' /etc/pterodactyl/config.yml 2>/dev/null || true)"
    fi
    if [[ -n "$FQDN" ]]; then
      log "TLS is enabled but the certificate is missing; requesting Let's Encrypt for ${FQDN}..."
      apt-get install -y certbot
      if [[ -n "$SSL_EMAIL" ]]; then
        certbot certonly --standalone --non-interactive --agree-tos --no-eff-email --email "$SSL_EMAIL" -d "$FQDN" || true
      else
        certbot certonly --standalone --non-interactive --agree-tos --register-unsafely-without-email -d "$FQDN" || true
      fi
    fi
  fi
fi

cat >/etc/systemd/system/nodexa-agent.service <<'UNIT'
[Unit]
Description=Nodexa Agent (Wings Engine)
After=docker.service
Requires=docker.service
PartOf=docker.service

[Service]
User=root
WorkingDirectory=/etc/nodexa
LimitNOFILE=4096
PIDFile=/var/run/wings/daemon.pid
ExecStart=/usr/local/bin/wings --config /etc/nodexa/config.yml
Restart=on-failure
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
UNIT

# Compatibility alias for standard Pterodactyl/Wings troubleshooting commands.
ln -sfn /etc/systemd/system/nodexa-agent.service /etc/systemd/system/wings.service
systemctl daemon-reload
systemctl enable --now nodexa-agent.service

if command -v ufw >/dev/null 2>&1; then
  ufw allow "${DAEMON_PORT}/tcp" >/dev/null 2>&1 || true
  ufw allow "${SFTP_PORT}/tcp" >/dev/null 2>&1 || true
fi

sleep 2
if ! systemctl is-active --quiet nodexa-agent.service; then
  journalctl -u nodexa-agent.service -n 80 --no-pager || true
  fail "Nodexa Agent did not start. Check the log above."
fi

log "Nodexa Agent is running with the Wings-compatible engine."
echo ""
echo "  Service: systemctl status nodexa-agent"
echo "  Alias:   systemctl status wings"
echo "  Config:  /etc/nodexa/config.yml -> /etc/pterodactyl/config.yml"
echo "  API:     port ${DAEMON_PORT}"
echo "  SFTP:    port ${SFTP_PORT}"
echo ""
