#!/usr/bin/env bash
set -Eeuo pipefail
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
APP="$PANEL_DIR/resources/js/App.tsx"
[[ -f "$APP" ]] || exit 0
python3 - "$APP" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); text=p.read_text()
old="  const power = (signal: string) => selected && axios.post(`/api/servers/${selected.id}/power`, { signal });"
new="""  const power = async (signal: string) => {
    if (!selected) return null;
    const labels: Record<string,string> = { start: 'Starter server...', stop: 'Stopper server...', restart: 'Genstarter server...', kill: 'Tvangsstopper server...' };
    setTab('console');
    setLogs(prev => `${prev ? prev.replace(/\\s+$/,'')+'\\n' : ''}container@nodexa~ ${labels[signal] || 'Udfører power-handling...'}`);
    try {
      const response = await axios.post(`/api/servers/${selected.id}/power`, { signal });
      await new Promise(resolve => setTimeout(resolve, 500));
      const [statsResult, logsResult] = await Promise.allSettled([
        axios.get(`/api/servers/${selected.id}/stats`),
        axios.get(`/api/servers/${selected.id}/logs?tail=300`),
      ]);
      if (statsResult.status === 'fulfilled') setStats(statsResult.value.data);
      if (logsResult.status === 'fulfilled') setLogs(typeof logsResult.value.data === 'string' ? logsResult.value.data : JSON.stringify(logsResult.value.data ?? '', null, 2));
      loadServers(me).catch(() => {});
      return response;
    } catch (e: any) {
      const detail = e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Ukendt fejl';
      setLogs(prev => `${prev ? prev.replace(/\\s+$/,'')+'\\n' : ''}[Nodexa Power] ERROR: ${detail}`);
      return null;
    }
  };"""
if old in text:
    text=text.replace(old,new,1)
elif '[Nodexa Power] ERROR:' not in text:
    raise SystemExit('Could not find Nodexa power handler to patch')
p.write_text(text)
PY
echo "[Nodexa] Power controls now show startup progress and exact errors in console."
