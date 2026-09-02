import React,{useEffect,useMemo,useState}from'react';
import axios from'axios';
import'./node-detail.css';

type NodeInfo={id:number;name:string;fqdn:string;scheme:string;daemon_port:number;sftp_port:number;memory_mb:number;disk_mb:number;location?:string|null;health_status?:string|null;health_latency_ms?:number|null;health_last_seen_at?:string|null;health_message?:string|null};
type NodeServer={id:string;name:string;identifier?:string|null;status:string;memory_mb:number;disk_mb:number;cpu_limit:number};
type Allocation={id:number;server_id?:string|null;ip:string;alias?:string|null;port:number;notes?:string|null;is_primary:boolean;server?:{id:string;name:string;identifier?:string|null}|null};
type Capacity={memory_mb:number;memory_allocated_mb:number;memory_free_mb:number;disk_mb:number;disk_allocated_mb:number;disk_free_mb:number;servers:number;allocations_total:number;allocations_assigned:number;allocations_free:number};
type DetailResponse={node:NodeInfo;capacity:Capacity;servers:NodeServer[];allocations:Allocation[]};
type NodeConfig={node_id:number;panel_url:string;token:string;scheme:string;fqdn:string;listen:string;public_port:number;sftp_port:number;data:string;backups:string;install_command:string};

const apiError=(e:any)=>e?.response?.data?.message||e?.response?.data?.error||e?.message||'Handlingen fejlede.';
const fmtMb=(value:number)=>value>=1024?`${(value/1024).toFixed(value%1024===0?0:1)} GB`:`${value} MB`;
const pct=(value:number,total:number)=>total>0?Math.min(100,Math.max(0,value/total*100)):0;
const address=(allocation:Allocation)=>`${allocation.alias||allocation.ip}:${allocation.port}`;

