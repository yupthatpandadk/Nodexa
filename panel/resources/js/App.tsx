import React, {useEffect, useState} from 'react';
import axios from 'axios';

type User={id:number;name:string;email:string;is_admin:boolean};
type Server={id:string;uuid:string;server_number?:number;identifier?:string;name:string;status:string;memory_mb:number;disk_mb:number;cpu_limit:number};
type Stats={state:string;cpu_percent:number;memory_bytes:number;memory_limit:number;network_rx_bytes:number;network_tx_bytes:number};
type Database={id:number;name:string;username:string;host:string;port:number;created_at:string};
type Node={id:number;name:string;fqdn:string;scheme:string;daemon_port:number;sftp_port:number;memory_mb:number;disk_mb:number;location?:string;servers_count?:number};
type NodeConfig={token:string;panel_url:string;listen:string;sftp_port:number;data:string;backups:string;install_command:string};
type Tab='console'|'files'|'databases'|'schedules'|'backups'|'network'|'startup'|'users'|'settings'|'nodes';
const fmt=(n:number)=>n>1024**3?(n/1024**3).toFixed(2)+' GB':(n/1024**2).toFixed(1)+' MB';
const TOKEN_KEY='nodexa_panel_token';
const savedToken=localStorage.getItem(TOKEN_KEY);
if(savedToken) axios.defaults.headers.common.Authorization=`Bearer ${savedToken}`;

