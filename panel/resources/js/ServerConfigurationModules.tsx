import React,{useEffect,useMemo,useState} from 'react';
import axios from 'axios';
import './server-configuration-modules.css';

type ServerLike={id:string;uuid:string;name:string;identifier?:string;status?:string};
type Allocation={id:number;ip:string;ip_alias?:string|null;port:number;notes?:string|null;is_primary:boolean};
const err=(e:any)=>e?.response?.data?.message||e?.response?.data?.error||e?.message||'Handlingen fejlede.';

export function NetworkPage({server,canUpdate=true}:{server:ServerLike;canUpdate?:boolean}){
 const [items,setItems]=useState<Allocation[]>([]),[error,setError]=useState('');
 const load=async()=>{try{const r=await axios.get(`/api/servers/${server.id}/allocations`);setItems(Array.isArray(r.data)?r.data:[])}catch(e){setError(err(e))}};
 useEffect(()=>{load()},[server.id]); const primary=items.find(x=>x.is_primary);
 const makePrimary=async(a:Allocation)=>{try{await axios.post(`/api/servers/${server.id}/allocations/${a.id}/primary`);await load()}catch(e){setError(err(e))}};
 const note=async(a:Allocation)=>{const notes=prompt('Noter til allocation',a.notes||'');if(notes===null)return;try{await axios.put(`/api/servers/${server.id}/allocations/${a.id}`,{notes});await load()}catch(e){setError(err(e))}};
 return <div className="nx-config-stack"><header className="nx-config-head"><div><h2>Netværk</h2><p>IP-adresser og porte som serveren kan lytte på.</p></div></header>{error&&<div className="nx-config-error">{error}</div>}{primary&&<div className="nx-primary-allocation"><small>PRIMÆR ALLOCATION</small><strong>{primary.ip_alias||primary.ip}:{primary.port}</strong><span>{primary.ip_alias&&primary.ip_alias!==primary.ip?primary.ip:''}</span></div>}<div className="nx-config-card"><div className="nx-allocation-row nx-allocation-header"><span>IP</span><span>Port</span><span>Noter</span><span>Status</span><span/></div>{items.map(a=><div className="nx-allocation-row" key={a.id}><strong>{a.ip_alias||a.ip}</strong><code>{a.port}</code><span>{a.notes||'—'}</span><span>{a.is_primary?<b className="nx-primary-badge">Primær</b>:'Ekstra'}</span><div>{canUpdate&&<><button onClick={()=>note(a)}>Noter</button>{!a.is_primary&&<button onClick={()=>makePrimary(a)}>Gør primær</button>}</>}</div></div>)}{!items.length&&<div className="nx-config-empty">Ingen allocations er tildelt serveren.</div>}</div></div>;
}

export function StartupPage({server,isAdmin=false}:{server:ServerLike;isAdmin?:boolean}){
 const [data,setData]=useState<any>(null),[error,setError]=useState(''),[saving,setSaving]=useState(false);
 const [envText,setEnvText]=useState('{}');
 const load=async()=>{setError('');try{const r=await axios.get(`/api/admin/servers/${server.id}/startup`);setData(r.data);setEnvText(JSON.stringify(r.data.environment||{},null,2))}catch(e){setError(err(e))}};
 useEffect(()=>{if(isAdmin)load()},[server.id,isAdmin]);
 const save=async()=>{if(!data)return;setSaving(true);try{const environment=JSON.parse(envText||'{}');const r=await axios.put(`/api/admin/servers/${server.id}/startup`,{startup:data.startup,docker_image:data.docker_image,environment});setData(r.data);setEnvText(JSON.stringify(r.data.environment||{},null,2))}catch(e){setError(err(e))}finally{setSaving(false)}};
 if(!isAdmin)return <div className="nx-config-card nx-config-empty">Startup-konfiguration kan kun ændres af en administrator.</div>;
 if(!data)return <div className="nx-config-stack">{error&&<div className="nx-config-error">{error}</div>}<div className="nx-config-card nx-config-empty">Indlæser startup…</div></div>;
 return <div className="nx-config-stack"><header className="nx-config-head"><div><h2>Startup</h2><p>Docker image, startup-kommando og miljøvariabler.</p></div><button className="primary" disabled={saving} onClick={save}>{saving?'Gemmer…':'Gem ændringer'}</button></header>{error&&<div className="nx-config-error">{error}</div>}<div className="nx-config-card nx-config-form"><label>STARTUP COMMAND<textarea value={data.startup||''} onChange={e=>setData({...data,startup:e.target.value})}/></label><label>DOCKER IMAGE<input value={data.docker_image||''} onChange={e=>setData({...data,docker_image:e.target.value})}/></label><label>MILJØVARIABLER <small>JSON</small><textarea className="env" value={envText} onChange={e=>setEnvText(e.target.value)} spellCheck={false}/></label></div></div>;
}

export function SettingsPage({server,canReinstall=true}:{server:ServerLike;canReinstall?:boolean}){
 const [name,setName]=useState(server.name),[error,setError]=useState(''),[busy,setBusy]=useState(false),[message,setMessage]=useState('');
 useEffect(()=>setName(server.name),[server.id,server.name]);
 const save=async()=>{setBusy(true);setMessage('');try{await axios.put(`/api/servers/${server.id}`,{name});setMessage('Servernavnet er gemt.')}catch(e){setError(err(e))}finally{setBusy(false)}};
 const reinstall=async()=>{if(!confirm('Geninstaller serveren? Nodexa forsøger at bevare brugerdata, men serverfiler kan blive ændret.'))return;setBusy(true);try{await axios.post(`/api/servers/${server.id}/reinstall`);setMessage('Geninstallationen er startet.')}catch(e){setError(err(e))}finally{setBusy(false)}};
 return <div className="nx-config-stack"><header className="nx-config-head"><div><h2>Indstillinger</h2><p>Administrer serverens identitet og installationsstatus.</p></div></header>{error&&<div className="nx-config-error">{error}</div>}{message&&<div className="nx-config-success">{message}</div>}<div className="nx-config-card nx-config-form"><label>SERVERNAVN<input value={name} onChange={e=>setName(e.target.value)}/></label><button className="primary" disabled={busy||!name.trim()} onClick={save}>Gem serverdetaljer</button></div><div className="nx-config-card nx-debug"><h3>Debug information</h3><dl><div><dt>Server ID</dt><dd>{server.id}</dd></div><div><dt>UUID</dt><dd>{server.uuid}</dd></div><div><dt>Identifier</dt><dd>{server.identifier||'—'}</dd></div><div><dt>Status</dt><dd>{server.status||'—'}</dd></div></dl></div>{canReinstall&&<div className="nx-config-card nx-danger-zone"><div><h3>Geninstaller server</h3><p>Kører Nodexa-templateinstallationen igen. Brug denne funktion hvis installationen er beskadiget.</p></div><button disabled={busy} onClick={reinstall}>Geninstaller server</button></div>}</div>;
}
