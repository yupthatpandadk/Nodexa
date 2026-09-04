#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
[[ -d "$PANEL_DIR" ]] || exit 0

python3 - "$PANEL_DIR" <<'PY'
from pathlib import Path
import re
import sys

root = Path(sys.argv[1])


def patch(path: Path, replacements=(), transform=None):
    if not path.is_file():
        return

    text = path.read_text()
    original = text
    for old, new in replacements:
        text = text.replace(old, new)
    if transform is not None:
        text = transform(text)

    if text != original:
        path.write_text(text)


# Laravel application name: this controls titles, notifications, mail subjects,
# and any server-rendered UI which asks Laravel for the application name.
patch(
    root / 'config/app.php',
    replacements=(
        ("'name' => env('APP_NAME', 'Pterodactyl')", "'name' => env('APP_NAME', 'Nodexa')"),
        ('This value is set when creating a Pterodactyl release.', 'This value is set when creating a Nodexa release.'),
    ),
)


def patch_wrapper(text: str) -> str:
    text = text.replace("config('app.name', 'Pterodactyl')", "config('app.name', 'Nodexa')")
    if '/js/nodexa-branding.js' not in text:
        text = text.replace(
            "        @include('layouts.scripts')\n",
            "        @include('layouts.scripts')\n        <script src=\"/js/nodexa-branding.js\" defer></script>\n",
            1,
        )
    return text


patch(root / 'resources/views/templates/wrapper.blade.php', transform=patch_wrapper)


def patch_admin(text: str) -> str:
    text = text.replace("config('app.name', 'Pterodactyl')", "config('app.name', 'Nodexa')")
    text = re.sub(
        r'Copyright\s*&copy;\s*2015\s*-\s*\{\{\s*date\(\'Y\'\)\s*\}\}\s*<a[^>]+href=[\"\']https://pterodactyl\.io/?[\"\'][^>]*>Pterodactyl Software</a>\.?',
        "Nodexa &copy; {{ date('Y') }}",
        text,
        flags=re.IGNORECASE,
    )
    if '/js/nodexa-branding.js' not in text:
        text = text.replace(
            "        @include('layouts.scripts')\n",
            "        @include('layouts.scripts')\n        <script src=\"/js/nodexa-branding.js\" defer></script>\n",
            1,
        )
    return text


patch(root / 'resources/views/layouts/admin.blade.php', transform=patch_admin)

# Translation files are presentation-only string tables, so replacing the old
# product name here is safe and also covers validation/help text rendered by
# both the admin area and the client panel.
lang_root = root / 'resources/lang'
if lang_root.is_dir():
    for path in lang_root.rglob('*.php'):
        text = path.read_text()
        branded = re.sub(r'Pterodactyl(?:®|&reg;)?', 'Nodexa', text, flags=re.IGNORECASE)
        if branded != text:
            path.write_text(branded)
PY

# Existing installations may still carry APP_NAME=Pterodactyl in their local
# .env file. The updater intentionally preserves .env, therefore branding has
# to be corrected explicitly rather than relying only on source defaults.
ENV_FILE="$PANEL_DIR/.env"
if [[ -f "$ENV_FILE" ]]; then
    if grep -q '^APP_NAME=' "$ENV_FILE"; then
        sed -i 's/^APP_NAME=.*/APP_NAME=Nodexa/' "$ENV_FILE"
    else
        printf '\nAPP_NAME=Nodexa\n' >> "$ENV_FILE"
    fi
fi

echo "[Nodexa] Nodexa-only user-facing branding enforced."
