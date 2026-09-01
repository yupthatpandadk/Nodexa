import React, { useEffect, useState } from 'react';
import axios from 'axios';

type User = { id: number; name: string; email: string; is_admin: boolean };
type Server = { id: string; uuid: string; owner_id: number; server_number?: number; identifier?: string; name: string; status: string; memory_mb: number; disk_mb: number; cpu_limit: number; access_permissions?: string[] | string | null };
type Stats = { state: string; cpu_percent: number; memory_bytes: number; memory_limit: number; network_rx_bytes: number; network_tx_bytes: number };
type Database = { id: number; name: string; username: string; host: string; port: number; created_at: string };
type Node = { id: number; name: string; fqdn: string; scheme: string; daemon_port: number; sftp_port: number; memory_mb: number; disk_mb: number; location?: string; servers_count?: number };
type NodeConfig = { token: string; panel_url: string; listen: string; sftp_port: number; data: string; backups: string; install_command: string };
type Subuser = { id: number; user_id: number; permissions: string[] | string | null; user: { id: number; name: string; email: string } };
type Tab = 'console' | 'files' | 'databases' | 'schedules' | 'backups' | 'network' | 'startup' | 'users' | 'settings' | 'nodes';

const TOKEN_KEY = 'nodexa_panel_token';
const savedToken = localStorage.getItem(TOKEN_KEY);
if (savedToken) axios.defaults.headers.common.Authorization = `Bearer ${savedToken}`;

const fmt = (n: number) => n > 1024 ** 3 ? `${(n / 1024 ** 3).toFixed(2)} GB` : `${(n / 1024 ** 2).toFixed(1)} MB`;

function toArray<T>(value: any): T[] {
  if (Array.isArray(value)) return value as T[];
  if (!value || typeof value !== 'object') return [];
  if (Array.isArray(value.data)) return value.data as T[];
  if (Array.isArray(value.items)) return value.items as T[];
  if (Array.isArray(value.results)) return value.results as T[];
  if (value.data && typeof value.data === 'object') return toArray<T>(value.data);
  return [];
}

function toStringArray(value: unknown): string[] {
  if (Array.isArray(value)) return value.filter((v): v is string => typeof v === 'string');
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) return [];
    try {
      const parsed = JSON.parse(trimmed);
      if (Array.isArray(parsed)) return parsed.filter((v): v is string => typeof v === 'string');
    } catch {}
    return trimmed.split(',').map(v => v.trim()).filter(Boolean);
  }
  return [];
}

function permissionsFor(server: Server | null): string[] {
  return server ? toStringArray(server.access_permissions) : [];
}

const has = (server: Server | null, permission: string) => {
  const permissions = permissionsFor(server);
  return permissions.includes('*') || permissions.includes(permission);
};

const tabsFor = (server: Server | null, me: User | null): Tab[] => {
  if (!server || !me) return [];
  const permissions = permissionsFor(server);
  if (permissions.includes('*')) return ['console', 'files', 'databases', 'schedules', 'backups', 'network', 'users', 'settings'];

  const out: Tab[] = [];
  if (permissions.some(x => x.startsWith('console.') || x.startsWith('power.'))) out.push('console');
  if (permissions.some(x => x.startsWith('files.'))) out.push('files');
  if (permissions.some(x => x.startsWith('database.'))) out.push('databases');
  if (permissions.some(x => x.startsWith('schedule.'))) out.push('schedules');
  if (permissions.some(x => x.startsWith('backups.'))) out.push('backups');
  if (permissions.includes('settings.read')) out.push('settings');
  return out;
};

