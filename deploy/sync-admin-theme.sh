#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
WRAPPER="$PANEL_DIR/resources/views/templates/wrapper.blade.php"
ADMIN="$PANEL_DIR/resources/views/layouts/admin.blade.php"

python3 - "$WRAPPER" "$ADMIN" <<'PY'
from pathlib import Path
import sys

wrapper = Path(sys.argv[1])
admin = Path(sys.argv[2])

if wrapper.is_file():
    text = wrapper.read_text()
    marker = 'nodexa-theme-cookie-sync'
    if marker not in text:
        snippet = r'''        <script id="nodexa-theme-cookie-sync">
            (function () {
                var KEY = 'nodexa_theme_accent';
                var DEFAULT_ACCENT = '#42e9a6';

                function normalize(value) {
                    value = String(value || '').trim();
                    return /^#[0-9a-fA-F]{6}$/.test(value) ? value.toLowerCase() : DEFAULT_ACCENT;
                }

                function persistCookie(value) {
                    var accent = normalize(value);
                    var cookie = KEY + '=' + encodeURIComponent(accent) + '; Path=/; Max-Age=31536000; SameSite=Lax';
                    if (window.location.protocol === 'https:') cookie += '; Secure';
                    document.cookie = cookie;
                    return accent;
                }

                try {
                    var saved = localStorage.getItem(KEY);
                    if (saved) persistCookie(saved);
                } catch (_) {}

                window.addEventListener('nodexa:theme', function (event) {
                    if (event && event.detail && event.detail.accent) {
                        persistCookie(event.detail.accent);
                    }
                });
            })();
        </script>

'''
        needle = "        @include('layouts.scripts')"
        if needle in text:
            text = text.replace(needle, snippet + needle, 1)
        else:
            text = text.replace('</head>', snippet + '    </head>', 1)
        wrapper.write_text(text)

if admin.is_file():
    text = admin.read_text()
    old = """                var saved = DEFAULT_ACCENT;\n                try { saved = localStorage.getItem(STORAGE_KEY) || DEFAULT_ACCENT; } catch (_) {}\n                window.applyNodexaAdminAccent(saved);\n"""
    new = """                function readThemeCookie() {\n                    var prefix = STORAGE_KEY + '=';\n                    var parts = String(document.cookie || '').split(';');\n                    for (var i = 0; i < parts.length; i++) {\n                        var item = parts[i].trim();\n                        if (item.indexOf(prefix) === 0) {\n                            try { return decodeURIComponent(item.slice(prefix.length)); } catch (_) { return item.slice(prefix.length); }\n                        }\n                    }\n                    return '';\n                }\n\n                var saved = '';\n                try { saved = localStorage.getItem(STORAGE_KEY) || ''; } catch (_) {}\n                if (!saved) saved = readThemeCookie();\n                saved = normalize(saved || DEFAULT_ACCENT);\n                try { localStorage.setItem(STORAGE_KEY, saved); } catch (_) {}\n                window.applyNodexaAdminAccent(saved);\n"""
    if old in text:
        text = text.replace(old, new, 1)
    elif 'function readThemeCookie()' not in text:
        anchor = "                window.applyNodexaAdminAccent(saved);\n"
        if anchor in text:
            text = text.replace(anchor, new, 1)
    admin.write_text(text)
PY

echo "[Nodexa] Theme persistence synced between client panel and admin area."
