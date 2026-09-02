import React,{useEffect,useState} from 'react';
import axios from 'axios';
import './server-configuration-modules.css';

type ServerLike={id:string;uuid:string;name:string;identifier?:string;status?:string;node_id?:number};
type Allocation={id:number;node_id?:number;server_id?:string|null;ip:string;alias?:string|null;port:number;notes?:string|null;is_primary:boolean;server?:{id:string;name:string;identifier?:string}|null};
const err=(e:any)=>{const data=e?.response?.data;if(data?.error&&data?.message)return `${data.message} ${data.error}`;return data?.error||data?.message||e?.message||'Handlingen fejlede.'};
const allocationAddress=(a:Allocation)=>`${a.alias||a.ip}:${a.port}`;

export function NetworkPage({server,canUpdate=true}:{server:ServerLike;canUpdate?:boolean}){
  const[items,setItems]=useState<Allocation[]>([]),[pool,setPool]=useState<Allocation[]>([]),[error,setError]=useState(''),[adminManage,setAdminManage]=useState(false),[busyId,setBusyId]=useState<number|null>(null),[selectedId,setSelectedId]=useState('');
  const load=async()=>{
    setError('');
    try{
      const r=await axios.get(`/api/servers/${server.id}/allocations`);
      setItems(Array.isArray(r.data)?r.data:[]);
    }catch(e){setError(err(e));return}
    if(!server.node_id){setAdminManage(false);setPool([]);return}
    try{
      const r=await axios.get(`/api/nodes/${server.node_id}/allocations`);
      const all=Array.isArray(r.data)?r.data:[];
      const available=all.filter((a:Allocation)=>!a.server_id);
      setPool(available);setAdminManage(true);
      setSelectedId(current=>current&&available.some((a:Allocation)=>String(a.id)===current)?current:(available[0]?String(available[0].id):''));
    }catch(e:any){
      if(e?.response?.status===403){setAdminManage(false);setPool([]);setSelectedId('');return}
      setAdminManage(false);setPool([]);setSelectedId('');setError(err(e));
    }
  };
  useEffect(()=>{load()},[server.id,server.node_id]);
  const primary=items.find(x=>x.is_primary);
  const makePrimary=async(a:Allocation)=>{if(!canUpdate)return;setBusyId(a.id);setError('');try{await axios.post(`/api/servers/${server.id}/allocations/${a.id}/primary`);await load()}catch(e){setError(err(e))}finally{setBusyId(null)}};
  const note=async(a:Allocation)=>{if(!canUpdate)return;const notes=prompt('Noter til allocation',a.notes||'');if(notes===null)return;setBusyId(a.id);setError('');try{await axios.put(`/api/servers/${server.id}/allocations/${a.id}`,{notes});await load()}catch(e){setError(err(e))}finally{setBusyId(null)}};
  const assign=async()=>{const id=Number(selectedId);if(!adminManage||!id)return;setBusyId(id);setError('');try{await axios.post(`/api/servers/${server.id}/allocations`,{allocation_id:id});await load()}catch(e){setError(err(e))}finally{setBusyId(null)}};
  const unassign=async(a:Allocation)=>{if(!adminManage||a.is_primary||!confirm(`Fjern ${allocationAddress(a)} fra ${server.name}?`))return;setBusyId(a.id);setError('');try{await axios.delete(`/api/servers/${server.id}/allocations/${a.id}`);await load()}catch(e){setError(err(e))}finally{setBusyId(null)}};
  return <div className="nx-config-stack"><header className="nx-config-head"><div><h2>Netværk</h2><p>IP-adresser og porte som serveren kan lytte på.</p></div></header>{error&&<div className="nx-config-error">{error}</div>}{primary&&<div className="nx-primary-allocation"><small>PRIMÆR ALLOCATION</small><strong>{allocationAddress(primary)}</strong><span>{primary.notes||'Ingen noter'}</span></div>}{adminManage&&<div className="nx-config-card nx-allocation-admin"><div><small>ADMINISTRATION</small><h3>Tildel ekstra allocation</h3><p>Vælg en ledig IP/port fra serverens Node. Nodexa synkroniserer containerens netværksbindinger efter tildelingen.</p></div><div className="nx-allocation-assign"><select value={selectedId} onChange={e=>setSelectedId(e.target.value)} disabled={!pool.length||busyId!==null}>{pool.map(a=><option value={a.id} key={a.id}>{allocationAddress(a)}</option>)}</select><button className="primary" onClick={assign} disabled={!selectedId||busyId!==null}>{busyId!==null?'Arbejder…':'Tildel'}</button></div>{!pool.length&&<span className="nx-pool-empty">Ingen ledige allocations på denne Node.</span>}</div>}<div className="nx-config-card">{items.map(a=><div className="nx-allocation-row" key={a.id}><strong>{a.alias||a.ip}</strong><code>{a.port}</code><span>{a.notes||'—'}</span><span>{a.is_primary?'Primær':'Ekstra'}</span><div>{canUpdate&&<button disabled={busyId!==null} onClick={()=>note(a)}>Noter</button>}{canUpdate&&!a.is_primary&&<button disabled={busyId!==null} onClick={()=>makePrimary(a)}>Gør primær</button>}{adminManage&&!a.is_primary&&<button className="danger" disabled={busyId!==null} onClick={()=>unassign(a)}>Fjern</button>}</div></div>)}{!items.length&&!error&&<div className="nx-config-empty">Serveren har ingen allocations tilknyttet endnu.</div>}</div></div>
}