export default function App() {
  const [me, setMe] = useState<User | null>(null);
  const [authReady, setAuthReady] = useState(false);
  const [servers, setServers] = useState<Server[]>([]);
  const [selected, setSelected] = useState<Server | null>(null);
  const [stats, setStats] = useState<Stats | null>(null);
  const [logs, setLogs] = useState('');
  const [cmd, setCmd] = useState('');
  const [tab, setTab] = useState<Tab>('console');
  const [databases, setDatabases] = useState<Database[]>([]);
  const [dbName, setDbName] = useState('');
  const [newPassword, setNewPassword] = useState<string | null>(null);
  const [pmaUrl, setPmaUrl] = useState<string | null>(null);
  const [serverUsers, setServerUsers] = useState<Subuser[]>([]);
  const [nodes, setNodes] = useState<Node[]>([]);
  const [nodeConfig, setNodeConfig] = useState<NodeConfig | null>(null);
  const [showNodeForm, setShowNodeForm] = useState(false);
  const [nodeForm, setNodeForm] = useState({ name: '', fqdn: '', scheme: 'https', daemon_port: 8080, sftp_port: 2022, memory_mb: 64000, disk_mb: 500000, location: 'Denmark' });

  const choose = (server: Server, user = me) => {
    setSelected(server);
    setPmaUrl(null);
    setTab(tabsFor(server, user)[0] ?? 'settings');
  };

  const loadServers = (user = me) => axios.get('/api/servers').then(response => {
    const list = toArray<Server>(response.data);
    setServers(list);
    if (list.length > 0) choose(list[0], user);
    else setSelected(null);
  });

  const bootstrap = () => axios.get('/api/me')
    .then(response => {
      setMe(response.data);
      return loadServers(response.data);
    })
    .catch(() => {
      localStorage.removeItem(TOKEN_KEY);
      delete axios.defaults.headers.common.Authorization;
      setMe(null);
      setServers([]);
      setSelected(null);
    })
    .finally(() => setAuthReady(true));

  useEffect(() => { bootstrap(); }, []);

  useEffect(() => {
    if (!selected || tab !== 'console' || !me || !has(selected, 'console.read')) return;
    const tick = () => {
      axios.get(`/api/servers/${selected.id}/stats`).then(r => setStats(r.data)).catch(() => {});
      axios.get(`/api/servers/${selected.id}/logs?tail=150`).then(r => setLogs(typeof r.data === 'string' ? r.data : JSON.stringify(r.data ?? '', null, 2))).catch(() => {});
    };
    tick();
    const timer = setInterval(tick, 3000);
    return () => clearInterval(timer);
  }, [selected, tab, me]);

  useEffect(() => { if (selected && tab === 'databases' && me && has(selected, 'database.read')) loadDatabases(); }, [selected, tab, me]);
  useEffect(() => { if (selected && tab === 'users' && me && (me.is_admin || selected.owner_id === me.id)) loadServerUsers(); }, [selected, tab, me]);
  useEffect(() => { if (me?.is_admin && tab === 'nodes') loadNodes(); }, [me, tab]);

  const login = (email: string, password: string) => axios.post('/api/login', { email, password }).then(response => {
    localStorage.setItem(TOKEN_KEY, response.data.token);
    axios.defaults.headers.common.Authorization = `Bearer ${response.data.token}`;
    setMe(response.data.user);
    setAuthReady(true);
    return loadServers(response.data.user);
  });

  const logout = () => axios.post('/api/logout').catch(() => {}).finally(() => {
    localStorage.removeItem(TOKEN_KEY);
    delete axios.defaults.headers.common.Authorization;
    setMe(null);
    setServers([]);
    setSelected(null);
    setPmaUrl(null);
    setTab('console');
  });

  const loadDatabases = () => selected && axios.get(`/api/servers/${selected.id}/databases`).then(r => setDatabases(toArray<Database>(r.data)));
  const loadServerUsers = () => selected && axios.get(`/api/servers/${selected.id}/users`).then(r => setServerUsers(toArray<Subuser>(r.data)));
  const loadNodes = () => axios.get('/api/nodes').then(r => setNodes(toArray<Node>(r.data)));
  const addServerUser = (email: string, permissions: string[]) => selected && axios.post(`/api/servers/${selected.id}/users`, { email, permissions }).then(loadServerUsers);
  const updateServerUser = (entry: Subuser, permissions: string[]) => selected && axios.put(`/api/servers/${selected.id}/users/${entry.id}`, { permissions }).then(loadServerUsers);
  const removeServerUser = (entry: Subuser) => selected && axios.delete(`/api/servers/${selected.id}/users/${entry.id}`).then(loadServerUsers);
  const power = (signal: string) => selected && axios.post(`/api/servers/${selected.id}/power`, { signal });
  const send = () => { if (selected && cmd.trim()) axios.post(`/api/servers/${selected.id}/command`, { command: cmd }).then(() => setCmd('')); };
  const createDatabase = () => { if (selected && dbName.trim()) { setNewPassword(null); axios.post(`/api/servers/${selected.id}/databases`, { name: dbName.trim() }).then(r => { setNewPassword(r.data.password); setDbName(''); loadDatabases(); }); } };
  const openDatabase = (db: Database) => selected && axios.post(`/api/servers/${selected.id}/databases/${db.id}/open`).then(r => setPmaUrl(r.data.url));
  const deleteDatabase = (db: Database) => { if (selected && confirm(`Delete ${db.name}? This cannot be undone.`)) axios.delete(`/api/servers/${selected.id}/databases/${db.id}`).then(loadDatabases); };
  const createNode = () => axios.post('/api/nodes', nodeForm).then(r => { setNodeConfig(r.data.configuration); setShowNodeForm(false); loadNodes(); });
  const configFor = (node: Node) => axios.get(`/api/nodes/${node.id}/configuration`).then(r => setNodeConfig(r.data));

  if (!authReady) return <div className="auth-screen"><div className="auth-card"><div className="brand auth-brand">NOD<span>EXA</span></div><p>Loading control panel…</p></div></div>;
  if (!me) return <LoginPage login={login} />;

  const safeServers = Array.isArray(servers) ? servers : [];
  const serverTabs = tabsFor(selected, me);

  return <div className="shell">
    <aside>
      <div className="brand">NOD<span>EXA</span></div>
      <nav>
        <button className={tab !== 'nodes' ? 'active' : ''} onClick={() => selected && choose(selected)}>{me.is_admin ? 'Servers' : 'My Servers'}</button>
        {me.is_admin && <><div className="nav-label">ADMINISTRATION</div><button className={tab === 'nodes' ? 'active' : ''} onClick={() => setTab('nodes')}>Nodes</button></>}
      </nav>
      <div className="account"><strong>{me.name}</strong><small>{me.is_admin ? 'Administrator' : 'Customer'}</small><button className="logout" onClick={logout}>Log out</button></div>
      <div className="version">Nodexa v0.9.8</div>
    </aside>
    <main>
      {tab === 'nodes' && me.is_admin
        ? <NodesPage nodes={nodes} showForm={showNodeForm} setShowForm={setShowNodeForm} form={nodeForm} setForm={setNodeForm} createNode={createNode} configFor={configFor} config={nodeConfig} closeConfig={() => setNodeConfig(null)} />
        : <>
          <header><div><small>NODEXA · {selected?.identifier ?? 'SERVER'}</small><h1>{selected?.name ?? (me.is_admin ? 'Servers' : 'My Servers')}</h1></div>{selected && <div className={'status ' + (stats?.state === 'running' ? 'online' : 'offline')}>{stats?.state ?? selected.status}</div>}</header>
          <section className="server-grid">
            <div className="server-list card"><h3>{me.is_admin ? 'Servers' : 'My Servers'}</h3>{safeServers.map(server => <button key={server.id} className={selected?.id === server.id ? 'active' : ''} onClick={() => choose(server)}><strong>{server.name}</strong><small>{server.identifier ?? '—'} · {server.memory_mb} MB</small></button>)}{safeServers.length === 0 && <small>No servers assigned.</small>}</div>
            <div className="content">
              {selected && <div className="tabs">{serverTabs.map(currentTab => <button key={currentTab} className={tab === currentTab ? 'active' : ''} onClick={() => { setPmaUrl(null); setTab(currentTab); }}>{currentTab[0].toUpperCase() + currentTab.slice(1)}</button>)}</div>}
              {!selected && <div className="card placeholder"><h2>No server selected</h2></div>}
              {selected && tab === 'console' && <ConsolePage stats={stats} logs={logs} cmd={cmd} setCmd={setCmd} send={send} power={power} canRead={has(selected, 'console.read')} canCommand={has(selected, 'console.command')} permissions={permissionsFor(selected)} />}
              {selected && tab === 'databases' && <DatabasePage server={selected} databases={databases} dbName={dbName} setDbName={setDbName} createDatabase={createDatabase} openDatabase={openDatabase} deleteDatabase={deleteDatabase} password={newPassword} clearPassword={() => setNewPassword(null)} pmaUrl={pmaUrl} closePma={() => setPmaUrl(null)} canCreate={has(selected, 'database.create')} canDelete={has(selected, 'database.delete')} />}
              {selected && tab === 'users' && (me.is_admin || selected.owner_id === me.id) && <UsersPage users={serverUsers} add={addServerUser} update={updateServerUser} remove={removeServerUser} />}
              {selected && !['console', 'databases', 'users'].includes(tab) && <div className="card placeholder"><h2>{tab[0].toUpperCase() + tab.slice(1)}</h2><p>This module is part of the Nodexa server workspace.</p></div>}
            </div>
          </section>
        </>}
    </main>
  </div>;
}

