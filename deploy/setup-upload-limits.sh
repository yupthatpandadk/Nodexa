#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || exit 0
PANEL_DIR="${NODEXA_DIR:-/var/www/nodexa}/panel"
SOURCE_SCRIPT="$PANEL_DIR/scripts/apply-upload-limit.sh"
SYSTEM_SCRIPT="/usr/local/sbin/nodexa-apply-upload-limit"
SUDOERS_FILE="/etc/sudoers.d/nodexa-upload-limit"
DEFAULT_MB=2048

# Install the privileged helper outside the web root. Root owns it so www-data
# cannot alter the command it is permitted to execute with sudo.
if [[ -f "$SOURCE_SCRIPT" ]]; then
    install -o root -g root -m 0755 "$SOURCE_SCRIPT" "$SYSTEM_SCRIPT"
else
    echo '[Nodexa] WARNING: upload-limit helper is missing.'
    exit 0
fi

# Allow the panel user to execute only this exact root-owned helper.
printf 'www-data ALL=(root) NOPASSWD: %s *\n' "$SYSTEM_SCRIPT" > "$SUDOERS_FILE"
chown root:root "$SUDOERS_FILE"
chmod 0440 "$SUDOERS_FILE"
if ! visudo -cf "$SUDOERS_FILE" >/dev/null; then
    rm -f "$SUDOERS_FILE"
    echo '[Nodexa] ERROR: invalid upload-limit sudoers rule.' >&2
    exit 1
fi

# Apply a safe default on first setup. Future changes are made from Admin.
if [[ ! -f /etc/nginx/conf.d/nodexa-upload.conf ]]; then
    "$SYSTEM_SCRIPT" "$DEFAULT_MB"
fi

echo "[Nodexa] Admin-controlled upload limits enabled (default ${DEFAULT_MB} MB)."
