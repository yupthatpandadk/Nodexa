import React, { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import './runtime-modules.css';

type ServerLike = { id: string; name: string; identifier?: string };
type FileItem = { name: string; directory: boolean; size: number; modified_at?: string };
type BackupItem = { name: string; size: number; modified_at?: string };
type ScheduleTask = { id?: number; action: 'command'|'power'|'backup'; payload?: string|null; time_offset?: number; continue_on_failure?: boolean };
type ScheduleItem = { id: number; name: string; mode: string; cron_minute: string; cron_hour: string; cron_day_of_month: string; cron_month: string; cron_day_of_week: string; timezone: string; enabled: boolean; only_when_online: boolean; last_run_at?: string|null; next_run_at?: string|null; tasks: ScheduleTask[] };

const apiError = (e: any) => e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Handlingen fejlede.';
const fmtBytes = (bytes: number) => {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
  if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
  if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${bytes} B`;
};
const joinPath = (base: string, name: string) => `${base === '/' ? '' : base}/${name}`.replace(/\/+/g, '/').replace(/^$/, '/');
const parentPath = (path: string) => { const parts = path.split('/').filter(Boolean); parts.pop(); return '/' + parts.join('/'); };

export function FilesPage({ server, canWrite = true }: { server: ServerLike; canWrite?: boolean }) {
  const [path, setPath] = useState('/');
  const [items, setItems] = useState<FileItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [editingPath, setEditingPath] = useState<string|null>(null);
  const [content, setContent] = useState('');
  const [saving, setSaving] = useState(false);
  const uploadId = `nx-upload-${server.id}`;

  const load = async (next = path) => {
    setLoading(true); setError('');
    try { const r = await axios.get(`/api/servers/${server.id}/files`, { params: { path: next } }); setItems(Array.isArray(r.data?.items) ? r.data.items : []); setPath(next); }
    catch (e) { setError(apiError(e)); }
    finally { setLoading(false); }
  };
  useEffect(() => { load('/'); }, [server.id]);

  const openFile = async (item: FileItem) => {
    const filePath = joinPath(path, item.name);
    if (item.directory) { load(filePath); return; }
    setError('');
    try { const r = await axios.get(`/api/servers/${server.id}/file`, { params: { path: filePath }, responseType: 'text' }); setEditingPath(filePath); setContent(typeof r.data === 'string' ? r.data : String(r.data ?? '')); }
    catch (e) { setError(apiError(e)); }
  };
  const save = async () => {
    if (!editingPath || !canWrite) return;
    setSaving(true); setError('');
    try { await axios.put(`/api/servers/${server.id}/file`, { path: editingPath, content }); setEditingPath(null); await load(path); }
    catch (e) { setError(apiError(e)); }
    finally { setSaving(false); }
  };
  const createFile = async () => {
    if (!canWrite) return;
    const name = prompt('Filnavn, fx server.properties'); if (!name) return;
    setEditingPath(joinPath(path, name)); setContent('');
  };
  const createFolder = async () => {
    if (!canWrite) return;
    const name = prompt('Navn på mappe'); if (!name) return;
    try { await axios.post(`/api/servers/${server.id}/directory`, { path: joinPath(path, name) }); await load(path); } catch (e) { setError(apiError(e)); }
  };
  const remove = async (item: FileItem) => {
    if (!canWrite || !confirm(`Slet ${item.name}?`)) return;
    try { await axios.delete(`/api/servers/${server.id}/file`, { data: { path: joinPath(path, item.name) } }); await load(path); } catch (e) { setError(apiError(e)); }
  };
  const rename = async (item: FileItem) => {
    if (!canWrite) return;
    const name = prompt('Nyt navn', item.name); if (!name || name === item.name) return;
    try { await axios.post(`/api/servers/${server.id}/file/rename`, { from: joinPath(path, item.name), to: joinPath(path, name) }); await load(path); } catch (e) { setError(apiError(e)); }
  };
  const upload = async (file?: File) => {
    if (!canWrite || !file) return;
    const form = new FormData(); form.append('path', path); form.append('file', file);
    try { await axios.post(`/api/servers/${server.id}/upload`, form, { headers: { 'Content-Type': 'multipart/form-data' } }); await load(path); } catch (e) { setError(apiError(e)); }
  };
  const download = async (item: FileItem) => {
    try { const r = await axios.get(`/api/servers/${server.id}/download`, { params: { path: joinPath(path, item.name) }, responseType: 'blob' }); const url = URL.createObjectURL(r.data); const a = document.createElement('a'); a.href = url; a.download = item.name; a.click(); URL.revokeObjectURL(url); } catch (e) { setError(apiError(e)); }
  };
  const archive = async (item: FileItem) => {
    if (!canWrite) return;
    try { await axios.post(`/api/servers/${server.id}/archive`, { path: joinPath(path, item.name) }); await load(path); } catch (e) { setError(apiError(e)); }
  };
  const extract = async (item: FileItem) => {
    if (!canWrite) return;
    try { await axios.post(`/api/servers/${server.id}/extract`, { path: joinPath(path, item.name) }); await load(path); } catch (e) { setError(apiError(e)); }
  };
  const crumbs = useMemo(() => path.split('/').filter(Boolean), [path]);

  return <div className="nx-runtime-stack">
    <div className="nx-module-head"><div><h2>Filer</h2><p>Administrer serverens filer direkte på Nodexa Agent.</p></div>{canWrite && <div className="nx-actions"><button onClick={createFile}>Ny fil</button><button onClick={createFolder}>Ny mappe</button><label className="nx-btn">Upload<input id={uploadId} type="file" hidden onChange={e => { upload(e.target.files?.[0]); e.currentTarget.value=''; }}/></label></div>}</div>
    {error && <div className="nx-error">{error}</div>}
    <div className="nx-card nx-files">
      <div className="nx-filebar"><button disabled={path==='/' || loading} onClick={() => load(parentPath(path))}>←</button><button onClick={() => load('/')}>/</button>{crumbs.map((c,i) => <React.Fragment key={`${c}-${i}`}><span>/</span><button onClick={() => load('/'+crumbs.slice(0,i+1).join('/'))}>{c}</button></React.Fragment>)}<span className="nx-spacer"/>{loading && <small>Indlæser…</small>}<button onClick={() => load(path)}>↻</button></div>
      <div className="nx-file-table"><div className="nx-file-row nx-file-header"><span>Navn</span><span>Størrelse</span><span>Ændret</span><span/></div>{items.map(item => <div className="nx-file-row" key={item.name}><button className="nx-file-name" onClick={() => openFile(item)}>{item.directory ? '📁' : '📄'} <strong>{item.name}</strong></button><span>{item.directory ? 'Mappe' : fmtBytes(item.size)}</span><span>{item.modified_at ? new Date(item.modified_at).toLocaleString('da-DK') : '—'}</span><div className="nx-row-actions">{!item.directory && <button onClick={() => download(item)}>Download</button>}{canWrite && <button onClick={() => rename(item)}>Omdøb</button>}{canWrite && item.directory && <button onClick={() => archive(item)}>Pak</button>}{canWrite && !item.directory && /\.(tar\.gz|tgz)$/i.test(item.name) && <button onClick={() => extract(item)}>Udpak</button>}{canWrite && <button className="danger" onClick={() => remove(item)}>Slet</button>}</div></div>)}{!items.length && !loading && <div className="nx-empty">Mappen er tom.</div>}</div>
    </div>
    {editingPath && <div className="nx-card nx-editor"><div className="nx-editor-head"><div><small>{canWrite ? 'REDIGERER' : 'VISER'}</small><strong>{editingPath}</strong></div><div className="nx-actions"><button onClick={() => setEditingPath(null)}>Luk</button>{canWrite && <button className="primary" disabled={saving} onClick={save}>{saving?'Gemmer…':'Gem'}</button>}</div></div><textarea value={content} readOnly={!canWrite} onChange={e => setContent(e.target.value)} spellCheck={false}/></div>}
  </div>;
}

function schedulePayload(s: ScheduleItem) {
  return { name:s.name, mode:'advanced', timezone:s.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Copenhagen', enabled:s.enabled, only_when_online:s.only_when_online, cron_minute:s.cron_minute||'*', cron_hour:s.cron_hour||'*', cron_day_of_month:s.cron_day_of_month||'*', cron_month:s.cron_month||'*', cron_day_of_week:s.cron_day_of_week||'*', tasks:(s.tasks||[]).map(t=>({action:t.action,payload:t.payload||'',time_offset:Number(t.time_offset||0),continue_on_failure:!!t.continue_on_failure})) };
}

export function SchedulesPage({ server, canCreate = true, canUpdate = true, canDelete = true, canExecute = true }: { server: ServerLike; canCreate?: boolean; canUpdate?: boolean; canDelete?: boolean; canExecute?: boolean }) {
  const [items,setItems]=useState<ScheduleItem[]>([]); const [error,setError]=useState(''); const [show,setShow]=useState(false); const [busy,setBusy]=useState(false);
  const [form,setForm]=useState({name:'Daglig genstart',mode:'daily',time:'04:00',weekday:1,action:'power',payload:'restart',only_when_online:false});
  const load=async()=>{try{const r=await axios.get(`/api/servers/${server.id}/schedules`);setItems(Array.isArray(r.data)?r.data:[])}catch(e){setError(apiError(e))}};
  useEffect(()=>{load()},[server.id]);
  const create=async()=>{setBusy(true);setError('');try{await axios.post(`/api/servers/${server.id}/schedules`,{name:form.name,mode:form.mode,time:form.time,weekday:Number(form.weekday),timezone:Intl.DateTimeFormat().resolvedOptions().timeZone||'Europe/Copenhagen',enabled:true,only_when_online:form.only_when_online,tasks:[{action:form.action,payload:form.payload,time_offset:0,continue_on_failure:false}]});setShow(false);await load()}catch(e){setError(apiError(e))}finally{setBusy(false)}};
  const toggle=async(s:ScheduleItem)=>{try{await axios.put(`/api/servers/${server.id}/schedules/${s.id}`,{...schedulePayload(s),enabled:!s.enabled});await load()}catch(e){setError(apiError(e))}};
  const run=async(s:ScheduleItem)=>{try{await axios.post(`/api/servers/${server.id}/schedules/${s.id}/run`);await load()}catch(e){setError(apiError(e))}};
  const remove=async(s:ScheduleItem)=>{if(!confirm(`Slet planlægningen ${s.name}?`))return;try{await axios.delete(`/api/servers/${server.id}/schedules/${s.id}`);await load()}catch(e){setError(apiError(e))}};
  const describe=(s:ScheduleItem)=>`${s.cron_minute} ${s.cron_hour} ${s.cron_day_of_month} ${s.cron_month} ${s.cron_day_of_week}`;
  return <div className="nx-runtime-stack"><div className="nx-module-head"><div><h2>Planlægninger</h2><p>Automatiser kommandoer, strømhandlinger og backups.</p></div>{canCreate&&<button className="nx-btn primary" onClick={()=>setShow(true)}>+ Ny planlægning</button>}</div>{error&&<div className="nx-error">{error}</div>}<div className="nx-card"><div className="nx-schedule-list">{items.map(s=><div className="nx-schedule" key={s.id}><div className={`nx-status-dot ${s.enabled?'on':''}`}/><div><strong>{s.name}</strong><span>Cron: <code>{describe(s)}</code> · {s.timezone}</span><small>{s.tasks?.map(t=>`${t.action}: ${t.payload||'—'}`).join(' → ')}</small></div><div className="nx-row-actions">{canExecute&&<button onClick={()=>run(s)}>Kør nu</button>}{canUpdate&&<button onClick={()=>toggle(s)}>{s.enabled?'Deaktivér':'Aktivér'}</button>}{canDelete&&<button className="danger" onClick={()=>remove(s)}>Slet</button>}</div></div>)}{!items.length&&<div className="nx-empty">Ingen planlægninger endnu.</div>}</div></div>{show&&<div className="nx-modal"><div className="nx-modal-card"><h3>Ny planlægning</h3><label>Navn<input value={form.name} onChange={e=>setForm({...form,name:e.target.value})}/></label><div className="nx-grid2"><label>Gentagelse<select value={form.mode} onChange={e=>setForm({...form,mode:e.target.value})}><option value="hourly">Hver time</option><option value="daily">Dagligt</option><option value="weekly">Ugentligt</option></select></label><label>Tid<input type="time" value={form.time} onChange={e=>setForm({...form,time:e.target.value})}/></label></div>{form.mode==='weekly'&&<label>Ugedag<select value={form.weekday} onChange={e=>setForm({...form,weekday:Number(e.target.value)})}><option value={1}>Mandag</option><option value={2}>Tirsdag</option><option value={3}>Onsdag</option><option value={4}>Torsdag</option><option value={5}>Fredag</option><option value={6}>Lørdag</option><option value={0}>Søndag</option></select></label>}<div className="nx-grid2"><label>Handling<select value={form.action} onChange={e=>{const action=e.target.value;setForm({...form,action,payload:action==='power'?'restart':action==='backup'?'Automatisk backup':''})}}><option value="power">Start / stop / genstart</option><option value="command">Kommando</option><option value="backup">Backup</option></select></label>{form.action==='power'?<label>Signal<select value={form.payload} onChange={e=>setForm({...form,payload:e.target.value})}><option value="restart">Genstart</option><option value="start">Start</option><option value="stop">Stop</option></select></label>:<label>{form.action==='backup'?'Backup-navn':'Kommando'}<input value={form.payload} onChange={e=>setForm({...form,payload:e.target.value})}/></label>}</div><label className="nx-check"><input type="checkbox" checked={form.only_when_online} onChange={e=>setForm({...form,only_when_online:e.target.checked})}/> Kør kun når serveren er online</label><div className="nx-actions end"><button onClick={()=>setShow(false)}>Annuller</button><button className="primary" disabled={busy||!form.name} onClick={create}>{busy?'Opretter…':'Opret'}</button></div></div></div>}</div>;
}

export function BackupsPage({ server, canCreate = true, canDownload = true, canRestore = true, canDelete = true }: { server: ServerLike; canCreate?: boolean; canDownload?: boolean; canRestore?: boolean; canDelete?: boolean }) {
  const [items,setItems]=useState<BackupItem[]>([]); const [error,setError]=useState(''); const [busy,setBusy]=useState(false);
  const load=async()=>{try{const r=await axios.get(`/api/servers/${server.id}/backups`);setItems(Array.isArray(r.data?.items)?r.data.items:[])}catch(e){setError(apiError(e))}};
  useEffect(()=>{load()},[server.id]);
  const create=async()=>{const name=prompt('Backup-navn',`backup-${new Date().toISOString().slice(0,16).replace(/[:T]/g,'-')}`);if(!name)return;setBusy(true);try{await axios.post(`/api/servers/${server.id}/backups`,{name});await load()}catch(e){setError(apiError(e))}finally{setBusy(false)}};
  const download=async(b:BackupItem)=>{try{const r=await axios.get(`/api/servers/${server.id}/backups/${encodeURIComponent(b.name)}/download`,{responseType:'blob'});const url=URL.createObjectURL(r.data);const a=document.createElement('a');a.href=url;a.download=b.name;a.click();URL.revokeObjectURL(url)}catch(e){setError(apiError(e))}};
  const restore=async(b:BackupItem)=>{if(!confirm(`Gendan ${b.name}? Serveren stoppes og nuværende serverfiler erstattes med backup-indholdet.`))return;setBusy(true);try{await axios.post(`/api/servers/${server.id}/backups/${encodeURIComponent(b.name)}/restore`);alert('Backup gendannet. Du kan nu starte serveren igen.')}catch(e){setError(apiError(e))}finally{setBusy(false)}};
  const remove=async(b:BackupItem)=>{if(!confirm(`Slet backup ${b.name}?`))return;try{await axios.delete(`/api/servers/${server.id}/backups/${encodeURIComponent(b.name)}`);await load()}catch(e){setError(apiError(e))}};
  return <div className="nx-runtime-stack"><div className="nx-module-head"><div><h2>Backups</h2><p>Opret, download og gendan komplette serverbackups.</p></div>{canCreate&&<button className="nx-btn primary" disabled={busy} onClick={create}>{busy?'Arbejder…':'+ Opret backup'}</button>}</div>{error&&<div className="nx-error">{error}</div>}<div className="nx-card"><div className="nx-backup-list">{items.map(b=><div className="nx-backup" key={b.name}><div className="nx-backup-icon">↺</div><div><strong>{b.name}</strong><span>{fmtBytes(b.size)} · {b.modified_at?new Date(b.modified_at).toLocaleString('da-DK'):'—'}</span></div><div className="nx-row-actions">{canDownload&&<button onClick={()=>download(b)}>Download</button>}{canRestore&&<button onClick={()=>restore(b)}>Gendan</button>}{canDelete&&<button className="danger" onClick={()=>remove(b)}>Slet</button>}</div></div>)}{!items.length&&<div className="nx-empty">Der er endnu ikke oprettet nogen backups.</div>}</div></div></div>;
}
