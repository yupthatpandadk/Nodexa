#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] setup-storefront-sync.sh must run as root." >&2; exit 1; }

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
LIB_DIR="/usr/local/lib/nodexa"
SYNC_SCRIPT="$LIB_DIR/setup-storefront.sh"
RUNNER="/usr/local/sbin/nodexa-storefront-sync"
REQUEST_FILE="$PANEL_DIR/storage/app/storefront-sync.request"
SOURCE_SCRIPT="$(cd "$(dirname "$0")" && pwd)/setup-storefront.sh"

mkdir -p "$LIB_DIR" "$(dirname "$REQUEST_FILE")"
install -m 0755 "$SOURCE_SCRIPT" "$SYNC_SCRIPT"

cat > "$RUNNER" <<EOF
#!/usr/bin/env bash
set -Eeuo pipefail
REQUEST_FILE="$REQUEST_FILE"
[[ -e "\$REQUEST_FILE" ]] || exit 0
rm -f "\$REQUEST_FILE"
NODEXA_PANEL_DIR="$PANEL_DIR" bash "$SYNC_SCRIPT"
EOF
chmod 0755 "$RUNNER"

cat > /etc/systemd/system/nodexa-storefront-sync.service <<EOF
[Unit]
Description=Nodexa Storefront domain/Nginx/SSL sync
After=network-online.target nginx.service
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=$RUNNER
EOF

cat > /etc/systemd/system/nodexa-storefront-sync.path <<EOF
[Unit]
Description=Watch for Nodexa Storefront domain changes

[Path]
PathChanged=$REQUEST_FILE
PathExists=$REQUEST_FILE
Unit=nodexa-storefront-sync.service

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now nodexa-storefront-sync.path

echo "[Nodexa] Automatic multisite domain sync enabled."
