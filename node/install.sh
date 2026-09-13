#!/usr/bin/env bash
set -Eeuo pipefail

# Nodexa Node Installer
# Prepares a fresh Debian/Ubuntu host for the Nodexa node runtime.
# The runtime remains protocol-compatible with the upstream node engine.

NODE_VERSION="${NODE_VERSION:-latest}"
NODE_DIR="/etc/nodexa"
DATA_DIR="/var/lib/nodexa"
NODE_BIN="/usr/local/bin/nodexa-wings"
SERVICE="nodexa-wings"

info(){ printf '\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }
ok(){ printf '\033[1;32m[OK]\033[0m %s\n' "$*"; }
fail(){ printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

[[ ${EUID} -eq 0 ]] || fail "Kør installeren som root (sudo -i)."
[[ -r /etc/os-release ]] || fail "Kan ikke identificere operativsystemet."
. /etc/os-release
case "${ID:-}" in ubuntu|debian) ;; *) fail "Denne version understøtter Ubuntu og Debian. Fundet: ${PRETTY_NAME:-ukendt}." ;; esac

ARCH="$(uname -m)"
case "$ARCH" in x86_64|amd64) NODE_ARCH="amd64" ;; aarch64|arm64) NODE_ARCH="arm64" ;; *) fail "Ikke-understøttet arkitektur: $ARCH" ;; esac

info "Klargør Nodexa Node på ${PRETTY_NAME:-$ID} ($NODE_ARCH)..."
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl gnupg lsb-release tar unzip ufw

if ! command -v docker >/dev/null 2>&1; then
  info "Installerer Docker Engine..."
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL "https://download.docker.com/linux/${ID}/gpg" | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  chmod a+r /etc/apt/keyrings/docker.gpg
  CODENAME="${VERSION_CODENAME:-$(lsb_release -cs)}"
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/${ID} ${CODENAME} stable" > /etc/apt/sources.list.d/docker.list
  apt-get update
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
else
  info "Docker er allerede installeret."
fi
systemctl enable --now docker

install -d -m 0755 "$NODE_DIR" "$DATA_DIR"

# Download the compatible upstream runtime, but expose it locally only as Nodexa Node.
if [[ "$NODE_VERSION" == "latest" ]]; then
  RUNTIME_URL="https://github.com/pterodactyl/wings/releases/latest/download/wings_linux_${NODE_ARCH}"
else
  RUNTIME_URL="https://github.com/pterodactyl/wings/releases/download/${NODE_VERSION}/wings_linux_${NODE_ARCH}"
fi
info "Installerer Nodexa Node runtime..."
curl -fL "$RUNTIME_URL" -o "$NODE_BIN"
chmod 0755 "$NODE_BIN"

cat > /etc/systemd/system/nodexa-wings.service <<'UNIT'
[Unit]
Description=Nodexa Node Runtime
After=docker.service
Requires=docker.service
PartOf=docker.service

[Service]
User=root
WorkingDirectory=/etc/nodexa
LimitNOFILE=4096
ExecStart=/usr/local/bin/nodexa-wings --config /etc/nodexa/config.yml
Restart=on-failure
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable "$SERVICE"

if command -v ufw >/dev/null 2>&1; then
  info "Forbereder Nodexa firewall-regler..."
  ufw allow 22/tcp >/dev/null || true
  ufw allow 8080/tcp >/dev/null || true
  ufw allow 2022/tcp >/dev/null || true
fi

cat > /usr/local/bin/nodexa-node <<'CLI'
#!/usr/bin/env bash
set -e
SERVICE="nodexa-wings"
CONFIG="/etc/nodexa/config.yml"
case "${1:-status}" in
  status) systemctl status "$SERVICE" --no-pager || true ;;
  start) systemctl start "$SERVICE" ;;
  stop) systemctl stop "$SERVICE" ;;
  restart) systemctl restart "$SERVICE" ;;
  logs) journalctl -u "$SERVICE" -n 150 --no-pager ;;
  follow) journalctl -u "$SERVICE" -f ;;
  config) ${EDITOR:-nano} "$CONFIG" ;;
  *) echo "Brug: nodexa-node {status|start|stop|restart|logs|follow|config}"; exit 1 ;;
esac
CLI
chmod 0755 /usr/local/bin/nodexa-node

# Compatibility bridge: existing panel Configuration commands commonly write
# /etc/pterodactyl/config.yml. Keep that command working while the actual
# Nodexa runtime reads /etc/nodexa/config.yml.
install -d -m 0755 /etc/pterodactyl
ln -sfn /etc/nodexa/config.yml /etc/pterodactyl/config.yml

ok "Nodexa Node er installeret og klar til konfiguration."
printf '\nNæste trin:\n'
printf '  1. Opret noden i Nodexa Admin -> Nodes.\n'
printf '  2. Åbn Configuration.\n'
printf '  3. Kør Configuration-commanden på denne node.\n'
printf '  4. Kør: nodexa-node start\n\n'
printf 'Nodexa config: /etc/nodexa/config.yml\n'
printf 'Nodexa data:   /var/lib/nodexa\n'
printf 'Service:       nodexa-wings.service\n'
printf 'Kommandoer:    nodexa-node status | logs | follow | restart\n'
