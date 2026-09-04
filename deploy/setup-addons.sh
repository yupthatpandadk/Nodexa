#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo '[Nodexa] setup-addons.sh must run as root.' >&2; exit 1; }

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
ADMIN_ROUTES="$PANEL_DIR/routes/admin.php"
ADMIN_LAYOUT="$PANEL_DIR/resources/views/layouts/admin.blade.php"
ADDON_ROOT="$PANEL_DIR/addons"
PUBLIC_ROOT="$PANEL_DIR/public/nodexa-addons"

[[ -f "$PANEL_DIR/artisan" ]] || { echo '[Nodexa] Panel not found; addon setup skipped.'; exit 0; }

# Register the dedicated addon route file without making the large upstream
# admin route file carry Nodexa-specific route definitions permanently.
if [[ -f "$ADMIN_ROUTES" ]] && ! grep -q "admin-addons.php" "$ADMIN_ROUTES"; then
    python3 - "$ADMIN_ROUTES" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
text = p.read_text()
needle = "Route::get('/', [Admin\\BaseController::class, 'index'])->name('admin.index');"
insert = needle + "\n\nrequire __DIR__ . '/admin-addons.php';"
if needle in text and 'admin-addons.php' not in text:
    p.write_text(text.replace(needle, insert, 1))
PY
fi

# Add the manager to the existing AdminLTE sidebar and load assets from enabled
# addons. These blocks are idempotent and are restored after every core update.
if [[ -f "$ADMIN_LAYOUT" ]]; then
    python3 - "$ADMIN_LAYOUT" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
text = p.read_text()

if 'admin.addons' not in text:
    needle = '                        <li class="header">MANAGEMENT</li>'
    block = '''                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.addons') ?: 'active' }}">
                            <a href="{{ route('admin.addons') }}">
                                <i class="fa fa-puzzle-piece"></i> <span>Addons</span>
                            </a>
                        </li>
'''
    if needle in text:
        text = text.replace(needle, block + needle, 1)

if 'nodexaAdminAddonStylesheets' not in text:
    needle = '            <!--[if lt IE 9]>'
    block = '''            @foreach(($nodexaAdminAddonStylesheets ?? []) as $stylesheet)
                <link rel="stylesheet" href="{{ $stylesheet }}?v={{ urlencode($appVersion ?? 'nodexa') }}">
            @endforeach

'''
    if needle in text:
        text = text.replace(needle, block + needle, 1)

if 'nodexaAdminAddonScripts' not in text:
    needle = '            @if(Auth::user()->root_admin)'
    block = '''            @foreach(($nodexaAdminAddonScripts ?? []) as $script)
                <script src="{{ $script }}?v={{ urlencode($appVersion ?? 'nodexa') }}"></script>
            @endforeach

'''
    if needle in text:
        text = text.replace(needle, block + needle, 1)

p.write_text(text)
PY
fi

# Publish static files for every catalog addon. Disabled addons are harmless:
# their files may exist publicly, but the layout never references them unless
# the database state marks the addon enabled.
mkdir -p "$PUBLIC_ROOT"
if [[ -d "$ADDON_ROOT" ]]; then
    while IFS= read -r -d '' manifest; do
        addon_dir="$(dirname "$manifest")"
        slug="$(basename "$addon_dir")"
        if [[ "$slug" =~ ^[a-z0-9][a-z0-9_-]{1,79}$ && -d "$addon_dir/public" ]]; then
            mkdir -p "$PUBLIC_ROOT/$slug"
            rsync -a --delete "$addon_dir/public/" "$PUBLIC_ROOT/$slug/"
        fi
    done < <(find "$ADDON_ROOT" -mindepth 2 -maxdepth 2 -name addon.json -print0 2>/dev/null)
fi

chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" 2>/dev/null || true
find "$PUBLIC_ROOT" -type d -exec chmod 755 {} + 2>/dev/null || true
find "$PUBLIC_ROOT" -type f -exec chmod 644 {} + 2>/dev/null || true

cd "$PANEL_DIR"
sudo -u www-data /usr/bin/php artisan migrate --force >/dev/null
sudo -u www-data /usr/bin/php artisan optimize:clear >/dev/null
sudo -u www-data /usr/bin/php artisan route:cache >/dev/null 2>&1 || true
sudo -u www-data /usr/bin/php artisan view:clear >/dev/null 2>&1 || true

echo '[Nodexa] Addon framework installed.'