function LoginPage({ login }: { login: (email: string, password: string) => Promise<any> }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  return <div className="auth-screen"><form className="auth-card" onSubmit={event => { event.preventDefault(); setBusy(true); setError(''); login(email, password).catch(x => setError(x?.response?.data?.message ?? 'Login failed.')).finally(() => setBusy(false)); }}><div className="brand auth-brand">NOD<span>EXA</span></div><h1>Welcome back</h1><p>Sign in to manage your game servers.</p><label>Email<input type="email" required value={email} onChange={e => setEmail(e.target.value)} /></label><label>Password<input type="password" required value={password} onChange={e => setPassword(e.target.value)} /></label>{error && <div className="auth-error">{error}</div>}<button className="primary auth-submit" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button></form></div>;
}

function ConsolePage({ stats, logs, cmd, setCmd, send, power, canRead, canCommand, permissions }: { stats: Stats | null; logs: string; cmd: string; setCmd: (value: string) => void; send: () => void; power: (value: string) => void; canRead: boolean; canCommand: boolean; permissions: string[] }) {
  const safePermissions = Array.isArray(permissions) ? permissions : [];
  const all = safePermissions.includes('*');
  return <>{canRead && <div className="metrics"><div className="metric"><small>CPU</small><strong>{stats ? stats.cpu_percent.toFixed(1) : '0'}%</strong></div><div className="metric"><small>MEMORY</small><strong>{stats ? fmt(stats.memory_bytes) : '0 MB'}</strong></div></div>}<div className="power">{(all || safePermissions.includes('power.start')) && <button onClick={() => power('start')}>Start</button>}{(all || safePermissions.includes('power.restart')) && <button onClick={() => power('restart')}>Restart</button>}{(all || safePermissions.includes('power.stop')) && <button onClick={() => power('stop')}>Stop</button>}</div>{canRead && <div className="console card"><div className="console-title">Live Console</div><pre>{logs || 'No console output yet.'}</pre>{canCommand && <div className="command"><span>&gt;</span><input value={cmd} onChange={e => setCmd(e.target.value)} onKeyDown={e => e.key === 'Enter' && send()} /><button onClick={send}>Send</button></div>}</div>}</>;
}