export default function App(){
 const [me,setMe]=useState<User|null>(null),[authReady,setAuthReady]=useState(false),[servers,setServers]=useState<Server[]>([]),[selected,setSelected]=useState<Server|null>(null);
 const [stats,setStats]=useState<Stats|null>(null),[logs,setLogs]=useState(''),[cmd,setCmd]=useState(''),[tab,setTab]=useState<Tab>('console');
 const [databases,setDatabases]=useState<Database[]>([]),[dbName,setDbName]=useState(''),[newPassword,setNewPassword]=useState<string|null>(null);
 const [nodes,setNodes]=useState<Node[]>([]),[nodeConfig,setNodeConfig]=useState<NodeConfig|null>(null),[showNodeForm,setShowNodeForm]=useState(false);
 const [nodeForm,setNodeForm]=useState({name:'',fqdn:'',scheme:'https',daemon_port:8080,sftp_port:2022,memory_mb:64000,disk_mb:500000,location:'Denmark'});

 const loadServers=()=>axios.get('/api/servers').then(r=>{const s=r.data.data??r.data;setServers(s);setSelected(current=>current??s[0]??null);});
 const bootstrap=()=>axios.get('/api/me').then(r=>{setMe(r.data);return loadServers();}).catch(()=>{localStorage.removeItem(TOKEN_KEY);delete axios.defaults.headers.common.Authorization;setMe(null);}).finally(()=>setAuthReady(true));
 useEffect(()=>{bootstrap();},[]);

 useEffect(()=>{
  if(!selected||tab!=='console'||!me)return;
  const tick=()=>{axios.get(`/api/servers/${selected.id}/stats`).then(r=>setStats(r.data)).catch(()=>{});axios.get(`/api/servers/${selected.id}/logs?tail=150`).then(r=>setLogs(r.data)).catch(()=>{});};
  tick();const i=setInterval(tick,3000);return()=>clearInterval(i);
 },[selected,tab,me]);
 useEffect(()=>{if(selected&&tab==='databases'&&me)loadDatabases();},[selected,tab,me]);
 useEffect(()=>{if(me?.is_admin&&tab==='nodes')loadNodes();},[me,tab]);

 const login=(email:string,password:string)=>axios.post('/api/login',{email,password}).then(r=>{localStorage.setItem(TOKEN_KEY,r.data.token);axios.defaults.headers.common.Authorization=`Bearer ${r.data.token}`;setMe(r.data.user);setAuthReady(true);return loadServers();});
 const logout=()=>axios.post('/api/logout').catch(()=>{}).finally(()=>{localStorage.removeItem(TOKEN_KEY);delete axios.defaults.headers.common.Authorization;setMe(null);setServers([]);setSelected(null);setStats(null);setLogs('');setTab('console');});
 const loadDatabases=()=>selected&&axios.get(`/api/servers/${selected.id}/databases`).then(r=>setDatabases(r.data));
 const loadNodes=()=>axios.get('/api/nodes').then(r=>setNodes(r.data.data??r.data));
 const power=(signal:string)=>selected&&axios.post(`/api/servers/${selected.id}/power`,{signal}).then(()=>setTimeout(()=>setSelected({...selected,status:signal==='start'?'running':signal==='stop'?'offline':selected.status}),500));
 const send=()=>{if(!selected||!cmd.trim())return;axios.post(`/api/servers/${selected.id}/command`,{command:cmd}).then(()=>setCmd(''));};
 const createDatabase=()=>{if(!selected||!dbName.trim())return;setNewPassword(null);axios.post(`/api/servers/${selected.id}/databases`,{name:dbName.trim()}).then(r=>{setNewPassword(r.data.password);setDbName('');loadDatabases();});};
 const openDatabase=(db:Database)=>{if(!selected)return;const popup=window.open('about:blank','_blank');axios.post(`/api/servers/${selected.id}/databases/${db.id}/open`).then(r=>{if(popup){popup.opener=null;popup.location.href=r.data.url;}else{window.location.href=r.data.url;}}).catch(()=>popup?.close());};
 const deleteDatabase=(db:Database)=>{if(!selected||!confirm(`Delete ${db.name}? This cannot be undone.`))return;axios.delete(`/api/servers/${selected.id}/databases/${db.id}`).then(loadDatabases);};
 const createNode=()=>axios.post('/api/nodes',nodeForm).then(r=>{setNodeConfig(r.data.configuration);setShowNodeForm(false);loadNodes();});
 const configFor=(node:Node)=>axios.get(`/api/nodes/${node.id}/configuration`).then(r=>setNodeConfig(r.data));

 if(!authReady)return <div className="auth-screen"><div className="auth-card"><div className="brand auth-brand">NOD<span>EXA</span></div><p>Loading control panel…</p></div></div>;
 if(!me)return <LoginPage login={login}/>;

 const serverTabs:Tab[]=['console','files','databases','schedules','backups','network','startup','users','settings'];
 return <div className="shell">
  <aside>
   <div className="brand">NOD<span>EXA</span></div>
   <nav>
    <button className={tab!=='nodes'?'active':''} onClick={()=>setTab('console')}>{me.is_admin?'Servers':'My Servers'}</button>
    {me.is_admin&&<><div className="nav-label">ADMINISTRATION</div><button className={tab==='nodes'?'active':''} onClick={()=>setTab('nodes')}>Nodes</button><button>Allocations</button><button>Users</button></>}
   </nav>
   <div className="account"><strong>{me.name}</strong><small>{me.is_admin?'Administrator':'Customer'}</small><button className="logout" onClick={logout}>Log out</button></div>
   <div className="version">Nodexa v0.5</div>
  </aside>
  <main>
   {tab==='nodes'&&me.is_admin?<NodesPage nodes={nodes} showForm={showNodeForm} setShowForm={setShowNodeForm} form={nodeForm} setForm={setNodeForm} createNode={createNode} configFor={configFor} config={nodeConfig} closeConfig={()=>setNodeConfig(null)}/>:<>
    <header><div><small>NODEXA · {selected?.identifier??'SERVER'}</small><h1>{selected?.name??(me.is_admin?'Servers':'My Servers')}</h1></div>{selected&&<div className={'status '+(stats?.state==='running'?'online':'offline')}>{stats?.state??selected.status??'offline'}</div>}</header>
    <section className="server-grid">
     <div className="server-list card"><h3>{me.is_admin?'Servers':'My Servers'}</h3>{servers.map(s=><button key={s.id} className={selected?.id===s.id?'active':''} onClick={()=>{setSelected(s);setTab('console')}}><strong>{s.name}</strong><small>{s.identifier??'—'} · {s.memory_mb} MB</small></button>)}{!servers.length&&<small>No servers assigned.</small>}</div>
     <div className="content">
      {selected&&<div className="tabs">{serverTabs.map(t=><button key={t} className={tab===t?'active':''} onClick={()=>setTab(t)}>{t[0].toUpperCase()+t.slice(1)}</button>)}</div>}
      {!selected&&<div className="card placeholder"><h2>No server selected</h2><p>Once a server is assigned to this account it will appear here.</p></div>}
      {selected&&tab==='console'&&<ConsolePage stats={stats} logs={logs} cmd={cmd} setCmd={setCmd} send={send} power={power}/>} 
      {selected&&tab==='databases'&&<DatabasePage server={selected} databases={databases} dbName={dbName} setDbName={setDbName} createDatabase={createDatabase} openDatabase={openDatabase} deleteDatabase={deleteDatabase} password={newPassword} clearPassword={()=>setNewPassword(null)}/>} 
      {selected&&tab!=='console'&&tab!=='databases'&&<div className="card placeholder"><h2>{tab[0].toUpperCase()+tab.slice(1)}</h2><p>This module is part of the Nodexa server workspace.</p></div>}
     </div>
    </section>
   </>}
  </main>
 </div>
}

