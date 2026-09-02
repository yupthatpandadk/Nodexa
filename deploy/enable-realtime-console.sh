#!/usr/bin/env bash
set -Eeuo pipefail
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
APP="$PANEL_DIR/resources/js/App.tsx"
[[ -f "$APP" ]] || exit 0
python3 - "$APP" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); text=p.read_text()
old="""  useEffect(() => {
    if (!selected || tab !== 'console' || !me || !has(selected, 'console.read')) return;
    const tick = () => {
      axios.get(`/api/servers/${selected.id}/stats`).then(r => setStats(r.data)).catch(() => {});
      axios.get(`/api/servers/${selected.id}/logs?tail=150`).then(r => setLogs(typeof r.data === 'string' ? r.data : JSON.stringify(r.data ?? '', null, 2))).catch(() => {});
    };
    tick();
    const timer = setInterval(tick, 3000);
    return () => clearInterval(timer);
  }, [selected, tab, me]);"""
new="""  useEffect(() => {
    if (!selected || tab !== 'console' || !me || !has(selected, 'console.read')) return;
    let cancelled = false;
    const controller = new AbortController();
    const statsTick = () => axios.get(`/api/servers/${selected.id}/stats`).then(r => !cancelled && setStats(r.data)).catch(() => {});
    statsTick();
    const timer = setInterval(statsTick, 3000);
    setLogs('');
    const token = localStorage.getItem(TOKEN_KEY);
    const headers = new Headers();
    if (token) headers.set('Authorization', `Bearer ${token}`);
    fetch(`/api/servers/${selected.id}/logs/stream?tail=150`, { headers, signal: controller.signal })
      .then(async response => {
        if (!response.ok || !response.body) throw new Error(`Console stream HTTP ${response.status}`);
        const reader = response.body.getReader(); const decoder = new TextDecoder();
        while (!cancelled) {
          const { value, done } = await reader.read(); if (done) break;
          const chunk = decoder.decode(value, { stream: true });
          if (chunk) setLogs(previous => (previous + chunk).slice(-300000));
        }
      })
      .catch(() => { if (!cancelled) axios.get(`/api/servers/${selected.id}/logs?tail=150`).then(r => setLogs(typeof r.data === 'string' ? r.data : JSON.stringify(r.data ?? '', null, 2))).catch(() => {}); });
    return () => { cancelled = true; controller.abort(); clearInterval(timer); };
  }, [selected, tab, me]);"""
if old not in text:
    print('[Nodexa] Realtime console transform already applied or source changed.')
else:
    text=text.replace(old,new,1);p.write_text(text);print('[Nodexa] Realtime console frontend enabled.')
PY