function DatabasePage({ server, databases, dbName, setDbName, createDatabase, openDatabase, deleteDatabase, password, clearPassword, pmaUrl, closePma, canCreate, canDelete }: { server: Server; databases: Database[]; dbName: string; setDbName: (value: string) => void; createDatabase: () => void; openDatabase: (database: Database) => void; deleteDatabase: (database: Database) => void; password: string | null; clearPassword: () => void; pmaUrl: string | null; closePma: () => void; canCreate: boolean; canDelete: boolean }) {
  const [showPassword, setShowPassword] = useState(false);
  const safeDatabases = Array.isArray(databases) ? databases : [];
  if (pmaUrl) return <div className="pma-workspace card"><div className="pma-toolbar"><div><strong>Database Manager</strong><small>{server.identifier} · isolated database session</small></div><button onClick={closePma}>Back to Databases</button></div><iframe title="Nodexa Database Manager" src={pmaUrl} /></div>;
  return <div><div className="page-heading"><div><h2>Databases</h2><p>Databases are isolated to <strong>{server.identifier}</strong>.</p></div>{canCreate && <div className="create-inline"><span>{server.identifier}_</span><input value={dbName} onChange={e => setDbName(e.target.value.replace(/[^A-Za-z0-9_-]/g, ''))} placeholder="MC" /><button onClick={createDatabase}>Create Database</button></div>}</div>{password && <div className="secret-box"><div><strong>Database created</strong><code>{showPassword ? password : '************'}</code></div><div className="actions"><button onClick={() => setShowPassword(v => !v)}>{showPassword ? 'Skjul' : 'Vis'}</button><button onClick={() => { setShowPassword(false); clearPassword(); }}>Close</button></div></div>}<div className="card table-card"><table><thead><tr><th>Database</th><th>Username</th><th>Host</th><th></th></tr></thead><tbody>{safeDatabases.map(db => <tr key={db.id}><td><strong>{db.name}</strong></td><td><code>{db.username}</code></td><td>{db.host}:{db.port}</td><td className="actions"><button className="primary" onClick={() => openDatabase(db)}>Open</button>{canDelete && <button onClick={() => deleteDatabase(db)}>Delete</button>}</td></tr>)}{safeDatabases.length === 0 && <tr><td colSpan={4} className="empty">No databases yet.</td></tr>}</tbody></table></div></div>;
}

const dbPermissions = [['database.read', 'Open database'], ['database.create', 'Create databases'], ['database.credentials', 'View credentials'], ['database.delete', 'Delete databases']] as const;