function LoginPage({login}:{login:(email:string,password:string)=>Promise<any>}){
 const [email,setEmail]=useState(''),[password,setPassword]=useState(''),[error,setError]=useState(''),[busy,setBusy]=useState(false);
 const submit=(e:React.FormEvent)=>{e.preventDefault();setBusy(true);setError('');login(email,password).catch(err=>setError(err?.response?.data?.message??'Login failed.')).finally(()=>setBusy(false));};
 return <div className="auth-screen"><form className="auth-card" onSubmit={submit}><div className="brand auth-brand">NOD<span>EXA</span></div><h1>Welcome back</h1><p>Sign in to manage your game servers.</p><label>Email<input type="email" autoComplete="username" required value={email} onChange={e=>setEmail(e.target.value)}/></label><label>Password<input type="password" autoComplete="current-password" required value={password} onChange={e=>setPassword(e.target.value)}/></label>{error&&<div className="auth-error">{error}</div>}<button className="primary auth-submit" disabled={busy}>{busy?'Signing in…':'Sign in'}</button></form></div>
}

function ConsolePage({stats,logs,cmd,setCmd,send,power}:{stats:Stats|null;logs:string;cmd:string;setCmd:(v:string)=>void;send:()=>void;power:(v:string)=>void}){
 return <><div className="metrics"><div className="metric"><small>CPU</small><strong>{stats?stats.cpu_percent.toFixed(1):'0'}%</strong></div><div className="metric"><small>MEMORY</small><strong>{stats?fmt(stats.memory_bytes):'0 MB'}</strong></div><div className="metric"><small>NETWORK RX</small><strong>{stats?fmt(stats.network_rx_bytes):'0 MB'}</strong></div><div className="metric"><small>NETWORK TX</small><strong>{stats?fmt(stats.network_tx_bytes):'0 MB'}</strong></div></div><div className="power"><button onClick={()=>power('start')}>Start</button><button onClick={()=>power('restart')}>Restart</button><button onClick={()=>power('stop')}>Stop</button><button className="danger" onClick={()=>power('kill')}>Kill</button></div><div className="console card"><div className="console-title">Live Console</div><pre>{logs||'No console output yet.'}</pre><div className="command"><span>&gt;</span><input value={cmd} onChange={e=>setCmd(e.target.value)} onKeyDown={e=>e.key==='Enter'&&send()} placeholder="Type a server command..."/><button onClick={send}>Send</button></div></div></>
}

