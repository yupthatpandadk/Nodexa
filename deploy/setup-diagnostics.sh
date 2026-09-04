#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo '[Nodexa] setup-diagnostics.sh must run as root.' >&2; exit 1; }
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"

install -m 0755 "$SOURCE_DIR/deploy/nodexa-diagnostics-fix.sh" /usr/local/sbin/nodexa-diagnostics-fix

cat > /etc/sudoers.d/nodexa-diagnostics <<'EOF'
# Nodexa Diagnostics may only invoke these predefined repair actions.
www-data ALL=(root) NOPASSWD: /usr/local/sbin/nodexa-diagnostics-fix permissions
www-data ALL=(root) NOPASSWD: /usr/local/sbin/nodexa-diagnostics-fix storage-link
www-data ALL=(root) NOPASSWD: /usr/local/sbin/nodexa-diagnostics-fix clear-cache
www-data ALL=(root) NOPASSWD: /usr/local/sbin/nodexa-diagnostics-fix restart-queue
www-data ALL=(root) NOPASSWD: /usr/local/sbin/nodexa-diagnostics-fix restart-scheduler
www-data ALL=(root) NOPASSWD: /usr/local/sbin/nodexa-diagnostics-fix restart-web
www-data ALL=(root) NOPASSWD: /usr/local/sbin/nodexa-diagnostics-fix local-wings
EOF
chmod 0440 /etc/sudoers.d/nodexa-diagnostics
visudo -cf /etc/sudoers.d/nodexa-diagnostics >/dev/null

# Keep the Diagnostics link in the classic admin sidebar without making the
# layout dependent on a later runtime transform.
ADMIN_LAYOUT="$PANEL_DIR/resources/views/layouts/admin.blade.php"
if [[ -f "$ADMIN_LAYOUT" ]] && ! grep -q "admin.diagnostics" "$ADMIN_LAYOUT"; then
    python3 - "$ADMIN_LAYOUT" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
text = p.read_text()
needle = '                        <li class="header">MANAGEMENT</li>'
insert = '''                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.diagnostics') ?: 'active' }}">
                            <a href="{{ route('admin.diagnostics') }}">
                                <i class="fa fa-heartbeat"></i> <span>Fejlcenter</span>
                            </a>
                        </li>
'''
if needle in text and 'admin.diagnostics' not in text:
    text = text.replace(needle, insert + needle, 1)
    p.write_text(text)
PY
fi

# Apply the Nodexa Admin visual refinement. This keeps the existing AdminLTE
# structure and functionality, but loads the modern theme-aware stylesheet.
if [[ -x "$SOURCE_DIR/deploy/setup-admin-design.sh" ]]; then
    NODEXA_PANEL_DIR="$PANEL_DIR" bash "$SOURCE_DIR/deploy/setup-admin-design.sh"
fi

# Install/repair the optional addon framework after the core admin layout is in
# place. The addon setup is idempotent and also republishes trusted static assets.
if [[ -x "$SOURCE_DIR/deploy/setup-addons.sh" ]]; then
    NODEXA_PANEL_DIR="$PANEL_DIR" bash "$SOURCE_DIR/deploy/setup-addons.sh"
fi

# The updater may have cached Blade views before this sidebar/design patch ran.
# Clear only compiled views so the new menu/design appears immediately without
# touching sessions, config or user data.
if [[ -f "$PANEL_DIR/artisan" ]]; then
    cd "$PANEL_DIR"
    sudo -u www-data /usr/bin/php artisan view:clear >/dev/null 2>&1 || true
fi

echo '[Nodexa] Diagnostics Center installed.'
