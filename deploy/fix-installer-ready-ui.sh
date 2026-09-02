#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
APP="$PANEL_DIR/resources/js/App.tsx"
[[ -f "$APP" ]] || exit 0

python3 - "$APP" <<'PY'
from pathlib import Path
import sys

p = Path(sys.argv[1])
text = p.read_text()
old = '''<div><Icon name="terminal" size={16}/> {logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "Installation Console" : "Live Console"}</div><span className="live-pill"><span/> {logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "INSTALLER" : "LIVE"}</span>'''
new = '''<div><Icon name="terminal" size={16}/> {logs.includes("Nodexa installation completed successfully.") ? "Installation færdig" : logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "Installation Console" : "Live Console"}</div><span className="live-pill"><span/> {logs.includes("Nodexa installation completed successfully.") ? "KLAR" : logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "INSTALLER" : "LIVE"}</span>'''
if old in text:
    text = text.replace(old, new, 1)
p.write_text(text)
PY

echo "[Nodexa] Installer completion state enabled in console UI."