function DatabasePage({server,databases,dbName,setDbName,createDatabase,openDatabase,deleteDatabase,password,clearPassword}:{server:Server;databases:Database[];dbName:string;setDbName:(v:string)=>void;createDatabase:()=>void;openDatabase:(d:Database)=>void;deleteDatabase:(d:Database)=>void;password:string|null;clearPassword:()=>void}){
 return <div className="database-page"><div className="page-heading"><div><h2>Databases</h2><p>Databases are isolated to <strong>{server.identifier}</strong>. Other servers cannot access them.</p></div><div className="create-inline"><span>{server.identifier}_</span><input value={dbName} onChange={e=>setDbName(e.target.value.replace(/[^A-Za-z0-9_-]/g,''))} placeholder="MC" onKeyDown={e=>e.key==='Enter'&&createDatabase()}/><button onClick={createDatabase}>Create Database</button></div></div>{password&&<div className="secret-box"><div><strong>Database created</strong><p>Save this generated password securely.</p><code>{password}</code></div><button onClick={clearPassword}>Close</button></div>}<div className="card table-card"><table><thead><tr><th>Database</th><th>Username</th><th>Host</th><th>Created</th><th></th></tr></thead><tbody>{databases.map(db=><tr key={db.id}><td><strong>{db.name}</strong></td><td><code>{db.username}</code></td><td>{db.host}:{db.port}</td><td>{new Date(db.created_at).toLocaleDateString()}</td><td className="actions"><button className="primary" onClick={()=>openDatabase(db)}>Open phpMyAdmin</button><button onClick={()=>deleteDatabase(db)}>Delete</button></td></tr>)}{!databases.length&&<tr><td colSpan={5} className="empty">No databases yet.</td></tr>}</tbody></table></div></div>
}

function NodesPage({nodes,showForm,setShowForm,form,setForm,createNode,configFor,config,closeConfig}:{nodes:Node[];showForm:boolean;setShowForm:(v:boolean)=>void;form:any;setForm:(v:any)=>void;createNode:()=>void;configFor:(n:Node)=>void;config:NodeConfig|null;closeConfig:()=>void}){
 return <><header><div><small>ADMINISTRATION</small><h1>Nodes</h1></div><button className="primary big" onClick={()=>setShowForm(true)}>Create Node</button></header><div className="card table-card"><table><thead><tr><th>Name</th><th>Location</th><th>FQDN</th><th>Agent</th><th>Resources</th><th>Servers</th><th></th></tr></thead><tbody>{nodes.map(n=><tr key={n.id}><td><strong>{n.name}</strong></td><td>{n.location??'—'}</td><td>{n.fqdn}</td><td>{n.scheme} · {n.daemon_port}</td><td>{Math.round(n.memory_mb/1024)} GB RAM · {Math.round(n.disk_mb/1024)} GB</td><td>{n.servers_count??0}</td><td><button onClick={()=>configFor(n)}>Configuration</button></td></tr>)}</tbody></table></div>{showForm&&<div className="modal"><div className="modal-card"><div className="modal-title"><h2>Create Node</h2><button onClick={()=>setShowForm(false)}>×</button></div><div className="form-grid"><label>Name<input value={form.name} onChange={e=>setForm({...form,name:e.target.value})}/></label><label>Location<input value={form.location} onChange={e=>setForm({...form,location:e.target.value})}/></label><label className="wide">FQDN<input placeholder="node01.example.com" value={form.fqdn} onChange={e=>setForm({...form,fqdn:e.target.value})}/></label><label>Agent Port<input type="number" value={form.daemon_port} onChange={e=>setForm({...form,daemon_port:+e.target.value})}/></label><label>SFTP Port<input type="number" value={form.sftp_port} onChange={e=>setForm({...form,sftp_port:+e.target.value})}/></label><label>Memory MB<input type="number" value={form.memory_mb} onChange={e=>setForm({...form,memory_mb:+e.target.value})}/></label><label>Disk MB<input type="number" value={form.disk_mb} onChange={e=>setForm({...form,disk_mb:+e.target.value})}/></label></div><div className="modal-actions"><button onClick={()=>setShowForm(false)}>Cancel</button><button className="primary" onClick={createNode}>Create Node</button></div></div></div>}{config&&<div className="modal"><div className="modal-card config-modal"><div className="modal-title"><h2>Node Configuration</h2><button onClick={closeConfig}>×</button></div><p>Run this command as root on the machine that should become this Nodexa Node:</p><pre className="install-command">{config.install_command}</pre><div className="config-grid"><div><small>Panel</small><strong>{config.panel_url}</strong></div><div><small>Listen</small><strong>{config.listen}</strong></div><div><small>SFTP</small><strong>{config.sftp_port}</strong></div><div><small>Data</small><strong>{config.data}</strong></div></div><div className="warning">The node token grants Agent access. Do not share it publicly.</div></div></div>}</>
}
