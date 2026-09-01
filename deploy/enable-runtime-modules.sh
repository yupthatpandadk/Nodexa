#!/usr/bin/env bash
set -Eeuo pipefail
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
APP="$PANEL_DIR/resources/js/App.tsx"
[[ -f "$APP" ]] || exit 0
python3 - "$APP" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); text=p.read_text()
imp="import { FilesPage, SchedulesPage, BackupsPage } from './RuntimeModules';\n"
if imp not in text:
    text=text.replace("import axios from 'axios';\n", "import axios from 'axios';\n"+imp, 1)
text=text.replace(
'''    {tab === 'files' && <ModulePlaceholder icon="folder" title="Filer" text="Filhåndtering bliver koblet direkte på Nodexa Agent med editor, upload, download og arkivfunktioner." />}''',
'''    {tab === 'files' && <FilesPage server={server} canWrite={has(server, 'files.write')} />}''')
text=text.replace(
'''    {tab === 'schedules' && <ModulePlaceholder icon="calendar" title="Planlægninger" text="Automatiser genstart, kommandoer og backups med en enkel visuel tidsplan." />}''',
'''    {tab === 'schedules' && <SchedulesPage server={server} canCreate={has(server, 'schedule.create')} canUpdate={has(server, 'schedule.update')} canDelete={has(server, 'schedule.delete')} canExecute={has(server, 'schedule.execute')} />}''')
text=text.replace(
'''    {tab === 'backups' && <ModulePlaceholder icon="backup" title="Backups" text="Opret, lås, download og gendan backups direkte fra serverpanelet." />}''',
'''    {tab === 'backups' && <BackupsPage server={server} canCreate={has(server, 'backups.create')} canDownload={has(server, 'backups.download')} canRestore={has(server, 'backups.restore')} canDelete={has(server, 'backups.delete')} />}''')
p.write_text(text)
PY
echo "[Nodexa] Files, schedules and backups modules enabled."