export default function NodeDetailPage({nodeId,onBack}:{nodeId:number;onBack:()=>void}){
  const[detail,setDetail]=useState<DetailResponse|null>(null);
  const[config,setConfig]=useState<NodeConfig|null>(null);
  const[showSecrets,setShowSecrets]=useState(false);
  const[error,setError]=useState('');
  const[message,setMessage]=useState('');
  const[busy,setBusy]=useState(false);
  const[single,setSingle]=useState({ip:'0.0.0.0',port:25565,alias:''});
  const[range,setRange]=useState({ip:'0.0.0.0',start_port:25565,end_port:25575,alias:''});

  const load=async()=>{
    setError('');
    try{const r=await axios.get(`/api/nodes/${nodeId}`);setDetail(r.data as DetailResponse)}catch(e){setError(apiError(e))}
  };
  const loadConfig=async()=>{
    setError('');
    try{const r=await axios.get(`/api/nodes/${nodeId}/config`);setConfig(r.data as NodeConfig)}catch(e){setError(apiError(e))}
  };
  useEffect(()=>{setDetail(null);setConfig(null);setShowSecrets(false);setMessage('');load();loadConfig()},[nodeId]);

  const createSingle=async()=>{
    setBusy(true);setError('');setMessage('');
    try{await axios.post(`/api/nodes/${nodeId}/allocations`,{ip:single.ip.trim(),port:Number(single.port),alias:single.alias.trim()||null});setMessage('Allocation oprettet.');await load()}
    catch(e){setError(apiError(e))}finally{setBusy(false)}
  };
  const createRange=async()=>{
    setBusy(true);setError('');setMessage('');
    try{const r=await axios.post(`/api/nodes/${nodeId}/allocations/range`,{ip:range.ip.trim(),start_port:Number(range.start_port),end_port:Number(range.end_port),alias:range.alias.trim()||null});setMessage(`${Number(r.data?.created||0)} allocations behandlet.`);await load()}
    catch(e){setError(apiError(e))}finally{setBusy(false)}
  };
  const removeAllocation=async(allocation:Allocation)=>{
    if(allocation.server_id||!confirm(`Slet ${address(allocation)} fra Node-poolen?`))return;
    setBusy(true);setError('');setMessage('');
    try{await axios.delete(`/api/nodes/${nodeId}/allocations/${allocation.id}`);setMessage('Allocation slettet.');await load()}
    catch(e){setError(apiError(e))}finally{setBusy(false)}
  };
  const rotateToken=async()=>{
    if(!confirm('Rotér Node-token? Den installerede Agent mister adgang, indtil den nye konfiguration er anvendt på noden.'))return;
    setBusy(true);setError('');setMessage('');setShowSecrets(false);
    try{const r=await axios.post(`/api/nodes/${nodeId}/rotate-token`);setConfig(r.data?.configuration as NodeConfig);setMessage('Node-token roteret. Kopiér og anvend den nye konfiguration på Agent-serveren.')}
    catch(e){setError(apiError(e))}finally{setBusy(false)}
  };
  const copy=async(value:string,label:string)=>{
    try{await navigator.clipboard.writeText(value);setMessage(`${label} kopieret.`)}catch{setError('Browseren tillod ikke kopiering til udklipsholderen.')}
  };
  const configText=useMemo(()=>config?[
    `panel_url: ${JSON.stringify(config.panel_url)}`,
    `token: ${JSON.stringify(config.token)}`,
    `scheme: ${JSON.stringify(config.scheme)}`,
    `fqdn: ${JSON.stringify(config.fqdn)}`,
    `listen: ${JSON.stringify(config.listen)}`,
    `public_port: ${config.public_port}`,
    `sftp_port: ${config.sftp_port}`,
    `data: ${JSON.stringify(config.data)}`,
    `backups: ${JSON.stringify(config.backups)}`,
  ].join('\n'):'',[config]);

  if(!detail&&!error)return <div className="nx-node-detail-loading">Indlæser Node…</div>;
  if(!detail)return <div className="nx-node-detail-stack"><button className="nx-node-back" onClick={onBack}>← Tilbage til Nodes</button><div className="nx-node-error">{error||'Node kunne ikke indlæses.'}</div></div>;

  const{node,capacity,servers,allocations}=detail;
  const health=String(node.health_status||'unknown').toLowerCase();
  const healthLabel=health==='online'?'Online':health==='offline'?'Offline':'Ukendt';
  const memoryPct=pct(capacity.memory_allocated_mb,capacity.memory_mb);
  const diskPct=pct(capacity.disk_allocated_mb,capacity.disk_mb);

  return <div className="nx-node-detail-stack">
    <header className="nx-node-detail-head"><div><button className="nx-node-back" onClick={onBack}>← Nodes</button><div className="nx-node-eyebrow">NODE ADMINISTRATION</div><h1>{node.name}</h1><p>{node.location||'Ingen lokation'} · {node.scheme}://{node.fqdn}:{node.daemon_port}</p></div><div className={`nx-node-health ${health==='online'?'online':health==='offline'?'offline':''}`} title={node.health_message||undefined}><i/>{healthLabel}{node.health_latency_ms!==null&&node.health_latency_ms!==undefined&&<span>{node.health_latency_ms} ms</span>}</div></header>
    {error&&<div className="nx-node-error">{error}</div>}{message&&<div className="nx-node-success">{message}</div>}

    <section className="nx-node-capacity-grid">
      <div className="nx-node-card nx-node-capacity"><div><span>RAM KAPACITET</span><strong>{fmtMb(capacity.memory_allocated_mb)} / {fmtMb(capacity.memory_mb)}</strong><small>{fmtMb(capacity.memory_free_mb)} fri</small></div><div className="nx-node-progress"><i style={{width:`${memoryPct}%`}}/></div></div>
      <div className="nx-node-card nx-node-capacity"><div><span>DISK KAPACITET</span><strong>{fmtMb(capacity.disk_allocated_mb)} / {fmtMb(capacity.disk_mb)}</strong><small>{fmtMb(capacity.disk_free_mb)} fri</small></div><div className="nx-node-progress"><i style={{width:`${diskPct}%`}}/></div></div>
      <div className="nx-node-card nx-node-mini-stat"><span>Servere</span><strong>{capacity.servers}</strong><small>Tilknyttet Node</small></div>
      <div className="nx-node-card nx-node-mini-stat"><span>Allocations</span><strong>{capacity.allocations_assigned}/{capacity.allocations_total}</strong><small>{capacity.allocations_free} ledige</small></div>
    </section>

    <section className="nx-node-card nx-node-section"><div className="nx-node-section-head"><div><h2>Servere</h2><p>Servere placeret på denne Node og deres tildelte ressourcer.</p></div></div><div className="nx-node-table nx-node-servers"><div className="nx-node-table-head"><span>Server</span><span>Status</span><span>RAM</span><span>Disk</span><span>CPU</span></div>{servers.map(server=><div className="nx-node-table-row" key={server.id}><div><strong>{server.name}</strong><small>{server.identifier||server.id}</small></div><span className="nx-node-server-status">{server.status}</span><span>{fmtMb(server.memory_mb)}</span><span>{fmtMb(server.disk_mb)}</span><span>{server.cpu_limit||0}%</span></div>)}{!servers.length&&<div className="nx-node-empty">Ingen servere er placeret på denne Node endnu.</div>}</div></section>

    <section className="nx-node-card nx-node-section"><div className="nx-node-section-head"><div><h2>Allocations</h2><p>Administrer IP-aliaser og porte, som kan tildeles servere på noden.</p></div></div><div className="nx-node-allocation-create"><div><h3>Enkelt allocation</h3><div className="nx-node-form-row"><input value={single.ip} onChange={e=>setSingle({...single,ip:e.target.value})} placeholder="IP"/><input type="number" min={1} max={65535} value={single.port} onChange={e=>setSingle({...single,port:Number(e.target.value)})}/><input value={single.alias} onChange={e=>setSingle({...single,alias:e.target.value})} placeholder="Alias (valgfri)"/><button disabled={busy||!single.ip.trim()} onClick={createSingle}>Opret</button></div></div><div><h3>Port-range</h3><div className="nx-node-form-row range"><input value={range.ip} onChange={e=>setRange({...range,ip:e.target.value})} placeholder="IP"/><input type="number" min={1} max={65535} value={range.start_port} onChange={e=>setRange({...range,start_port:Number(e.target.value)})}/><input type="number" min={1} max={65535} value={range.end_port} onChange={e=>setRange({...range,end_port:Number(e.target.value)})}/><input value={range.alias} onChange={e=>setRange({...range,alias:e.target.value})} placeholder="Alias"/><button disabled={busy||!range.ip.trim()} onClick={createRange}>Opret range</button></div></div></div><div className="nx-node-table nx-node-allocations"><div className="nx-node-table-head"><span>Adresse</span><span>Status</span><span>Server</span><span>Noter</span><span/></div>{allocations.map(allocation=><div className="nx-node-table-row" key={allocation.id}><div><strong>{allocation.alias||allocation.ip}</strong><small>{allocation.ip}:{allocation.port}</small></div><span>{allocation.server_id?'Tildelt':'Ledig'}</span><span>{allocation.server?.name||'—'}</span><span>{allocation.notes||'—'}</span><div>{!allocation.server_id&&<button className="danger" disabled={busy} onClick={()=>removeAllocation(allocation)}>Slet</button>}</div></div>)}{!allocations.length&&<div className="nx-node-empty">Ingen allocations oprettet endnu.</div>}</div></section>

    <section className="nx-node-card nx-node-section"><div className="nx-node-section-head"><div><h2>Agent-konfiguration</h2><p>Se, kopiér og rotér den hemmelige konfiguration til Nodexa Agent.</p></div><div className="nx-node-actions"><button disabled={busy||!config} onClick={()=>setShowSecrets(v=>!v)}>{showSecrets?'Skjul secrets':'Vis secrets'}</button><button disabled={busy||!config} onClick={()=>config&&copy(config.install_command,'Installationskommando')}>Kopiér install command</button><button className="danger" disabled={busy} onClick={rotateToken}>Rotér token</button></div></div>{config?<><pre className="nx-node-config">{showSecrets?configText:configText.replace(/token: .*/, 'token: "••••••••••••••••••••••••••••••••"')}</pre><div className="nx-node-actions under"><button disabled={!showSecrets} onClick={()=>copy(configText,'Agent-konfiguration')}>Kopiér konfiguration</button></div><div className="nx-node-install"><span>INSTALL COMMAND</span><code>{showSecrets?config.install_command:'••••••••••••••••••••••••••••••••••••••••••••••••'}</code></div></>:<div className="nx-node-empty">Konfiguration kunne ikke indlæses.</div>}</section>
  </div>;
}
