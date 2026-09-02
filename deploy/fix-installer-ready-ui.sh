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

vanilla = '''<div><Icon name="terminal" size={16}/> Live Console</div><span className="live-pill"><span/> LIVE</span>'''
legacy = '''<div><Icon name="terminal" size={16}/> {logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "Installation Console" : "Live Console"}</div><span className="live-pill"><span/> {logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "INSTALLER" : "LIVE"}</span>'''
completion_only = '''<div><Icon name="terminal" size={16}/> {logs.includes("Nodexa installation completed successfully.") ? "Installation færdig" : logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "Installation Console" : "Live Console"}</div><span className="live-pill"><span/> {logs.includes("Nodexa installation completed successfully.") ? "KLAR" : logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "INSTALLER" : "LIVE"}</span>'''

replacement = '''<div><Icon name="terminal" size={16}/> {logs.includes("REINSTALL FAILED") || logs.includes("INSTALLATION FAILED") || logs.includes("Minecraft installer exited with code") ? "Installation fejlede" : logs.includes("Nodexa installation completed successfully.") ? "Installation færdig" : logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "Installation Console" : "Live Console"}</div><span className="live-pill"><span/> {logs.includes("REINSTALL FAILED") || logs.includes("INSTALLATION FAILED") || logs.includes("Minecraft installer exited with code") ? "FEJLET" : logs.includes("Nodexa installation completed successfully.") ? "KLAR" : logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "INSTALLER" : "LIVE"}</span>'''

if replacement not in text:
    for candidate in (completion_only, legacy, vanilla):
        if candidate in text:
            text = text.replace(candidate, replacement, 1)
            break

command = '''{canCommand && <div className="command-bar"><span>›</span><input value={cmd} onChange={e => setCmd(e.target.value)} onKeyDown={e => e.key === 'Enter' && send()} placeholder="Skriv en kommando…"/><button onClick={send}>Send</button></div>}'''
command_guarded = '''{canCommand && !(logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") || logs.includes("Nodexa installation completed successfully.")) && <div className="command-bar"><span>›</span><input value={cmd} onChange={e => setCmd(e.target.value)} onKeyDown={e => e.key === 'Enter' && send()} placeholder="Skriv en kommando…"/><button onClick={send}>Send</button></div>}'''
if command_guarded not in text and command in text:
    text = text.replace(command, command_guarded, 1)

pre = '''<pre>{logs || 'Ingen console-output endnu. Start serveren for at se live output.'}</pre>'''
notice = '''<pre>{logs || 'Ingen console-output endnu. Start serveren for at se live output.'}</pre>{(logs.includes("REINSTALL FAILED") || logs.includes("INSTALLATION FAILED") || logs.includes("Minecraft installer exited with code")) && <div className="auth-error">Installationen er stoppet efter en fejl. Ret årsagen og kør Geninstaller server igen fra Indstillinger.</div>}'''
if notice not in text and pre in text:
    text = text.replace(pre, notice, 1)

p.write_text(text)
PY

echo "[Nodexa] Installer success/failure states enabled in console UI."
