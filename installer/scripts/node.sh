#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
log "Installing Nodexa Agent..."
apt-get update -y
apt-get install -y ca-certificates curl tar build-essential pkg-config docker.io openssl
systemctl enable --now docker
ARCH="$(dpkg --print-architecture)"
case "$ARCH" in amd64) GOARCH=amd64;; arm64) GOARCH=arm64;; *) fail "Unsupported architecture: $ARCH";; esac
if ! command -v go >/dev/null 2>&1; then
  GO_VERSION="${NODEXA_GO_VERSION:-1.23.12}"
  curl -fL "https://go.dev/dl/go${GO_VERSION}.linux-${GOARCH}.tar.gz" -o /tmp/nodexa-go.tgz
  rm -rf /usr/local/go && tar -C /usr/local -xzf /tmp/nodexa-go.tgz
  ln -sf /usr/local/go/bin/go /usr/local/bin/go
fi
install -d /var/www/nodexa/agent /var/lib/nodexa /var/lib/nodexa/backups
cp -a "$SOURCE_ROOT/agent/." /var/www/nodexa/agent/
cd /var/www/nodexa/agent
go mod tidy
go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad
TOKEN="${NODEXA_AGENT_TOKEN:-$(openssl rand -hex 32)}"
PORT="${NODEXA_AGENT_PORT:-8080}"
cat >/etc/nodexa.env <<ENV
NODEXA_ADDR=0.0.0.0:${PORT}
NODEXA_TOKEN=${TOKEN}
NODEXA_DATA=/var/lib/nodexa
NODEXA_BACKUPS=/var/lib/nodexa/backups
ENV
chmod 600 /etc/nodexa.env
cat >/etc/systemd/system/nodexa-agent.service <<'UNIT'
[Unit]
Description=Nodexa Agent
After=network-online.target docker.service
Requires=docker.service
[Service]
Type=simple
EnvironmentFile=/etc/nodexa.env
ExecStart=/usr/local/bin/nodexad
Restart=always
RestartSec=3
LimitNOFILE=1048576
[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now nodexa-agent
log "Nodexa Agent installed. Token is stored in /etc/nodexa.env"
