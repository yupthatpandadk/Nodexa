#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo '[Nodexa] setup-admin-design.sh must run as root.' >&2; exit 1; }
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
LAYOUT="$PANEL_DIR/resources/views/layouts/admin.blade.php"
CSS="$PANEL_DIR/public/css/nodexa-admin-modern.css"

[[ -f "$LAYOUT" ]] || { echo '[Nodexa] Admin layout not found; design setup skipped.' >&2; exit 0; }
[[ -f "$CSS" ]] || { echo '[Nodexa] Modern admin stylesheet not found; design setup skipped.' >&2; exit 0; }

python3 - "$LAYOUT" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
text = p.read_text()
marker = '<link rel="stylesheet" href="{{ asset(\'css/nodexa-admin-modern.css\') }}?v=0.14.42">'
if marker not in text:
    needle = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">'
    if needle in text:
        text = text.replace(needle, needle + '\n            ' + marker, 1)
    else:
        text = text.replace('<style id="nodexa-admin-theme">', marker + '\n\n            <style id="nodexa-admin-theme">', 1)
p.write_text(text)
PY

# Clear compiled Blade views so the refined layout is visible immediately.
if [[ -f "$PANEL_DIR/artisan" ]]; then
    cd "$PANEL_DIR"
    sudo -u www-data /usr/bin/php artisan view:clear >/dev/null 2>&1 || true
fi

echo '[Nodexa] Modern Admin design enabled.'
