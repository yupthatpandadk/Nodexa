#!/usr/bin/env bash
# Configure a narrowly delegated systemd update service for Nodexa.
# Executed as root by install.sh during a privileged CLI update.
set -Eeuo pipefail

[[ "$(id -u)" -eq 0 ]] || { echo "[Nodexa] Setup must run as root." >&2; exit 1; }

PANEL_DIR="$(realpath -e "\${NODEXA_PANEL_DIR:-/var/www/pterodactyl}")"
UNIT_NAME="nodexa-update.service"
UNIT_FILE="/etc/systemd/system/$UNIT_NAME"
SUDOERS_FILE="/etc/sudoers.d/nodexa-update"
SYSTEMCTL="$(readlink -f "$(command -v systemctl)")"
VISUDO="$(command -v visudo)"

[[ -d "$PANEL_DIR/.git" && -f "$PANEL_DIR/bin/nodexa-update" ]] || {
    echo "[Nodexa] Invalid panel checkout." >&2; exit 1;
}
[[ ! "$PANEL_DIR" =~ [[:space:]] ]] || {
    echo "[Nodexa] Paths containing whitespace are not supported." >&2; exit 1;
}
[[ "$SYSTEMCTL" == "/usr/bin/systemctl" ]] || {
    echo "[Nodexa] systemctl must be /usr/bin/systemctl." >&2; exit 1;
}
[[ -x "$VISUDO" ]] || { echo "[Nodexa] visudo is required." >&2; exit 1; }
id www-data >/dev/null 2>&1 || { echo "[Nodexa] www-data account not found." >&2; exit 1; }

# The privileged updater script, parent directories and Git metadata must not
# be replaceable by a compromised web worker. Keep storage writable separately.
for path in "$PANEL_DIR" "$PANEL_DIR/bin" "$PANEL_DIR/bin/nodexa-update" "$PANEL_DIR/.git"; do
    [[ ! -L "$path" ]] || { echo "[Nodexa] Symlink not allowed: $path" >&2; exit 1; }
done
chown root:root "$PANEL_DIR" "$PANEL_DIR/bin" "$PANEL_DIR/bin/nodexa-update"
chmod go-w "$PANEL_DIR" "$PANEL_DIR/bin" "$PANEL_DIR/bin/nodexa-update"
chown -R root:root "$PANEL_DIR/.git"
chmod -R go-w "$PANEL_DIR/.git"

install -d -o www-data -g www-data -m 0775 "$PANEL_DIR/storage/app" "$PANEL_DIR/storage/logs"
for file in "$PANEL_DIR/storage/app/nodexa-update-progress.json" "$PANEL_DIR/storage/logs/nodexa-update.log"; do
    if [[ -f "$file" && ! -L "$file" ]]; then
        chown www-data:www-data "$file"
        chmod u+rw,g+rw "$file"
    fi
done

tmp_unit="$(mktemp /etc/systemd/system/.nodexa-update.XXXXXX)"
tmp_sudoers="$(mktemp /etc/sudoers.d/.nodexa-update.XXXXXX)"
trap 'rm -f "$tmp_unit" "$tmp_sudoers"' EXIT

cat > "$tmp_unit" <<EOF
[Unit]
Description=Nodexa privileged panel update
Documentation=https://github.com/yupthatpandadk/Nodexa

[Service]
Type=exec
User=root
Group=root
WorkingDirectory=$PANEL_DIR
ExecStart=/usr/bin/bash $PANEL_DIR/bin/nodexa-update
Environment=HOME=/tmp
Environment=COMPOSER_HOME=/tmp/composer
Environment=NODEXA_SKIP_SYSTEM_MIGRATION=1
NoNewPrivileges=true
ProtectHome=true
ProtectSystem=full
PrivateTmp=true
UMask=0022
TimeoutStartSec=0
EOF

cat > "$tmp_sudoers" <<EOF
# Permit only this single, fixed update action.
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl start --no-block $UNIT_NAME
EOF

"$VISUDO" -cf "$tmp_sudoers" >/dev/null
install -o root -g root -m 0644 "$tmp_unit" "$UNIT_FILE"
install -o root -g root -m 0440 "$tmp_sudoers" "$SUDOERS_FILE"
"$VISUDO" -cf "$SUDOERS_FILE" >/dev/null
"$SYSTEMCTL" daemon-reload
echo "[Nodexa] Restricted Update Center service configured."
