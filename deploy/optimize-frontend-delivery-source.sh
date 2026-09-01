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
        ")();"
    )
    # replacement above deliberately contains the IIFE closing token. Replace
    # the complete tail from the old admin fetch through the original close.
    text=text[:pos]+replacement+text[endpos+len(end):]

p.write_text(text)
PY
fi

echo "[Nodexa] Critical frontend path trimmed; admin enrichment deferred until idle."
