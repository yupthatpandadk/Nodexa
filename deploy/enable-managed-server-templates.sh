#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
CREATE_VIEW="$PANEL_DIR/resources/views/admin-server-create.blade.php"
APP="$PANEL_DIR/resources/js/App.tsx"

if [[ -f "$CREATE_VIEW" ]]; then
python3 - "$CREATE_VIEW" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); text=p.read_text()
text=text.replace('Game-filer/install scripts håndteres senere af Templates/Eggs-modulet.','Minecraft Java installeres automatisk af Nodexa. Reinstall kan senere gendanne de template-styrede filer.')
optimized="java -Xms256M -XX:MaxRAMPercentage=75.0 -XX:+UseG1GC -XX:+ParallelRefProcEnabled -XX:+UseStringDeduplication -jar server.jar nogui"
# Upgrade already transformed Nodexa sources as well as the original preset.
text=text.replace('java -Xms128M -XX:MaxRAMPercentage=95.0 -jar server.jar nogui', optimized)
text=text.replace('java -Xms128M -XX:MaxRAMPercentage=95.0 -jar server.jar', optimized)
old="const presets={custom:{image:'ghcr.io/parkervcp/yolks:debian',startup:''},minecraft:{image:'ghcr.io/parkervcp/yolks:java_21',startup:'java -Xms128M -XX:MaxRAMPercentage=95.0 -jar server.jar'},fivem:{image:'ghcr.io/parkervcp/yolks:debian',startup:'bash ./run.sh'}};"
new=f"let currentPreset='custom';\nconst presets={{custom:{{image:'ghcr.io/parkervcp/yolks:debian',startup:''}},minecraft:{{image:'ghcr.io/parkervcp/yolks:java_21',startup:'{optimized}'}},fivem:{{image:'ghcr.io/parkervcp/yolks:debian',startup:'bash ./run.sh'}}}};"
if old in text: text=text.replace(old,new,1)
old="document.querySelectorAll('.preset').forEach(b=>b.onclick=()=>{document.querySelectorAll('.preset').forEach(x=>x.classList.remove('active'));b.classList.add('active');const p=presets[b.dataset.preset];$('image').value=p.image;$('startup').value=p.startup});"
new="document.querySelectorAll('.preset').forEach(b=>b.onclick=()=>{document.querySelectorAll('.preset').forEach(x=>x.classList.remove('active'));b.classList.add('active');currentPreset=b.dataset.preset||'custom';const p=presets[currentPreset];$('image').value=p.image;$('startup').value=p.startup;if(currentPreset==='minecraft'&&!$('environment').value.includes('MINECRAFT_VERSION=')){$('environment').value=($('environment').value.trim()?$('environment').value.trim()+'\\n':'')+'MINECRAFT_VERSION=1.21.8\\nSERVER_PORT=25565'}});"
if old in text: text=text.replace(old,new,1)
old="const payload={name:$('name').value.trim(),owner_id:Number($('owner').value),node_id:Number($('node').value),docker_image:$('image').value.trim(),startup:$('startup').value.trim(),memory_mb:Number($('memory').value),disk_mb:Number($('disk').value),cpu_limit:Number($('cpu').value),environment:parseEnvironment()};"
new="const payload={name:$('name').value.trim(),owner_id:Number($('owner').value),node_id:Number($('node').value),template_slug:currentPreset==='minecraft'?'minecraft-java':currentPreset,docker_image:$('image').value.trim(),startup:$('startup').value.trim(),memory_mb:Number($('memory').value),disk_mb:Number($('disk').value),cpu_limit:Number($('cpu').value),environment:parseEnvironment()};"
if old in text: text=text.replace(old,new,1)
p.write_text(text)
PY
fi

if [[ -f "$APP" ]]; then
python3 - "$APP" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1]); text=p.read_text()
text=text.replace("type Server = { id: string; uuid: string; owner_id: number; server_number?: number; identifier?: string; name: string; status: string; memory_mb: number; disk_mb: number; cpu_limit: number; access_permissions?: string[] | string | null };", "type Server = { id: string; uuid: string; owner_id: number; server_number?: number; identifier?: string; template_slug?: string; name: string; status: string; memory_mb: number; disk_mb: number; cpu_limit: number; access_permissions?: string[] | string | null };")

# During a reinstall the console is the primary progress screen, just like a
# Pterodactyl install console. Poll logs every second while keeping heavier stats
# requests at the old three-second cadence.
old_poll="""  useEffect(() => {
    if (!selected || tab !== 'console' || !me || !has(selected, 'console.read')) return;
    const tick = () => {
      axios.get(`/api/servers/${selected.id}/stats`).then(r => setStats(r.data)).catch(() => {});
      axios.get(`/api/servers/${selected.id}/logs?tail=150`).then(r => setLogs(typeof r.data === 'string' ? r.data : JSON.stringify(r.data ?? '', null, 2))).catch(() => {});
    };
    tick();
    const timer = setInterval(tick, 3000);
    return () => clearInterval(timer);
  }, [selected, tab, me]);"""
new_poll="""  useEffect(() => {
    if (!selected || tab !== 'console' || !me || !has(selected, 'console.read')) return;
    const loadStats = () => axios.get(`/api/servers/${selected.id}/stats`).then(r => setStats(r.data)).catch(() => {});
    const loadLogs = () => axios.get(`/api/servers/${selected.id}/logs?tail=300`).then(r => setLogs(typeof r.data === 'string' ? r.data : JSON.stringify(r.data ?? '', null, 2))).catch(() => {});
    loadStats(); loadLogs();
    const statsTimer = setInterval(loadStats, 3000);
    const logsTimer = setInterval(loadLogs, 1000);
    return () => { clearInterval(statsTimer); clearInterval(logsTimer); };
  }, [selected, tab, me]);"""