function UsersPage({ users, add, update, remove }: { users: Subuser[]; add: (email: string, permissions: string[]) => any; update: (user: Subuser, permissions: string[]) => any; remove: (user: Subuser) => any }) {
  const [email, setEmail] = useState('');
  const [permissions, setPermissions] = useState<string[]>(['database.read']);
  const safeUsers = Array.isArray(users) ? users : [];
  const toggle = (permission: string) => setPermissions(value => value.includes(permission) ? value.filter(x => x !== permission) : [...value, permission]);
  return <div><div className="page-heading"><div><h2>Server Users</h2><p>Give another Nodexa account only the permissions it needs.</p></div></div><div className="card permission-create"><input type="email" placeholder="user@example.com" value={email} onChange={e => setEmail(e.target.value)} /><div className="permission-options">{dbPermissions.map(([key, label]) => <label key={key}><input type="checkbox" checked={permissions.includes(key)} onChange={() => toggle(key)} />{label}</label>)}</div><button className="primary" onClick={() => { if (email.trim()) Promise.resolve(add(email.trim(), permissions)).then(() => setEmail('')); }}>Add User</button></div><div className="card table-card"><table><tbody>{safeUsers.map(user => { const userPermissions = toStringArray(user.permissions); return <tr key={user.id}><td><strong>{user.user?.name ?? 'Unknown user'}</strong><br /><small>{user.user?.email ?? ''}</small></td><td><div className="permission-options compact">{dbPermissions.map(([key, label]) => <label key={key}><input type="checkbox" checked={userPermissions.includes(key)} onChange={() => update(user, userPermissions.includes(key) ? userPermissions.filter(x => x !== key) : [...userPermissions, key])} />{label}</label>)}</div></td><td><button onClick={() => confirm(`Remove ${user.user?.email ?? 'this user'}?`) && remove(user)}>Remove</button></td></tr>; })}</tbody></table></div></div>;
}

function NodesPage({ nodes, showForm, setShowForm, form, setForm, createNode, configFor, config, closeConfig }: { nodes: Node[]; showForm: boolean; setShowForm: (value: boolean) => void; form: any; setForm: (value: any) => void; createNode: () => void; configFor: (node: Node) => void; config: NodeConfig | null; closeConfig: () => void }) {
  const [showConfigSecret, setShowConfigSecret] = useState(false);
  const safeNodes = Array.isArray(nodes) ? nodes : [];
  return <><header><div><small>ADMINISTRATION</small><h1>Nodes</h1></div><button className="primary big" onClick={() => setShowForm(true)}>Create Node</button></header><div className="card table-card"><table><tbody>{safeNodes.map(node => <tr key={node.id}><td><strong>{node.name}</strong><br /><small>{node.location}</small></td><td>{node.fqdn}:{node.daemon_port}</td><td>{Math.round(node.memory_mb / 1024)} GB RAM</td><td><button onClick={() => { setShowConfigSecret(false); configFor(node); }}>Configuration</button></td></tr>)}</tbody></table></div>{showForm && <div className="modal"><div className="modal-card"><div className="modal-title"><h2>Create Node</h2><button onClick={() => setShowForm(false)}>×</button></div><div className="form-grid"><label>Name<input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} /></label><label>Location<input value={form.location} onChange={e => setForm({ ...form, location: e.target.value })} /></label><label className="wide">FQDN<input value={form.fqdn} onChange={e => setForm({ ...form, fqdn: e.target.value })} /></label><label>Agent Port<input type="number" value={form.daemon_port} onChange={e => setForm({ ...form, daemon_port: +e.target.value })} /></label><label>SFTP Port<input type="number" value={form.sftp_port} onChange={e => setForm({ ...form, sftp_port: +e.target.value })} /></label><label>Memory MB<input type="number" value={form.memory_mb} onChange={e => setForm({ ...form, memory_mb: +e.target.value })} /></label><label>Disk MB<input type="number" value={form.disk_mb} onChange={e => setForm({ ...form, disk_mb: +e.target.value })} /></label></div><div className="modal-actions"><button onClick={() => setShowForm(false)}>Cancel</button><button className="primary" onClick={createNode}>Create Node</button></div></div></div>}{config && <div className="modal"><div className="modal-card"><div className="modal-title"><h2>Node Configuration</h2><button onClick={() => { setShowConfigSecret(false); closeConfig(); }}>×</button></div><p>Run this command as root on the Node:</p><pre className="install-command">{showConfigSecret ? config.install_command : '************'}</pre><div className="modal-actions"><button onClick={() => setShowConfigSecret(v => !v)}>{showConfigSecret ? 'Skjul' : 'Vis'}</button></div><div className="warning">Keep the Node token private.</div></div></div>}</>;
}
