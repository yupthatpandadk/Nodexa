#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
WRAPPER="$PANEL_DIR/resources/views/templates/wrapper.blade.php"
ADMIN="$PANEL_DIR/resources/views/layouts/admin.blade.php"
SCRIPTS="$PANEL_DIR/resources/views/layouts/scripts.blade.php"

[[ -f "$SCRIPTS" ]] || { echo "[Nodexa] Missing shared layouts/scripts.blade.php" >&2; exit 1; }
grep -q "partials.nodexa-theme" "$SCRIPTS" || {
    echo "[Nodexa] Shared layout is missing the global Nodexa theme bootstrap." >&2
    exit 1
}

python3 - "$WRAPPER" "$ADMIN" <<'PY'
from pathlib import Path
import re
import sys

wrapper = Path(sys.argv[1])
admin = Path(sys.argv[2])

# layouts/scripts.blade.php is now the one and only theme entry point. Remove
# older per-layout includes so the theme bootstrap is never executed twice.
for path in (wrapper, admin):
    if not path.is_file():
        continue
    text = path.read_text()
    text = re.sub(r"^[ \t]*@include\(['\"]partials\.nodexa-theme['\"]\)[ \t]*\n?", "", text, flags=re.M)
    path.write_text(text)

if admin.is_file():
    text = admin.read_text()

    # Remove the legacy isolated admin theme JavaScript. It only knew about an
    # accent and did not calculate the shared background/surface palette.
    text = re.sub(
        r'\n\s*<script>\s*\(function \(\) \{\s*var STORAGE_KEY = [\'\"]nodexa_theme_accent[\'\"];.*?</script>\s*',
        '\n',
        text,
        flags=re.S,
    )

    # Remove every historical admin-only color layer. These blocks contained
    # fixed green backgrounds and were the reason /admin stayed green while the
    # customer area changed to blue/purple/etc.
    text = re.sub(
        r'\n\s*<style id=[\'\"]nodexa-admin-theme[\'\"]>.*?</style>\s*',
        '\n',
        text,
        flags=re.S,
    )
    text = re.sub(
        r'\n\s*<style id=[\'\"]nodexa-global-admin-accent-overrides[\'\"]>.*?</style>\s*',
        '\n',
        text,
        flags=re.S,
    )

    admin.write_text(text)
PY

echo "[Nodexa] Global theme is authoritative across customer, server, auth and admin UI."