if old_poll in text: text=text.replace(old_poll,new_poll,1)

text=text.replace("{tab === 'settings' && <SettingsPage server={server} />}", "{tab === 'settings' && <SettingsPage server={server} me={me} onOpenConsole={() => setTab('console')} />}")

old_header='<div><Icon name="terminal" size={16}/> Live Console</div><span className="live-pill"><span/> LIVE</span>'
new_header='<div><Icon name="terminal" size={16}/> {logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "Installation Console" : "Live Console"}</div><span className="live-pill"><span/> {logs.includes("[Nodexa Installer]") || logs.includes("container@nodexa~") ? "INSTALLER" : "LIVE"}</span>'
if old_header in text: text=text.replace(old_header,new_header,1)

old="function SettingsPage({ server }: { server: Server }) {\n  return <div className=\"module-stack\"><section className=\"module-heading\"><div><h2>Indstillinger</h2><p>Grundlæggende oplysninger for din server.</p></div></section><div className=\"settings-layout\"><div className=\"panel-card settings-card\"><div className=\"setting-block\"><label>Servernavn</label><div className=\"readonly-input\">{server.name}</div><small>Servernavn kan senere redigeres via den sikre settings-API.</small></div><div className=\"setting-block\"><label>Server ID</label><div className=\"readonly-input mono\">{server.identifier ?? server.id}</div></div><div className=\"setting-block\"><label>Ressourcer</label><div className=\"settings-resources\"><span>{fmtMb(server.memory_mb)} RAM</span><span>{fmtMb(server.disk_mb)} Disk</span><span>{server.cpu_limit || 0}% CPU</span></div></div></div><div className=\"panel-card info-card\"><div className=\"info-icon\"><Icon name=\"shield\"/></div><h3>Beskyttede indstillinger</h3><p>Startup command, Docker image og environment-variabler kan kun ændres af en administrator.</p><div className=\"info-note\">Det beskytter serveren mod utilsigtede ændringer.</div></div></div></div>;\n}"
new="function SettingsPage({ server, me, onOpenConsole }: { server: Server; me: User; onOpenConsole: () => void }) {\n  const [reinstalling, setReinstalling] = useState(false);\n  const [reinstallMessage, setReinstallMessage] = useState('');\n  const managed = Boolean(server.template_slug && server.template_slug !== 'custom');\n  const canReinstall = me.is_admin || server.owner_id === me.id;\n  const reinstall = () => {\n    if (!managed || !canReinstall || reinstalling) return;\n    if (!confirm('Geninstaller serveren? Nodexa gendanner template/Egg-filer som server.jar. Worlds, plugins og øvrige brugerfiler bevares. Serveren stoppes under geninstallationen.')) return;\n    setReinstalling(true); setReinstallMessage('');\n    const request = axios.post(`/api/servers/${server.id}/reinstall`);\n    onOpenConsole();\n    request.catch((e: any) => console.error('Nodexa reinstall failed', e));\n  };\n  return <div className=\"module-stack\"><section className=\"module-heading\"><div><h2>Indstillinger</h2><p>Grundlæggende oplysninger for din server.</p></div></section><div className=\"settings-layout\"><div className=\"panel-card settings-card\"><div className=\"setting-block\"><label>Servernavn</label><div className=\"readonly-input\">{server.name}</div></div><div className=\"setting-block\"><label>Server ID</label><div className=\"readonly-input mono\">{server.identifier ?? server.id}</div></div><div className=\"setting-block\"><label>Template</label><div className=\"readonly-input\">{server.template_slug === 'minecraft-java' ? 'Minecraft Java' : server.template_slug || 'Custom'}</div></div><div className=\"setting-block\"><label>Ressourcer</label><div className=\"settings-resources\"><span>{fmtMb(server.memory_mb)} RAM</span><span>{fmtMb(server.disk_mb)} Disk</span><span>{server.cpu_limit || 0}% CPU</span></div></div></div><div className=\"panel-card info-card\"><div className=\"info-icon\"><Icon name=\"shield\"/></div><h3>Geninstaller server</h3><p>Som Pterodactyls Reinstall: Nodexa stopper serveren og åbner automatisk Installation Console, hvor hele Egg/template-processen vises live. Worlds, plugins og øvrige brugerfiler bevares.</p>{reinstallMessage && <div className=\"info-note\">{reinstallMessage}</div>}{canReinstall && <button className=\"secondary-btn full\" disabled={!managed || reinstalling} onClick={reinstall}>{reinstalling ? 'Geninstallerer…' : managed ? 'Geninstaller server' : 'Ingen managed template'}</button>}<div className=\"info-note\">Startup command, Docker image og environment-variabler kan kun ændres af en administrator.</div></div></div></div>;\n}"
if old in text: text=text.replace(old,new,1)
p.write_text(text)
PY
fi

RUNTIME_OPT="$(cd "$(dirname "$0")" && pwd)/optimize-minecraft-runtime.sh"
if [[ -f "$RUNTIME_OPT" ]]; then
  NODEXA_PANEL_DIR="$PANEL_DIR" bash "$RUNTIME_OPT"
fi

echo "[Nodexa] Managed server template UI enabled with live reinstall console."
