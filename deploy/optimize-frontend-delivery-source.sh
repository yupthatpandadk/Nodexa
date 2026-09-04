#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
MAIN="$PANEL_DIR/resources/js/main.tsx"
BLADE="$PANEL_DIR/resources/views/app.blade.php"

if [[ -f "$MAIN" ]]; then
python3 - "$MAIN" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); text=p.read_text()
# BrowserRouter is not used by Nodexa's current state-driven panel. Removing it
# keeps react-router-dom out of the critical production bundle.
text=text.replace("import { BrowserRouter } from 'react-router-dom';\n", "")
text=text.replace("      <BrowserRouter>\n        <App />\n      </BrowserRouter>", "      <App />")
p.write_text(text)
PY
fi

if [[ -f "$BLADE" ]]; then
python3 - "$BLADE" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); text=p.read_text()
# Never include Vite's development refresh client in the production shell. A
# stale public/hot file must not make mobile browsers wait for a dev server.
text=text.replace("    @viteReactRefresh\n", "")

# The old admin navigation enhancer used a subtree MutationObserver. add() also
# updated the update-button text, which itself produced another mutation. On an
# administrator account this could create a self-triggering observer loop that
# pegged the browser main thread: the dashboard was visible, but taps/clicks no
# longer fired. Replace it with a short bounded retry while React mounts.
text=text.replace(
    "if(app){const observer=new MutationObserver(()=>add());observer.observe(app,{childList:true,subtree:true})}",
    "if(app){let tries=0;const timer=setInterval(()=>{tries++;if(add()||tries>=20)clearInterval(timer)},150)}"
)
# Avoid needless DOM writes when the updater badge is refreshed.
text=text.replace(
    "u.textContent=updateAvailable?'Opdatering tilgængelig •':'Opdateringer';",
    "const updateLabel=updateAvailable?'Opdatering tilgængelig •':'Opdateringer';if(u.textContent!==updateLabel)u.textContent=updateLabel;"
)

# Admin links are injected after React boots. Give them the same navigation
# class as native sidebar items so mobile browsers do not render them as large
# white default HTML buttons.
for marker in [
    "c.textContent='Opret server';",
    "n.textContent='Node Setup';",
    "b.textContent='Database Hosts';",
    "s.textContent='Storefronts';",
    "e.textContent='Fejl';",
]:
    var = marker.split('.', 1)[0]
    text=text.replace(marker, f"{var}.className='nav-item';{marker}")
text=text.replace(
    "if(!u){u=document.createElement('button');u.id='nodexa-update-link';u.onclick=()=>location.href='/admin/update';nav.appendChild(u)}",
    "if(!u){u=document.createElement('button');u.id='nodexa-update-link';u.className='nav-item';u.onclick=()=>location.href='/admin/update';nav.appendChild(u)}"
)

start="\n fetch('/api/me',{headers}).then(r=>r.ok?r.json():null).then(me=>{"
end="\n }).catch(()=>{});\n})();"
pos=text.find(start)
endpos=text.rfind(end)
if pos != -1 and endpos != -1 and endpos > pos:
    block=text[pos+1:endpos+len("\n }).catch(()=>{});")].strip()
    replacement=(
        "\n const startAdminEnhancements=()=>{\n  " + block.replace("\n", "\n  ") + "\n };\n"
        " const scheduleAdminEnhancements=()=>{\n"
        "  const run=()=>startAdminEnhancements();\n"
        "  if('requestIdleCallback' in window){ window.requestIdleCallback(run,{timeout:2500}); }\n"
        "  else { setTimeout(run,1200); }\n"
        " };\n"
        " if(window.__NODEXA_BOOTED__) scheduleAdminEnhancements();\n"
        " else window.addEventListener('nodexa:booted',scheduleAdminEnhancements,{once:true});\n"
        "})();"
    )
    # Replace the old immediate admin bootstrap and the original IIFE close.
    text=text[:pos]+replacement+text[endpos+len(end):]

p.write_text(text)
PY
fi

THEME_SYNC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/sync-admin-theme.sh"
if [[ -f "$THEME_SYNC" ]]; then
    NODEXA_PANEL_DIR="$PANEL_DIR" bash "$THEME_SYNC"
fi

echo "[Nodexa] Critical frontend path trimmed; admin enrichment deferred without blocking UI interactions."
