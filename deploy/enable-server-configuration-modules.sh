#!/usr/bin/env bash
set -Eeuo pipefail
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
APP="$PANEL_DIR/resources/js/App.tsx"
[[ -f "$APP" ]] || exit 0
python3 - "$APP" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); text=p.read_text()
imp="import { NetworkPage, StartupPage, SettingsPage as ServerSettingsPage } from './ServerConfigurationModules';\n"
if imp not in text:text=text.replace("import axios from 'axios';\n","import axios from 'axios';\n"+imp,1)
text=text.replace("type Tab = 'console' | 'files' | 'databases' | 'schedules' | 'backups' | 'users' | 'settings';","type Tab = 'console' | 'files' | 'databases' | 'schedules' | 'backups' | 'network' | 'startup' | 'users' | 'settings';")
text=text.replace("backups: 'Backups', users: 'Brugere', settings: 'Indstillinger',","backups: 'Backups', network: 'Netværk', startup: 'Startup', users: 'Brugere', settings: 'Indstillinger',")
text=text.replace("backups: 'backup', users: 'users', settings: 'settings',","backups: 'backup', network: 'network', startup: 'activity', users: 'users', settings: 'settings',")
text=text.replace("if (p.includes('*')) return ['console', 'files', 'databases', 'schedules', 'backups', 'users', 'settings'];","if (p.includes('*')) return ['console', 'files', 'databases', 'schedules', 'backups', 'network', 'startup', 'users', 'settings'];")
text=text.replace("if (p.some(x => x.startsWith('backups.'))) out.push('backups');\n  if (p.includes('settings.read')) out.push('settings');","if (p.some(x => x.startsWith('backups.'))) out.push('backups');\n  if (p.some(x => x.startsWith('allocation.'))) out.push('network');\n  if (me.is_admin) out.push('startup');\n  if (p.includes('settings.read')) out.push('settings');")
needle="    {tab === 'settings' && <SettingsPage server={server} />}"
replacement="    {tab === 'network' && <NetworkPage server={server} canUpdate={has(server, 'allocation.update')} />}\n    {tab === 'startup' && <StartupPage server={server} isAdmin={me.is_admin} />}\n    {tab === 'settings' && <ServerSettingsPage server={server} canReinstall={has(server, 'settings.reinstall')} />}"
if needle in text:
    text=text.replace(needle,replacement)
else:
    # Runtime transforms may have already enriched the original SettingsPage with
    # me/onOpenConsole props. Replace the complete JSX element rather than only
    # its prefix so those legacy props are not accidentally passed to the new
    # ServerSettingsPage component.
    import re
    pattern=r"    \{tab === 'settings' && <SettingsPage server=\{server\}[^\n]*?/?>\}"
    match=re.search(pattern,text)
    if match:
        text=text[:match.start()]+replacement+text[match.end():]
p.write_text(text)
PY
echo "[Nodexa] Network, Startup and Settings modules enabled."
