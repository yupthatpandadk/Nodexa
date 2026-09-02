#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] setup-updater.sh must run as root." >&2; exit 1; }
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO="${NODEXA_UPDATE_REPOSITORY:-yupthatpandadk/Nodexa}"
BRANCH="${NODEXA_UPDATE_BRANCH:-main}"
STATE_DIR="/var/lib/nodexa"
VERSION="$(tr -d '[:space:]' < "$SOURCE_DIR/VERSION" 2>/dev/null || echo unknown)"
apt-get install -y sudo curl python3 >/dev/null
mkdir -p "$STATE_DIR"
install -m 0755 "$SOURCE_DIR/deploy/nodexa-update-runner.sh" /usr/local/sbin/nodexa-update-runner
install -m 0755 "$SOURCE_DIR/deploy/nodexa-update-trigger.sh" /usr/local/sbin/nodexa-update-trigger
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
cat > /etc/sudoers.d/nodexa-updater <<'EOF'
# Nodexa Panel may only execute the dedicated updater trigger.
# The trigger itself starts exactly nodexa-update.service as root.
www-data ALL=(root) NOPASSWD: /usr/local/sbin/nodexa-update-trigger
EOF
chmod 0440 /etc/sudoers.d/nodexa-updater
visudo -cf /etc/sudoers.d/nodexa-updater >/dev/null
systemctl daemon-reload

INSTALLED_SHA="${NODEXA_SOURCE_COMMIT:-}"
if [[ -z "$INSTALLED_SHA" ]]; then
  INSTALLED_SHA="$(curl -fsSL -H 'Accept: application/vnd.github+json' -H 'User-Agent: Nodexa-Updater' "https://api.github.com/repos/${REPO}/commits/${BRANCH}" 2>/dev/null | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d.get("sha", ""))' 2>/dev/null || true)"
fi
[[ "$INSTALLED_SHA" =~ ^[0-9a-fA-F]{40}$ ]] || INSTALLED_SHA=""
python3 - "$STATE_DIR/version.json" "$VERSION" "$INSTALLED_SHA" "$REPO" "$BRANCH" <<'PY'
import json,sys,datetime
path,version,commit,repo,branch=sys.argv[1:]
data={"version":version,"commit":commit or None,"repository":repo,"branch":branch,"installed_at":datetime.datetime.now(datetime.timezone.utc).isoformat()}
with open(path,'w') as f: json.dump(data,f,separators=(',',':'))
PY
chmod 0644 "$STATE_DIR/version.json"
printf '%s\n' "$VERSION" > "$STATE_DIR/VERSION"
chmod 0644 "$STATE_DIR/VERSION"
if [[ ! -f "$STATE_DIR/update-state.json" ]]; then
  printf '{"status":"idle","message":"Ingen opdatering kører.","updated_at":"%s"}\n' "$(date --iso-8601=seconds)" > "$STATE_DIR/update-state.json"
  chmod 0644 "$STATE_DIR/update-state.json"
fi
touch /var/log/nodexa-update.log
chmod 0644 /var/log/nodexa-update.log
echo "[Nodexa] Panel updater installed for version ${VERSION}${INSTALLED_SHA:+ @ ${INSTALLED_SHA:0:8}}."
