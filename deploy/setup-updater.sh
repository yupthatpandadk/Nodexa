#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] setup-updater.sh must run as root." >&2; exit 1; }

SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO="${NODEXA_UPDATE_REPOSITORY:-yupthatpandadk/Nodexa}"
BRANCH="${NODEXA_UPDATE_BRANCH:-main}"
STATE_DIR="/var/lib/nodexa"

apt-get install -y sudo curl >/dev/null
mkdir -p "$STATE_DIR"

install -m 0755 "$SOURCE_DIR/deploy/nodexa-update-runner.sh" /usr/local/sbin/nodexa-update-runner

cat > /etc/systemd/system/nodexa-update.service <<'UNIT'
[Unit]
Description=Nodexa Platform Updater
After=network-online.target mariadb.service redis-server.service
Wants=network-online.target

[Service]
Type=simple
User=root
Group=root
ExecStart=/usr/local/sbin/nodexa-update-runner
Nice=5
IOSchedulingClass=best-effort
IOSchedulingPriority=6

[Install]
WantedBy=multi-user.target
UNIT

SYSTEMCTL="$(command -v systemctl)"
cat > /etc/sudoers.d/nodexa-updater <<EOF
# Nodexa Panel may only start the dedicated updater service.
www-data ALL=(root) NOPASSWD: ${SYSTEMCTL} --no-block start nodexa-update.service
EOF
chmod 0440 /etc/sudoers.d/nodexa-updater
visudo -cf /etc/sudoers.d/nodexa-updater >/dev/null

systemctl daemon-reload

LATEST_SHA="$(curl -fsSL -H 'Accept: application/vnd.github+json' -H 'User-Agent: Nodexa-Updater' "https://api.github.com/repos/${REPO}/commits/${BRANCH}" 2>/dev/null | sed -n 's/^[[:space:]]*"sha": "\([0-9a-f]\{40\}\)",/\1/p' | head -n1 || true)"

if [[ -n "$LATEST_SHA" ]]; then
  printf '{"version":"0.5.0","commit":"%s","repository":"%s","branch":"%s","installed_at":"%s"}\n' \
    "$LATEST_SHA" "$REPO" "$BRANCH" "$(date --iso-8601=seconds)" > "$STATE_DIR/version.json"
  chmod 0644 "$STATE_DIR/version.json"
fi

if [[ ! -f "$STATE_DIR/update-state.json" ]]; then
  printf '{"status":"idle","message":"Ingen opdatering kører.","updated_at":"%s"}\n' "$(date --iso-8601=seconds)" > "$STATE_DIR/update-state.json"
  chmod 0644 "$STATE_DIR/update-state.json"
fi

touch /var/log/nodexa-update.log
chmod 0644 /var/log/nodexa-update.log

echo "[Nodexa] Panel updater installed."
