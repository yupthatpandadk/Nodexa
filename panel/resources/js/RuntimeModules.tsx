import React, { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import './runtime-modules.css';

type ServerLike = { id: string; name: string; identifier?: string };
type FileItem = { name: string; directory: boolean; size: number; modified_at?: string };
type BackupItem = { name: string; size: number; modified_at?: string };
type ScheduleTask = { id?: number; action: 'command'|'power'|'backup'; payload?: string|null; time_offset?: number; continue_on_failure?: boolean };
type ScheduleMode = 'hourly'|'daily'|'weekly'|'monthly'|'advanced';
type ScheduleItem = { id: number; name: string; mode: string; cron_minute: string; cron_hour: string; cron_day_of_month: string; cron_month: string; cron_day_of_week: string; timezone: string; enabled: boolean; only_when_online: boolean; last_run_at?: string|null; next_run_at?: string|null; tasks: ScheduleTask[] };
type ScheduleDraft = { name: string; mode: ScheduleMode; time: string; weekday: number; monthday: number; cron_minute: string; cron_hour: string; cron_day_of_month: string; cron_month: string; cron_day_of_week: string; timezone: string; enabled: boolean; only_when_online: boolean; tasks: ScheduleTask[] };

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

const browserTimezone = () => Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Copenhagen';
const scheduleModes: ScheduleMode[] = ['hourly','daily','weekly','monthly','advanced'];
const pad2 = (value: string|number|undefined|null) => String(value ?? '0').padStart(2,'0');
const numericCron = (value: string|undefined|null, fallback: number) => /^\d+$/.test(String(value ?? '')) ? Number(value) : fallback;
const newScheduleTask = (action: ScheduleTask['action'] = 'power'): ScheduleTask => ({ action, payload: action === 'power' ? 'restart' : action === 'backup' ? 'Automatisk backup' : '', time_offset: 0, continue_on_failure: false });
const newScheduleDraft = (): ScheduleDraft => ({ name:'Daglig genstart', mode:'daily', time:'04:00', weekday:1, monthday:1, cron_minute:'0', cron_hour:'4', cron_day_of_month:'*', cron_month:'*', cron_day_of_week:'*', timezone:browserTimezone(), enabled:true, only_when_online:false, tasks:[newScheduleTask()] });

function draftFromSchedule(s: ScheduleItem): ScheduleDraft {
  const mode = scheduleModes.includes(s.mode as ScheduleMode) ? s.mode as ScheduleMode : 'advanced';
  const minute = numericCron(s.cron_minute,0);
  const hour = numericCron(s.cron_hour,0);
  return {
    name:s.name,
    mode,
    time:`${pad2(hour)}:${pad2(minute)}`,
    weekday:numericCron(s.cron_day_of_week,1),
    monthday:numericCron(s.cron_day_of_month,1),
    cron_minute:s.cron_minute || '*',
    cron_hour:s.cron_hour || '*',
    cron_day_of_month:s.cron_day_of_month || '*',
    cron_month:s.cron_month || '*',
    cron_day_of_week:s.cron_day_of_week || '*',
    timezone:s.timezone || browserTimezone(),
    enabled:!!s.enabled,
    only_when_online:!!s.only_when_online,
    tasks:(s.tasks || []).map(t=>({ action:t.action, payload:t.payload || '', time_offset:Number(t.time_offset || 0), continue_on_failure:!!t.continue_on_failure })),
  };
}

function schedulePayload(draft: ScheduleDraft) {
  const base = {
    name:draft.name.trim(),
    mode:draft.mode,
    timezone:draft.timezone || browserTimezone(),
    enabled:draft.enabled,
    only_when_online:draft.only_when_online,
    tasks:draft.tasks.map(t=>({ action:t.action, payload:t.payload || '', time_offset:Math.max(0,Math.min(86400,Number(t.time_offset || 0))), continue_on_failure:!!t.continue_on_failure })),
  };
  if (draft.mode === 'advanced') return { ...base, cron_minute:draft.cron_minute || '*', cron_hour:draft.cron_hour || '*', cron_day_of_month:draft.cron_day_of_month || '*', cron_month:draft.cron_month || '*', cron_day_of_week:draft.cron_day_of_week || '*' };
  return { ...base, time:draft.time, weekday:Number(draft.weekday), monthday:Number(draft.monthday) };
}

function scheduleDescription(s: ScheduleItem) {
  const minute = pad2(numericCron(s.cron_minute,0));
  const hour = pad2(numericCron(s.cron_hour,0));
  if (s.mode === 'hourly') return `Hver time kl. :${minute}`;
  if (s.mode === 'daily') return `Dagligt kl. ${hour}:${minute}`;
  if (s.mode === 'weekly') return `Ugentligt · dag ${s.cron_day_of_week} kl. ${hour}:${minute}`;
  if (s.mode === 'monthly') return `Månedligt · dag ${s.cron_day_of_month} kl. ${hour}:${minute}`;
  return `Cron ${s.cron_minute} ${s.cron_hour} ${s.cron_day_of_month} ${s.cron_month} ${s.cron_day_of_week}`;
}

function scheduleTaskLabel(task: ScheduleTask) {
  if (task.action === 'command') return `Kommando: ${task.payload || '—'}`;
  if (task.action === 'backup') return `Backup: ${task.payload || 'Automatisk backup'}`;
  return `Power: ${task.payload || 'restart'}`;
}

export function SchedulesPage({ server, canCreate = true, canUpdate = true, canDelete = true, canExecute = true }: { server: ServerLike; canCreate?: boolean; canUpdate?: boolean; canDelete?: boolean; canExecute?: boolean }) {
  const [items,setItems]=useState<ScheduleItem[]>([]);
  const [error,setError]=useState('');
  const [draft,setDraft]=useState<ScheduleDraft|null>(null);
  const [editingId,setEditingId]=useState<number|null>(null);
  const [busy,setBusy]=useState(false);

  const load=async()=>{try{setError('');const r=await axios.get(`/api/servers/${server.id}/schedules`);setItems(Array.isArray(r.data)?r.data:[])}catch(e){setError(apiError(e))}};
  useEffect(()=>{load()},[server.id]);

  const openCreate=()=>{setEditingId(null);setDraft(newScheduleDraft());setError('')};
  const openEdit=(s:ScheduleItem)=>{setEditingId(s.id);setDraft(draftFromSchedule(s));setError('')};
  const closeEditor=()=>{if(busy)return;setDraft(null);setEditingId(null)};
  const save=async()=>{
    if(!draft || !draft.name.trim() || !draft.tasks.length) return;
    setBusy(true); setError('');
    try {
      if(editingId===null) await axios.post(`/api/servers/${server.id}/schedules`,schedulePayload(draft));
      else await axios.put(`/api/servers/${server.id}/schedules/${editingId}`,schedulePayload(draft));
      setDraft(null); setEditingId(null); await load();
    } catch(e) { setError(apiError(e)); }
    finally { setBusy(false); }
  };
  const toggle=async(s:ScheduleItem)=>{try{const next={...draftFromSchedule(s),enabled:!s.enabled};await axios.put(`/api/servers/${server.id}/schedules/${s.id}`,schedulePayload(next));await load()}catch(e){setError(apiError(e))}};
  const run=async(s:ScheduleItem)=>{try{await axios.post(`/api/servers/${server.id}/schedules/${s.id}/run`);await load()}catch(e){setError(apiError(e))}};
  const remove=async(s:ScheduleItem)=>{if(!confirm(`Slet planlægningen ${s.name}?`))return;try{await axios.delete(`/api/servers/${server.id}/schedules/${s.id}`);await load()}catch(e){setError(apiError(e))}};
  const updateTask=(index:number,patch:Partial<ScheduleTask>)=>setDraft(current=>current?{...current,tasks:current.tasks.map((task,i)=>i===index?{...task,...patch}:task)}:current);
  const removeTask=(index:number)=>setDraft(current=>current?{...current,tasks:current.tasks.filter((_,i)=>i!==index)}:current);
  const addTask=(action:ScheduleTask['action'])=>setDraft(current=>current?{...current,tasks:[...current.tasks,newScheduleTask(action)]}:current);
  const moveTask=(index:number,direction:-1|1)=>setDraft(current=>{
    if(!current) return current;
    const target=index+direction; if(target<0||target>=current.tasks.length)return current;
    const tasks=[...current.tasks]; [tasks[index],tasks[target]]=[tasks[target],tasks[index]]; return {...current,tasks};
  });

  return <div className="nx-runtime-stack">
    <div className="nx-module-head"><div><h2>Planlægninger</h2><p>Automatiser kommandoer, strømhandlinger og backups i task chains.</p></div>{canCreate&&<button className="nx-btn primary" onClick={openCreate}>+ Ny planlægning</button>}</div>
    {error&&<div className="nx-error">{error}</div>}
    <div className="nx-card"><div className="nx-schedule-list">{items.map(s=><div className="nx-schedule" key={s.id}><div className={`nx-status-dot ${s.enabled?'on':''}`}/><div><strong>{s.name}</strong><span>{scheduleDescription(s)} · {s.timezone}</span><small>{s.tasks?.map((t,i)=>`${i>0&&Number(t.time_offset||0)>0?`+${t.time_offset}s · `:''}${scheduleTaskLabel(t)}`).join(' → ')}</small>{(s.last_run_at||s.next_run_at)&&<small>Sidst: {s.last_run_at?new Date(s.last_run_at).toLocaleString('da-DK'):'Aldrig'} · Næste: {s.next_run_at?new Date(s.next_run_at).toLocaleString('da-DK'):'—'}</small>}</div><div className="nx-row-actions">{canExecute&&<button onClick={()=>run(s)}>Kør nu</button>}{canUpdate&&<button onClick={()=>openEdit(s)}>Rediger</button>}{canUpdate&&<button onClick={()=>toggle(s)}>{s.enabled?'Deaktivér':'Aktivér'}</button>}{canDelete&&<button className="danger" onClick={()=>remove(s)}>Slet</button>}</div></div>)}{!items.length&&<div className="nx-empty">Ingen planlægninger endnu.</div>}</div></div>
    {draft&&<div className="nx-modal"><div className="nx-modal-card nx-schedule-editor"><div className="nx-editor-title"><div><small>{editingId===null?'NY PLANLÆGNING':'REDIGER PLANLÆGNING'}</small><h3>{editingId===null?'Opret planlægning':draft.name}</h3></div><button disabled={busy} onClick={closeEditor}>×</button></div>
      <label>Navn<input value={draft.name} disabled={busy} onChange={e=>setDraft({...draft,name:e.target.value})}/></label>
      <div className="nx-grid2"><label>Gentagelse<select value={draft.mode} disabled={busy} onChange={e=>setDraft({...draft,mode:e.target.value as ScheduleMode})}><option value="hourly">Hver time</option><option value="daily">Dagligt</option><option value="weekly">Ugentligt</option><option value="monthly">Månedligt</option><option value="advanced">Avanceret cron</option></select></label>{draft.mode!=='advanced'&&<label>Tidspunkt<input type="time" value={draft.time} disabled={busy} onChange={e=>setDraft({...draft,time:e.target.value})}/></label>}</div>
      {draft.mode==='weekly'&&<label>Ugedag<select value={draft.weekday} disabled={busy} onChange={e=>setDraft({...draft,weekday:Number(e.target.value)})}><option value={1}>Mandag</option><option value={2}>Tirsdag</option><option value={3}>Onsdag</option><option value={4}>Torsdag</option><option value={5}>Fredag</option><option value={6}>Lørdag</option><option value={0}>Søndag</option></select></label>}
      {draft.mode==='monthly'&&<label>Dag i måneden<input type="number" min={1} max={31} value={draft.monthday} disabled={busy} onChange={e=>setDraft({...draft,monthday:Math.max(1,Math.min(31,Number(e.target.value)||1))})}/></label>}
      {draft.mode==='advanced'&&<div className="nx-cron-grid"><label>Minut<input value={draft.cron_minute} disabled={busy} onChange={e=>setDraft({...draft,cron_minute:e.target.value})}/></label><label>Time<input value={draft.cron_hour} disabled={busy} onChange={e=>setDraft({...draft,cron_hour:e.target.value})}/></label><label>Dag<input value={draft.cron_day_of_month} disabled={busy} onChange={e=>setDraft({...draft,cron_day_of_month:e.target.value})}/></label><label>Måned<input value={draft.cron_month} disabled={busy} onChange={e=>setDraft({...draft,cron_month:e.target.value})}/></label><label>Ugedag<input value={draft.cron_day_of_week} disabled={busy} onChange={e=>setDraft({...draft,cron_day_of_week:e.target.value})}/></label></div>}
      <label>Tidszone<input value={draft.timezone} disabled={busy} onChange={e=>setDraft({...draft,timezone:e.target.value})}/></label>
      <div className="nx-grid2 nx-check-grid"><label className="nx-check"><input type="checkbox" checked={draft.enabled} disabled={busy} onChange={e=>setDraft({...draft,enabled:e.target.checked})}/> Planlægningen er aktiv</label><label className="nx-check"><input type="checkbox" checked={draft.only_when_online} disabled={busy} onChange={e=>setDraft({...draft,only_when_online:e.target.checked})}/> Kør kun når serveren er online</label></div>
      <div className="nx-task-heading"><div><h3>Task chain</h3><p>Handlinger køres i rækkefølge. Delay er ventetid før den enkelte handling.</p></div><span>{draft.tasks.length} task{draft.tasks.length===1?'':'s'}</span></div>
      <div className="nx-task-list">{draft.tasks.map((task,index)=><div className="nx-task-card" key={index}><div className="nx-task-head"><strong>{index+1}. {scheduleTaskLabel(task)}</strong><div className="nx-row-actions"><button disabled={busy||index===0} onClick={()=>moveTask(index,-1)}>↑</button><button disabled={busy||index===draft.tasks.length-1} onClick={()=>moveTask(index,1)}>↓</button><button className="danger" disabled={busy||draft.tasks.length===1} onClick={()=>removeTask(index)}>Fjern</button></div></div><div className="nx-task-grid"><label>Handling<select value={task.action} disabled={busy} onChange={e=>{const action=e.target.value as ScheduleTask['action'];updateTask(index,{action,payload:newScheduleTask(action).payload})}}><option value="command">Send kommando</option><option value="power">Server handling</option><option value="backup">Lav backup</option></select></label>{task.action==='power'?<label>Signal<select value={task.payload||'restart'} disabled={busy} onChange={e=>updateTask(index,{payload:e.target.value})}><option value="start">Start</option><option value="stop">Stop</option><option value="restart">Genstart</option></select></label>:<label>{task.action==='command'?'Kommando':'Backup-navn'}<input value={task.payload||''} disabled={busy} placeholder={task.action==='command'?'say Serveren genstarter snart':'Automatisk backup'} onChange={e=>updateTask(index,{payload:e.target.value})}/></label>}<label>Delay før task (sek.)<input type="number" min={0} max={86400} value={Number(task.time_offset||0)} disabled={busy} onChange={e=>updateTask(index,{time_offset:Math.max(0,Math.min(86400,Number(e.target.value)||0))})}/></label><label className="nx-check nx-task-continue"><input type="checkbox" checked={!!task.continue_on_failure} disabled={busy} onChange={e=>updateTask(index,{continue_on_failure:e.target.checked})}/> Fortsæt kæden hvis denne task fejler</label></div></div>)}</div>
      <div className="nx-add-tasks"><button disabled={busy} onClick={()=>addTask('command')}>+ Kommando</button><button disabled={busy} onClick={()=>addTask('power')}>+ Server handling</button><button disabled={busy} onClick={()=>addTask('backup')}>+ Backup</button></div>
      <div className="nx-actions end"><button disabled={busy} onClick={closeEditor}>Annuller</button><button className="primary" disabled={busy||!draft.name.trim()||!draft.tasks.length} onClick={save}>{busy?'Gemmer…':editingId===null?'Opret planlægning':'Gem ændringer'}</button></div>
    </div></div>}
  </div>;
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