export function StartupPage({server,isAdmin=false}:{server:ServerLike;isAdmin?:boolean}){
  const[data,setData]=useState<any>(null),[error,setError]=useState(''),[saving,setSaving]=useState(false),[envText,setEnvText]=useState('{}');
  const load=async()=>{try{setError('');const r=await axios.get(`/api/admin/servers/${server.id}/startup`);setData(r.data);setEnvText(JSON.stringify(r.data.environment||{},null,2))}catch(e){setError(err(e))}};
  useEffect(()=>{if(isAdmin)load()},[server.id,isAdmin]);
  const save=async()=>{if(!data||!isAdmin)return;setSaving(true);setError('');try{const environment=JSON.parse(envText||'{}');const r=await axios.put(`/api/admin/servers/${server.id}/startup`,{startup:data.startup,docker_image:data.docker_image,environment});setData(r.data);setEnvText(JSON.stringify(r.data.environment||environment,null,2))}catch(e){setError(err(e))}finally{setSaving(false)}};
  if(!isAdmin)return <div className="nx-config-card nx-config-empty">Startup-konfiguration kan kun ændres af en administrator.</div>;
  if(!data&&!error)return <div className="nx-config-card nx-config-empty">Indlæser startup…</div>;
  return <div className="nx-config-stack"><header className="nx-config-head"><div><h2>Startup</h2><p>Administrer container image, startup command og miljøvariabler.</p></div><button onClick={save} disabled={saving||!data}>{saving?'Gemmer…':'Gem'}</button></header>{error&&<div className="nx-config-error">{error}</div>}{data&&<div className="nx-config-card nx-config-form"><label>STARTUP COMMAND<textarea value={data.startup||''} onChange={e=>setData({...data,startup:e.target.value})}/></label><label>DOCKER IMAGE<input value={data.docker_image||''} onChange={e=>setData({...data,docker_image:e.target.value})}/></label><label>MILJØVARIABLER<textarea value={envText} onChange={e=>setEnvText(e.target.value)}/></label></div>}</div>
}

export function SettingsPage({server,canUpdate=true,canReinstall=true,canSftp=true}:{server:ServerLike;canUpdate?:boolean;canReinstall?:boolean;canSftp?:boolean}){
  const[name,setName]=useState(server.name),[error,setError]=useState(''),[busy,setBusy]=useState(false),[message,setMessage]=useState(''),[sftp,setSftp]=useState<any>(null),[sftpPassword,setSftpPassword]=useState('');
  useEffect(()=>{
    setName(server.name);setError('');setMessage('');setSftp(null);setSftpPassword('');
    if(canSftp)axios.get(`/api/servers/${server.id}/sftp`).then(r=>setSftp(r.data)).catch(e=>setError(err(e)));
  },[server.id,server.name,canSftp]);
  const save=async()=>{if(!canUpdate||!name.trim())return;setBusy(true);setError('');setMessage('');try{await axios.put(`/api/servers/${server.id}`,{name:name.trim()});setName(name.trim());setMessage('Servernavnet er gemt.')}catch(e){setError(err(e))}finally{setBusy(false)}};
  const syncSftp=async()=>{if(!canSftp||!sftpPassword)return;setBusy(true);setError('');setMessage('');try{await axios.post(`/api/servers/${server.id}/sftp/sync`,{password:sftpPassword});setSftpPassword('');setMessage('SFTP-adgangen er synkroniseret med dit Nodexa-password.')}catch(e){setError(err(e))}finally{setBusy(false)}};
  const reinstall=async()=>{if(!canReinstall||!confirm('Geninstaller serveren? Template-filer installeres igen.'))return;setBusy(true);setError('');setMessage('');try{await axios.post(`/api/servers/${server.id}/reinstall`);setMessage('Geninstallationen er færdig. Serveren er klar til at blive startet.')}catch(e){setError(err(e))}finally{setBusy(false)}};
  return <div className="nx-config-stack"><header className="nx-config-head"><div><h2>Indstillinger</h2><p>Administrer serverdetaljer, SFTP og geninstallation.</p></div></header>{error&&<div className="nx-config-error">{error}</div>}{message&&<div className="nx-config-success">{message}</div>}{canSftp&&sftp&&<div className="nx-config-card nx-config-form"><h3>SFTP detaljer</h3><label>SERVER ADDRESS<input readOnly value={`sftp://${sftp.host}:${sftp.port}`}/></label><label>USERNAME<input readOnly value={sftp.username}/></label><p>Dit SFTP-password er det samme password, som du bruger til at logge ind på Nodexa.</p><label>BEKRÆFT DIT NUVÆRENDE PASSWORD<input type="password" autoComplete="current-password" value={sftpPassword} onChange={e=>setSftpPassword(e.target.value)} placeholder="••••••••"/></label><button className="primary" disabled={busy||!sftpPassword} onClick={syncSftp}>Aktivér / synkronisér SFTP</button></div>}<div className="nx-config-card nx-config-form"><label>SERVERNAVN<input readOnly={!canUpdate} value={name} onChange={e=>setName(e.target.value)}/></label>{canUpdate&&<button className="primary" disabled={busy||!name.trim()} onClick={save}>Gem serverdetaljer</button>}</div><div className="nx-config-card nx-debug"><h3>Debug information</h3><dl><div><dt>Server ID</dt><dd>{server.id}</dd></div><div><dt>UUID</dt><dd>{server.uuid}</dd></div></dl></div>{canReinstall&&<div className="nx-config-card nx-danger-zone"><div><h3>Geninstaller server</h3><p>Kører Nodexa-templateinstallationen igen. Brugerdata håndteres efter serverens template-regler.</p></div><button disabled={busy} onClick={reinstall}>Geninstaller server</button></div>}</div>
}
