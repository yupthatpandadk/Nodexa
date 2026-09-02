import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { BackupsPage, FilesPage, SchedulesPage } from './RuntimeModules';
import { NetworkPage, SettingsPage as ServerSettingsPage, StartupPage } from './ServerConfigurationModules';

type User = { id: number; name: string; email: string; is_admin: boolean };
type Server = { id: string; uuid: string; owner_id: number; server_number?: number; identifier?: string; name: string; status: string; memory_mb: number; disk_mb: number; cpu_limit: number; access_permissions?: string[] | string | null };
type Stats = { state: string; cpu_percent: number; memory_bytes: number; memory_limit: number; network_rx_bytes: number; network_tx_bytes: number };
type Database = { id: number; name: string; username: string; host: string; port: number; created_at: string };
type Node = { id: number; name: string; fqdn: string; scheme: string; daemon_port: number; sftp_port: number; memory_mb: number; disk_mb: number; location?: string; servers_count?: number; health_status?: string; health_latency_ms?: number | null; health_last_checked_at?: string | null; health_last_seen_at?: string | null; health_message?: string | null };
type NodeConfig = { token: string; panel_url: string; listen: string; sftp_port: number; data: string; backups: string; install_command: string };
type Subuser = { id: number; user_id: number; permissions: string[] | string | null; user: { id: number; name: string; email: string } };
type Tab = 'console' | 'files' | 'databases' | 'schedules' | 'backups' | 'network' | 'startup' | 'users' | 'settings';
type View = 'dashboard' | 'servers' | 'nodes';

type IconName = 'grid' | 'server' | 'node' | 'terminal' | 'folder' | 'database' | 'calendar' | 'backup' | 'users' | 'settings' | 'menu' | 'bell' | 'cpu' | 'memory' | 'disk' | 'network' | 'play' | 'restart' | 'stop' | 'chevron' | 'plus' | 'search' | 'shield' | 'activity' | 'logout';

const TOKEN_KEY = 'nodexa_panel_token';
const savedToken = localStorage.getItem(TOKEN_KEY);
if (savedToken) axios.defaults.headers.common.Authorization = `Bearer ${savedToken}`;

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
    const text = value.trim();
    if (!text) return [];
    try {
      const parsed = JSON.parse(text);
      if (Array.isArray(parsed)) return parsed.filter((v): v is string => typeof v === 'string');
    } catch {}
    return text.split(',').map(v => v.trim()).filter(Boolean);
  }
  return [];
}

const fmtBytes = (bytes: number) => {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 MB';
  return bytes >= 1024 ** 3 ? `${(bytes / 1024 ** 3).toFixed(2)} GB` : `${(bytes / 1024 ** 2).toFixed(1)} MB`;
};
const fmtMb = (mb: number) => mb >= 1024 ? `${(mb / 1024).toFixed(mb % 1024 === 0 ? 0 : 1)} GB` : `${mb} MB`;
const pct = (value: number, max: number) => max > 0 ? Math.min(100, Math.max(0, (value / max) * 100)) : 0;
const permissionsFor = (server: Server | null) => server ? toStringArray(server.access_permissions) : [];
const has = (server: Server | null, permission: string) => {
  const permissions = permissionsFor(server);
  return permissions.includes('*') || permissions.includes(permission);
};

const tabLabels: Record<Tab, string> = {
  console: 'Konsol', files: 'Filer', databases: 'Databaser', schedules: 'Planlægninger', backups: 'Backups', network: 'Netværk', startup: 'Startup', users: 'Brugere', settings: 'Indstillinger',
};
const tabIcons: Record<Tab, IconName> = {
  console: 'terminal', files: 'folder', databases: 'database', schedules: 'calendar', backups: 'backup', network: 'network', startup: 'play', users: 'users', settings: 'settings',
};

function tabsFor(server: Server | null, me: User | null): Tab[] {
  if (!server || !me) return [];
  const p = permissionsFor(server);
  if (p.includes('*')) {
    const full: Tab[] = ['console', 'files', 'databases', 'schedules', 'backups', 'network'];
    if (me.is_admin) full.push('startup');
    full.push('users', 'settings');
    return full;
  }
  const out: Tab[] = [];
  if (p.some(x => x.startsWith('console.') || x.startsWith('power.'))) out.push('console');
  if (p.some(x => x.startsWith('files.'))) out.push('files');
  if (p.some(x => x.startsWith('database.'))) out.push('databases');
  if (p.some(x => x.startsWith('schedule.'))) out.push('schedules');
  if (p.some(x => x.startsWith('backups.'))) out.push('backups');
  if (p.some(x => x.startsWith('allocation.'))) out.push('network');
  if (p.some(x => x.startsWith('settings.')) || p.includes('files.sftp')) out.push('settings');
  return out;
}

function Icon({ name, size = 18 }: { name: IconName; size?: number }) {
  const common = { width: size, height: size, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, 'aria-hidden': true };
  const paths: Record<IconName, React.ReactNode> = {
    grid: <><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></>,
    server: <><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01M11 7h6M11 17h6"/></>,
    node: <><circle cx="12" cy="5" r="2.2"/><circle cx="5" cy="18" r="2.2"/><circle cx="19" cy="18" r="2.2"/><path d="M12 7.2v4M10.7 11.2 6.3 16M13.3 11.2l4.4 4.8"/></>,
    terminal: <><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3M12.5 15H17"/></>,
    folder: <><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H9l2 2h7.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/></>,
    database: <><ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></>,
    calendar: <><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></>,
    backup: <><path d="M5 7v4h4M5.8 10.2A7 7 0 1 1 6 16"/><path d="M12 8v5l3 2"/></>,
    users: <><path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></>,
    settings: <><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21H10v-.1a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3V10h.1A1.7 1.7 0 0 0 4.7 8.9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3H14v.1A1.7 1.7 0 0 0 15.1 4.7a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.4.3.7.6.9 1 .2.4.3.8.3 1.1V14h-.1a1.7 1.7 0 0 0-1.1 1z"/></>,
    menu: <><path d="M4 6h16M4 12h16M4 18h16"/></>, bell: <><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></>,
    cpu: <><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 14h3M1 9h3M1 14h3M10 10h4v4h-4z"/></>,
    memory: <><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10v4M11 10v4M15 10v4M19 10v4M6 18v3M10 18v3M14 18v3M18 18v3"/></>,
    disk: <><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2"/><path d="M12 4v4M20 12h-4"/></>,
    network: <><path d="M5 12h14M12 5l7 7-7 7"/></>, play: <path d="m8 5 11 7-11 7z"/>, restart: <><path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/></>, stop: <rect x="6" y="6" width="12" height="12" rx="2"/>, chevron: <path d="m9 18 6-6-6-6"/>, plus: <path d="M12 5v14M5 12h14"/>, search: <><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></>, shield: <><path d="M12 3 5 6v5c0 4.5 2.8 8.2 7 10 4.2-1.8 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-4"/></>, activity: <path d="M3 12h4l2-6 4 12 2-6h6"/>, logout: <><path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-6"/></>,
  };
  return <svg {...common}>{paths[name]}</svg>;
}

export default function App() {
  const [me, setMe] = useState<User | null>(null);
  const [authReady, setAuthReady] = useState(false);
  const [servers, setServers] = useState<Server[]>([]);
  const [selected, setSelected] = useState<Server | null>(null);
  const [stats, setStats] = useState<Stats | null>(null);
  const [logs, setLogs] = useState('');
  const [cmd, setCmd] = useState('');
  const [tab, setTab] = useState<Tab>('console');
  const [view, setView] = useState<View>('dashboard');
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [databases, setDatabases] = useState<Database[]>([]);
  const [dbName, setDbName] = useState('');
  const [newPassword, setNewPassword] = useState<string | null>(null);
  const [pmaUrl, setPmaUrl] = useState<string | null>(null);
  const [serverUsers, setServerUsers] = useState<Subuser[]>([]);
  const [nodes, setNodes] = useState<Node[]>([]);
  const [nodeConfig, setNodeConfig] = useState<NodeConfig | null>(null);
  const [showNodeForm, setShowNodeForm] = useState(false);
  const [nodeForm, setNodeForm] = useState({ name: '', fqdn: '', scheme: 'https', daemon_port: 8080, sftp_port: 2022, memory_mb: 64000, disk_mb: 500000, location: 'Denmark' });

  const safeServers = Array.isArray(servers) ? servers : [];
  const onlineServers = safeServers.filter(s => ['online', 'running'].includes(String(s.status).toLowerCase())).length;
  const totalMemory = safeServers.reduce((sum, s) => sum + Number(s.memory_mb || 0), 0);
  const totalDisk = safeServers.reduce((sum, s) => sum + Number(s.disk_mb || 0), 0);

  const choose = (server: Server, user = me) => {
    setSelected(server);
    setPmaUrl(null);
    setView('servers');
    setTab(tabsFor(server, user)[0] ?? 'settings');
    setSidebarOpen(false);
  };

  const loadServers = (user = me) => axios.get('/api/servers').then(response => {
    const list = toArray<Server>(response.data);
    setServers(list);
    if (selected) {
      const refreshed = list.find(server => server.id === selected.id);
      if (refreshed) setSelected(refreshed);
    }
    return list;
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
  useEffect(() => {
    if (!me?.is_admin || view !== 'nodes') return;
    loadNodes();
    const timer = setInterval(loadNodes, 15000);
    return () => clearInterval(timer);
  }, [me, view]);

  const login = (email: string, password: string) => axios.post('/api/login', { email, password }).then(response => {
    localStorage.setItem(TOKEN_KEY, response.data.token);
    axios.defaults.headers.common.Authorization = `Bearer ${response.data.token}`;
    setMe(response.data.user);
    setAuthReady(true);
    setView('dashboard');
    return loadServers(response.data.user);
  });
  const logout = () => axios.post('/api/logout').catch(() => {}).finally(() => {
    localStorage.removeItem(TOKEN_KEY);
    delete axios.defaults.headers.common.Authorization;
    setMe(null); setServers([]); setSelected(null); setStats(null); setView('dashboard');
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
  const deleteDatabase = (db: Database) => { if (selected && confirm(`Slet ${db.name}? Dette kan ikke fortrydes.`)) axios.delete(`/api/servers/${selected.id}/databases/${db.id}`).then(loadDatabases); };
  const createNode = () => axios.post('/api/nodes', nodeForm).then(r => { setNodeConfig(r.data.configuration); setShowNodeForm(false); loadNodes(); });
  const configFor = (node: Node) => axios.get(`/api/nodes/${node.id}/configuration`).then(r => setNodeConfig(r.data));

  if (!authReady) return <LoadingScreen />;
  if (!me) return <LoginPage login={login} />;

  const pageTitle = view === 'dashboard' ? 'Oversigt' : view === 'nodes' ? 'Nodes' : selected?.name ?? 'Mine servere';
  const pageSubtitle = view === 'servers' && selected ? `${selected.identifier ?? 'SERVER'} · Game Server` : me.is_admin ? 'Nodexa Administration' : 'Nodexa Kundeportal';

  return <div className="app-shell">
    <div className={'sidebar-backdrop ' + (sidebarOpen ? 'show' : '')} onClick={() => setSidebarOpen(false)} />
    <aside className={'sidebar ' + (sidebarOpen ? 'open' : '')}>
      <div className="sidebar-brand"><div className="brand-mark">N</div><div><strong>NOD<span>EXA</span></strong><small>Hosting Platform</small></div></div>
      <nav className="side-nav">
        <div className="nav-section">KUNDEPORTAL</div>
        <NavButton icon="grid" label="Oversigt" active={view === 'dashboard'} onClick={() => { setView('dashboard'); setSelected(null); setSidebarOpen(false); }} />
        <NavButton icon="server" label={me.is_admin ? 'Servere' : 'Mine servere'} active={view === 'servers'} badge={safeServers.length || undefined} onClick={() => { setView('servers'); setSelected(null); setSidebarOpen(false); }} />
        {me.is_admin && <><div className="nav-section">ADMINISTRATION</div><NavButton icon="node" label="Nodes" active={view === 'nodes'} badge={nodes.length || undefined} onClick={() => { setView('nodes'); setSelected(null); setSidebarOpen(false); }} /></>}
        <div className="nav-section">SYSTEM</div>
        <button className="nav-item muted" type="button"><Icon name="activity"/><span>Activity</span><span className="soon">Snart</span></button>
      </nav>
      <div className="sidebar-footer">
        <div className="user-chip"><div className="avatar">{initials(me.name)}</div><div><strong>{me.name}</strong><small>{me.is_admin ? 'Administrator' : 'Kunde'}</small></div></div>
        <button className="logout-btn" onClick={logout} title="Log ud"><Icon name="logout"/></button>
      </div>
      <div className="build-info"><span className="build-dot"/> Nodexa v0.10.0</div>
    </aside>

    <div className="app-main">
      <header className="topbar">
        <button className="mobile-menu" onClick={() => setSidebarOpen(true)}><Icon name="menu" size={21}/></button>
        <div className="topbar-title"><small>{pageSubtitle}</small><strong>{pageTitle}</strong></div>
        <div className="topbar-actions"><button className="icon-btn"><Icon name="bell"/></button><div className="top-avatar">{initials(me.name)}</div></div>
      </header>

      <main className="page-wrap">
        {view === 'dashboard' && <DashboardPage me={me} servers={safeServers} onlineServers={onlineServers} totalMemory={totalMemory} totalDisk={totalDisk} choose={choose} openServers={() => setView('servers')} />}
        {view === 'servers' && !selected && <ServersPage servers={safeServers} choose={choose} me={me} />}
        {view === 'servers' && selected && <ServerWorkspace me={me} server={selected} stats={stats} tab={tab} setTab={value => { setPmaUrl(null); setTab(value); }} back={() => setSelected(null)} power={power} logs={logs} cmd={cmd} setCmd={setCmd} send={send} databases={databases} dbName={dbName} setDbName={setDbName} createDatabase={createDatabase} openDatabase={openDatabase} deleteDatabase={deleteDatabase} password={newPassword} clearPassword={() => setNewPassword(null)} pmaUrl={pmaUrl} closePma={() => setPmaUrl(null)} users={serverUsers} addUser={addServerUser} updateUser={updateServerUser} removeUser={removeServerUser} />}
        {view === 'nodes' && me.is_admin && <NodesPage nodes={nodes} showForm={showNodeForm} setShowForm={setShowNodeForm} form={nodeForm} setForm={setNodeForm} createNode={createNode} configFor={configFor} config={nodeConfig} closeConfig={() => setNodeConfig(null)} />}
      </main>
    </div>
  </div>;
}

function LoadingScreen() {
  return <div className="loading-screen"><div className="loading-logo"><div className="brand-mark large">N</div><strong>NOD<span>EXA</span></strong></div><div className="loading-line"><span/></div><small>Indlæser kontrolpanel…</small></div>;
}

function LoginPage({ login }: { login: (email: string, password: string) => Promise<any> }) {
  const [email, setEmail] = useState(''); const [password, setPassword] = useState(''); const [error, setError] = useState(''); const [busy, setBusy] = useState(false);
  return <div className="login-screen"><div className="login-glow one"/><div className="login-glow two"/><form className="login-card" onSubmit={event => { event.preventDefault(); setBusy(true); setError(''); login(email, password).catch(x => setError(x?.response?.data?.message ?? 'Forkert e-mail eller adgangskode.')).finally(() => setBusy(false)); }}>
    <div className="login-brand"><div className="brand-mark large">N</div><div><strong>NOD<span>EXA</span></strong><small>Hosting Platform</small></div></div>
    <div className="login-copy"><h1>Velkommen tilbage</h1><p>Log ind for at administrere dine servere og tjenester.</p></div>
    <label>E-mailadresse<input type="email" required autoComplete="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="navn@eksempel.dk" /></label>
    <label>Adgangskode<input type="password" required autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••••••" /></label>
    {error && <div className="auth-error">{error}</div>}
    <button className="primary-btn login-submit" disabled={busy}>{busy ? 'Logger ind…' : 'Log ind'}</button>
    <div className="login-security"><Icon name="shield" size={16}/><span>Sikker forbindelse · Nodexa</span></div>
  </form></div>;
}

function DashboardPage({ me, servers, onlineServers, totalMemory, totalDisk, choose, openServers }: { me: User; servers: Server[]; onlineServers: number; totalMemory: number; totalDisk: number; choose: (s: Server) => void; openServers: () => void }) {
  const firstName = me.name.split(' ')[0] || me.name;
  return <div className="page-stack">
    <section className="welcome-row"><div><div className="eyebrow">KUNDEPORTAL</div><h1>Velkommen tilbage, {firstName}</h1><p>Her får du et samlet overblik over dine servere og ressourcer.</p></div><button className="secondary-btn" onClick={openServers}><Icon name="server"/> Se alle servere</button></section>
    <section className="stat-grid">
      <StatCard icon="server" label="Servere" value={String(servers.length)} note="Tilknyttet din konto" />
      <StatCard icon="activity" label="Online" value={String(onlineServers)} note={servers.length ? `${Math.round((onlineServers / servers.length) * 100)}% tilgængelighed nu` : 'Ingen servere endnu'} accent="green" />
      <StatCard icon="memory" label="Tildelt RAM" value={fmtMb(totalMemory)} note="På tværs af servere" />
      <StatCard icon="disk" label="Tildelt disk" value={fmtMb(totalDisk)} note="Samlet lagerplads" />
    </section>
    <section className="dashboard-grid">
      <div className="panel-card services-card"><CardHeader title="Dine servere" subtitle="Senest aktive game servers" action={servers.length ? <button className="text-btn" onClick={openServers}>Vis alle <Icon name="chevron" size={15}/></button> : undefined}/>
        {servers.length ? <div className="service-list">{servers.slice(0, 4).map(server => <ServerRow key={server.id} server={server} onClick={() => choose(server)} />)}</div> : <EmptyServers compact onOpen={openServers} />}
      </div>
      <div className="panel-card account-overview"><CardHeader title="Konto" subtitle="Din Nodexa-profil"/><div className="profile-hero"><div className="avatar big-avatar">{initials(me.name)}</div><div><strong>{me.name}</strong><span>{me.email}</span></div></div><div className="detail-list"><div><span>Kontotype</span><strong>{me.is_admin ? 'Administrator' : 'Kunde'}</strong></div><div><span>Sikkerhed</span><strong className="ok-text">Aktiv</strong></div><div><span>Platform</span><strong>Nodexa Cloud</strong></div></div></div>
    </section>
  </div>;
}

function ServersPage({ servers, choose, me }: { servers: Server[]; choose: (s: Server) => void; me: User }) {
  const [query, setQuery] = useState('');
  const filtered = servers.filter(s => `${s.name} ${s.identifier ?? ''}`.toLowerCase().includes(query.toLowerCase()));
  return <div className="page-stack"><section className="page-heading-row"><div><div className="eyebrow">SERVICES</div><h1>{me.is_admin ? 'Servere' : 'Mine servere'}</h1><p>Administrer dine game servers fra ét sted.</p></div><div className="search-box"><Icon name="search" size={17}/><input placeholder="Søg efter server…" value={query} onChange={e => setQuery(e.target.value)}/></div></section>
    {filtered.length ? <div className="server-card-grid">{filtered.map(server => <ServerCard key={server.id} server={server} onClick={() => choose(server)} />)}</div> : <div className="panel-card"><EmptyServers onOpen={() => setQuery('')} /></div>}
  </div>;
}

function ServerWorkspace(props: { me: User; server: Server; stats: Stats | null; tab: Tab; setTab: (t: Tab) => void; back: () => void; power: (s: string) => any; logs: string; cmd: string; setCmd: (v: string) => void; send: () => void; databases: Database[]; dbName: string; setDbName: (v: string) => void; createDatabase: () => void; openDatabase: (d: Database) => void; deleteDatabase: (d: Database) => void; password: string | null; clearPassword: () => void; pmaUrl: string | null; closePma: () => void; users: Subuser[]; addUser: (e: string, p: string[]) => any; updateUser: (u: Subuser, p: string[]) => any; removeUser: (u: Subuser) => any }) {
  const { me, server, stats, tab, setTab, back, power } = props;
  const tabs = tabsFor(server, me);
  const status = stats?.state ?? server.status;
  const running = ['running', 'online'].includes(String(status).toLowerCase());
  const permissions = permissionsFor(server);
  const all = permissions.includes('*');
  return <div className="page-stack server-workspace">
    <section className="server-hero"><div className="server-hero-main"><button className="back-btn" onClick={back}>‹</button><div className="server-icon"><Icon name="server" size={24}/></div><div><div className="server-meta"><span>{server.identifier ?? 'SERVER'}</span><span>•</span><span>Game Server</span></div><h1>{server.name}</h1><div className="status-line"><span className={'status-dot ' + (running ? 'online' : '')}/><strong>{running ? 'Online' : 'Offline'}</strong><span>·</span><span>{fmtMb(server.memory_mb)} RAM</span><span>·</span><span>{fmtMb(server.disk_mb)} Disk</span></div></div></div>
      <div className="server-actions">{(all || permissions.includes('power.start')) && <button className="power-btn start" onClick={() => power('start')}><Icon name="play"/> Start</button>}{(all || permissions.includes('power.restart')) && <button className="power-btn" onClick={() => power('restart')}><Icon name="restart"/> Genstart</button>}{(all || permissions.includes('power.stop')) && <button className="power-btn stop" onClick={() => power('stop')}><Icon name="stop"/> Stop</button>}</div>
    </section>
    <div className="workspace-tabs">{tabs.map(t => <button key={t} className={tab === t ? 'active' : ''} onClick={() => setTab(t)}><Icon name={tabIcons[t]} size={17}/><span>{tabLabels[t]}</span></button>)}</div>
    {tab === 'console' && <ConsolePage stats={stats} server={server} logs={props.logs} cmd={props.cmd} setCmd={props.setCmd} send={props.send} canRead={has(server, 'console.read')} canCommand={has(server, 'console.command')} />}
    {tab === 'databases' && <DatabasePage server={server} databases={props.databases} dbName={props.dbName} setDbName={props.setDbName} createDatabase={props.createDatabase} openDatabase={props.openDatabase} deleteDatabase={props.deleteDatabase} password={props.password} clearPassword={props.clearPassword} pmaUrl={props.pmaUrl} closePma={props.closePma} canCreate={has(server, 'database.create')} canDelete={has(server, 'database.delete')} />}
    {tab === 'users' && (me.is_admin || server.owner_id === me.id) && <UsersPage users={props.users} add={props.addUser} update={props.updateUser} remove={props.removeUser} />}
    {tab === 'files' && <FilesPage server={server} canWrite={has(server, 'files.write')} />}
    {tab === 'schedules' && <SchedulesPage server={server} canCreate={has(server, 'schedule.create')} canUpdate={has(server, 'schedule.update')} canDelete={has(server, 'schedule.delete')} canExecute={has(server, 'schedule.execute')} />}
    {tab === 'backups' && <BackupsPage server={server} canCreate={has(server, 'backups.create')} canDownload={has(server, 'backups.download')} canRestore={has(server, 'backups.restore')} canDelete={has(server, 'backups.delete')} />}
    {tab === 'network' && <NetworkPage server={server} canUpdate={has(server, 'allocation.update')} />}
    {tab === 'startup' && me.is_admin && <StartupPage server={server} isAdmin />}
    {tab === 'settings' && <ServerSettingsPage server={server} canUpdate={has(server, 'settings.update')} canReinstall={has(server, 'settings.reinstall')} canSftp={has(server, 'files.sftp')} />}
  </div>;
}

function ConsolePage({ stats, server, logs, cmd, setCmd, send, canRead, canCommand }: { stats: Stats | null; server: Server; logs: string; cmd: string; setCmd: (v: string) => void; send: () => void; canRead: boolean; canCommand: boolean }) {
  const memoryLimit = stats?.memory_limit || server.memory_mb * 1024 * 1024;
  return <div className="module-stack">
    <div className="resource-grid"><ResourceCard icon="cpu" label="CPU" value={`${Number(stats?.cpu_percent ?? 0).toFixed(1)}%`} percent={Number(stats?.cpu_percent ?? 0)} /><ResourceCard icon="memory" label="RAM" value={fmtBytes(stats?.memory_bytes ?? 0)} sub={`af ${fmtMb(server.memory_mb)}`} percent={pct(stats?.memory_bytes ?? 0, memoryLimit)} /><ResourceCard icon="disk" label="Disk" value={fmtMb(server.disk_mb)} sub="tildelt" percent={0} /><ResourceCard icon="network" label="Netværk" value={fmtBytes((stats?.network_rx_bytes ?? 0) + (stats?.network_tx_bytes ?? 0))} sub="samlet trafik" percent={0} /></div>
    {canRead ? <div className="console-panel"><div className="console-header"><div><span className="console-dot red"/><span className="console-dot yellow"/><span className="console-dot green"/></div><div><Icon name="terminal" size={16}/> Live Console</div><span className="live-pill"><span/> LIVE</span></div><pre>{logs || 'Ingen console-output endnu. Start serveren for at se live output.'}</pre>{canCommand && <div className="command-bar"><span>›</span><input value={cmd} onChange={e => setCmd(e.target.value)} onKeyDown={e => e.key === 'Enter' && send()} placeholder="Skriv en kommando…"/><button onClick={send}>Send</button></div>}</div> : <div className="panel-card empty-permission">Du har ikke tilladelse til at se konsollen.</div>}
  </div>;
}

function DatabasePage({ server, databases, dbName, setDbName, createDatabase, openDatabase, deleteDatabase, password, clearPassword, pmaUrl, closePma, canCreate, canDelete }: { server: Server; databases: Database[]; dbName: string; setDbName: (v: string) => void; createDatabase: () => void; openDatabase: (d: Database) => void; deleteDatabase: (d: Database) => void; password: string | null; clearPassword: () => void; pmaUrl: string | null; closePma: () => void; canCreate: boolean; canDelete: boolean }) {
  const [showPassword, setShowPassword] = useState(false);
  if (pmaUrl) return <div className="db-workspace"><div className="db-workspace-bar"><div><button className="back-link" onClick={closePma}>‹ Tilbage</button><strong>Database workspace</strong><small>{server.identifier} · sikker session</small></div></div><iframe title="Nodexa Database Workspace" src={pmaUrl}/></div>;
  return <div className="module-stack"><section className="module-heading"><div><h2>Databaser</h2><p>Opret og administrer isolerede databaser til {server.name}.</p></div>{canCreate && <div className="db-create"><span>{server.identifier}_</span><input value={dbName} onChange={e => setDbName(e.target.value.replace(/[^A-Za-z0-9_-]/g, ''))} placeholder="database"/><button className="primary-btn" onClick={createDatabase}><Icon name="plus"/> Opret</button></div>}</section>
    {password && <div className="secret-banner"><div><div className="secret-icon"><Icon name="shield"/></div><div><strong>Database oprettet</strong><p>Gem adgangskoden et sikkert sted. Den skjules automatisk.</p><code>{showPassword ? password : '••••••••••••••••••••••••••••••••'}</code></div></div><div className="secret-actions"><button onClick={() => setShowPassword(v => !v)}>{showPassword ? 'Skjul' : 'Vis'}</button><button onClick={() => { setShowPassword(false); clearPassword(); }}>Luk</button></div></div>}
    <div className="panel-card table-card"><table><thead><tr><th>Database</th><th>Brugernavn</th><th>Host</th><th>Oprettet</th><th/></tr></thead><tbody>{databases.map(db => <tr key={db.id}><td><div className="table-title"><span className="mini-icon"><Icon name="database" size={15}/></span><strong>{db.name}</strong></div></td><td><code>{db.username}</code></td><td>{db.host}:{db.port}</td><td>{db.created_at ? new Date(db.created_at).toLocaleDateString('da-DK') : '—'}</td><td className="row-actions"><button className="secondary-btn small" onClick={() => openDatabase(db)}>Åbn</button>{canDelete && <button className="danger-link" onClick={() => deleteDatabase(db)}>Slet</button>}</td></tr>)}{!databases.length && <tr><td colSpan={5}><div className="table-empty"><div className="empty-icon"><Icon name="database"/></div><strong>Ingen databaser endnu</strong><span>Opret din første database ovenfor.</span></div></td></tr>}</tbody></table></div>
  </div>;
}

const permissionGroups = [
  { title: 'Konsol & strøm', items: [['console.read','Se konsol'],['console.command','Send kommandoer'],['power.start','Start server'],['power.stop','Stop server'],['power.restart','Genstart server']] },
  { title: 'Filer & SFTP', items: [['files.read','Se filer'],['files.write','Rediger filer'],['files.sftp','SFTP-adgang']] },
  { title: 'Databaser', items: [['database.read','Se databaser'],['database.create','Opret databaser'],['database.credentials','Se credentials'],['database.delete','Slet databaser']] },
  { title: 'Planlægninger', items: [['schedule.read','Se planlægninger'],['schedule.create','Opret'],['schedule.update','Rediger'],['schedule.execute','Kør nu'],['schedule.delete','Slet']] },
  { title: 'Backups', items: [['backups.read','Se backups'],['backups.create','Opret backup'],['backups.download','Download'],['backups.restore','Gendan'],['backups.delete','Slet']] },
  { title: 'Netværk', items: [['allocation.read','Se allocations'],['allocation.update','Rediger noter/primær']] },
  { title: 'Indstillinger', items: [['settings.read','Se indstillinger'],['settings.update','Rediger servernavn'],['settings.reinstall','Geninstaller server']] },
];

function UsersPage({ users, add, update, remove }: { users: Subuser[]; add: (e: string, p: string[]) => any; update: (u: Subuser, p: string[]) => any; remove: (u: Subuser) => any }) {
  const [showInvite, setShowInvite] = useState(false);
  const [email, setEmail] = useState('');
  const [permissions, setPermissions] = useState<string[]>(['console.read']);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState<Subuser | null>(null);
  const [editPermissions, setEditPermissions] = useState<string[]>([]);
  const [editBusy, setEditBusy] = useState(false);
  const [editError, setEditError] = useState('');
  const toggle = (p: string) => setPermissions(v => v.includes(p) ? v.filter(x => x !== p) : [...v, p]);
  const toggleEdit = (p: string) => setEditPermissions(v => v.includes(p) ? v.filter(x => x !== p) : [...v, p]);
  const openEditor = (entry: Subuser) => {
    setEditing(entry);
    setEditPermissions(toStringArray(entry.permissions));
    setEditError('');
  };
  const closeEditor = () => {
    if (editBusy) return;
    setEditing(null);
    setEditPermissions([]);
    setEditError('');
  };
  return <div className="module-stack"><section className="module-heading"><div><h2>Brugere</h2><p>Giv andre eksisterende Nodexa-brugere adgang til denne server.</p></div><button className="primary-btn" onClick={() => setShowInvite(true)}><Icon name="plus"/> Inviter bruger</button></section>
    <div className="panel-card user-list"><div className="owner-row"><div className="user-identity"><div className="avatar owner-avatar">E</div><div><strong>Serverejer</strong><span>Fuld adgang til serveren</span></div></div><span className="role-badge">Ejer</span><div className="permission-summary"><Icon name="shield" size={15}/> Alle rettigheder</div></div>{users.map(entry => { const p = toStringArray(entry.permissions); return <div className="user-row" key={entry.id}><div className="user-identity"><div className="avatar">{initials(entry.user.name)}</div><div><strong>{entry.user.name}</strong><span>{entry.user.email}</span></div></div><span className="role-badge secondary">Bruger</span><div className="permission-summary">{p.length} rettigheder</div><div className="row-actions subuser-actions"><button className="secondary-btn small" onClick={() => openEditor(entry)}>Rediger</button><button className="danger-link" onClick={() => confirm(`Fjern ${entry.user.email}?`) && remove(entry)}>Fjern</button></div></div>; })}{!users.length && <div className="subtle-empty">Ingen ekstra brugere har adgang til serveren endnu.</div>}</div>
    {showInvite && <div className="modal"><div className="modal-card invite-modal"><div className="modal-title"><div><div className="eyebrow">SERVER ACCESS</div><h2>Inviter bruger</h2><p>Brugeren skal allerede have en Nodexa-konto.</p></div><button className="close-btn" onClick={() => setShowInvite(false)}>×</button></div><label className="field-label">E-mailadresse<input value={email} onChange={e => setEmail(e.target.value)} type="email" placeholder="ven@eksempel.dk"/></label><div className="permissions-grid">{permissionGroups.map(group => <div className="permission-group" key={group.title}><strong>{group.title}</strong>{group.items.map(([key,label]) => <label key={key}><input type="checkbox" checked={permissions.includes(key)} onChange={() => toggle(key)}/><span>{label}</span></label>)}</div>)}</div>{error && <div className="auth-error">{error}</div>}<div className="modal-actions"><button className="secondary-btn" onClick={() => setShowInvite(false)}>Annuller</button><button className="primary-btn" disabled={busy || !email.trim()} onClick={() => { setBusy(true); setError(''); Promise.resolve(add(email.trim(), permissions)).then(() => { setEmail(''); setShowInvite(false); }).catch(e => setError(e?.response?.data?.message ?? 'Kunne ikke invitere brugeren.')).finally(() => setBusy(false)); }}>{busy ? 'Tilføjer…' : 'Giv adgang'}</button></div></div></div>}
    {editing && <div className="modal"><div className="modal-card invite-modal"><div className="modal-title"><div><div className="eyebrow">SERVER ACCESS</div><h2>Rediger rettigheder</h2><p>{editing.user.name} · {editing.user.email}</p></div><button className="close-btn" disabled={editBusy} onClick={closeEditor}>×</button></div><div className="permissions-grid">{permissionGroups.map(group => <div className="permission-group" key={group.title}><strong>{group.title}</strong>{group.items.map(([key,label]) => <label key={key}><input type="checkbox" checked={editPermissions.includes(key)} disabled={editBusy} onChange={() => toggleEdit(key)}/><span>{label}</span></label>)}</div>)}</div>{editError && <div className="auth-error">{editError}</div>}<div className="modal-actions"><button className="secondary-btn" disabled={editBusy} onClick={closeEditor}>Annuller</button><button className="primary-btn" disabled={editBusy || editPermissions.length === 0} onClick={() => { setEditBusy(true); setEditError(''); Promise.resolve(update(editing, editPermissions)).then(() => { setEditing(null); setEditPermissions([]); }).catch(e => setEditError(e?.response?.data?.message ?? 'Kunne ikke gemme rettighederne.')).finally(() => setEditBusy(false)); }}>{editBusy ? 'Gemmer…' : 'Gem rettigheder'}</button></div></div></div>}
  </div>;
}

function NodesPage({ nodes, showForm, setShowForm, form, setForm, createNode, configFor, config, closeConfig }: { nodes: Node[]; showForm: boolean; setShowForm: (v: boolean) => void; form: any; setForm: (v: any) => void; createNode: () => void; configFor: (n: Node) => void; config: NodeConfig | null; closeConfig: () => void }) {
  const [showConfigSecret, setShowConfigSecret] = useState(false);
  return <div className="page-stack"><section className="page-heading-row"><div><div className="eyebrow">ADMINISTRATION</div><h1>Nodes</h1><p>Administrer Nodexa Agent-noder og deres kapacitet.</p></div><button className="primary-btn" onClick={() => setShowForm(true)}><Icon name="plus"/> Opret Node</button></section><div className="node-grid">{nodes.map(node => {
      const health = String(node.health_status ?? 'unknown').toLowerCase();
      const online = health === 'online';
      const healthLabel = online ? 'Online' : health === 'offline' ? 'Offline' : 'Ukendt';
      const lastSeen = node.health_last_seen_at ? new Date(node.health_last_seen_at).toLocaleString('da-DK') : 'Aldrig';
      return <div className="panel-card node-card" key={node.id}><div className="node-top"><div className="node-icon"><Icon name="node"/></div><span className={'service-status ' + (online ? 'online' : '')} title={node.health_message ?? undefined}><i/>{healthLabel}</span></div><h3>{node.name}</h3><p>{node.location ?? 'Ingen lokation'}</p><div className="node-host">{node.fqdn}:{node.daemon_port}</div><div className="server-meta"><span>{node.health_latency_ms !== null && node.health_latency_ms !== undefined ? `${node.health_latency_ms} ms` : 'Ingen latency'}</span><span>•</span><span>Sidst set {lastSeen}</span></div><div className="node-stats"><div><span>RAM</span><strong>{fmtMb(node.memory_mb)}</strong></div><div><span>Disk</span><strong>{fmtMb(node.disk_mb)}</strong></div><div><span>Servere</span><strong>{node.servers_count ?? 0}</strong></div></div><button className="secondary-btn full" onClick={() => { setShowConfigSecret(false); configFor(node); }}>Konfiguration</button></div>;
    })}{!nodes.length && <div className="panel-card node-empty"><div className="empty-icon"><Icon name="node"/></div><h3>Ingen Nodes endnu</h3><p>Opret din første Node og installer Nodexa Agent.</p><button className="primary-btn" onClick={() => setShowForm(true)}><Icon name="plus"/> Opret Node</button></div>}</div>
    {showForm && <div className="modal"><div className="modal-card"><div className="modal-title"><div><div className="eyebrow">NODE SETUP</div><h2>Opret Node</h2></div><button className="close-btn" onClick={() => setShowForm(false)}>×</button></div><div className="form-grid"><label>Navn<input value={form.name} onChange={e => setForm({...form,name:e.target.value})}/></label><label>Lokation<input value={form.location} onChange={e => setForm({...form,location:e.target.value})}/></label><label className="wide">FQDN<input value={form.fqdn} onChange={e => setForm({...form,fqdn:e.target.value})} placeholder="node.example.com"/></label><label>Agent port<input type="number" value={form.daemon_port} onChange={e => setForm({...form,daemon_port:+e.target.value})}/></label><label>SFTP port<input type="number" value={form.sftp_port} onChange={e => setForm({...form,sftp_port:+e.target.value})}/></label><label>Memory MB<input type="number" value={form.memory_mb} onChange={e => setForm({...form,memory_mb:+e.target.value})}/></label><label>Disk MB<input type="number" value={form.disk_mb} onChange={e => setForm({...form,disk_mb:+e.target.value})}/></label></div><div className="modal-actions"><button className="secondary-btn" onClick={() => setShowForm(false)}>Annuller</button><button className="primary-btn" onClick={createNode}>Opret Node</button></div></div></div>}
    {config && <div className="modal"><div className="modal-card"><div className="modal-title"><div><div className="eyebrow">AGENT SETUP</div><h2>Node konfiguration</h2></div><button className="close-btn" onClick={() => { setShowConfigSecret(false); closeConfig(); }}>×</button></div><p>Kør kommandoen som root på Node-serveren:</p><pre className="install-command">{showConfigSecret ? config.install_command : '••••••••••••••••••••••••••••••••••••••••'}</pre><div className="warning-box"><Icon name="shield"/> Node-tokenet er hemmeligt og må ikke deles.</div><div className="modal-actions"><button className="secondary-btn" onClick={() => setShowConfigSecret(v => !v)}>{showConfigSecret ? 'Skjul' : 'Vis kommando'}</button></div></div></div>}
  </div>;
}

function NavButton({ icon, label, active, badge, onClick }: { icon: IconName; label: string; active: boolean; badge?: number; onClick: () => void }) { return <button className={'nav-item ' + (active ? 'active' : '')} onClick={onClick}><Icon name={icon}/><span>{label}</span>{badge !== undefined && <b>{badge}</b>}</button>; }
function StatCard({ icon, label, value, note, accent }: { icon: IconName; label: string; value: string; note: string; accent?: string }) { return <div className="panel-card stat-card"><div className={'stat-icon ' + (accent ?? '')}><Icon name={icon}/></div><div><span>{label}</span><strong>{value}</strong><small>{note}</small></div></div>; }
function ResourceCard({ icon, label, value, sub, percent }: { icon: IconName; label: string; value: string; sub?: string; percent: number }) { return <div className="panel-card resource-card"><div className="resource-head"><div className="resource-icon"><Icon name={icon}/></div><span>{label}</span></div><div className="resource-value"><strong>{value}</strong>{sub && <small>{sub}</small>}</div><div className="progress-track"><span style={{width: `${Math.min(100, Math.max(0, percent))}%`}}/></div></div>; }
function CardHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: React.ReactNode }) { return <div className="card-header"><div><h3>{title}</h3>{subtitle && <p>{subtitle}</p>}</div>{action}</div>; }
function ServerRow({ server, onClick }: { server: Server; onClick: () => void }) { const online = ['online','running'].includes(String(server.status).toLowerCase()); return <button className="service-row" onClick={onClick}><div className="service-icon"><Icon name="server"/></div><div className="service-main"><strong>{server.name}</strong><span>{server.identifier ?? 'SERVER'} · {fmtMb(server.memory_mb)} RAM</span></div><span className={'service-status ' + (online ? 'online' : '')}><i/>{online ? 'Online' : 'Offline'}</span><Icon name="chevron" size={17}/></button>; }
function ServerCard({ server, onClick }: { server: Server; onClick: () => void }) { const online = ['online','running'].includes(String(server.status).toLowerCase()); return <div className="panel-card server-card"><div className="server-card-top"><div className="server-icon"><Icon name="server"/></div><span className={'service-status ' + (online ? 'online' : '')}><i/>{online ? 'Online' : 'Offline'}</span></div><div className="server-card-title"><span>{server.identifier ?? 'SERVER'}</span><h3>{server.name}</h3></div><div className="server-card-resources"><div><Icon name="memory" size={15}/><span>{fmtMb(server.memory_mb)} RAM</span></div><div><Icon name="disk" size={15}/><span>{fmtMb(server.disk_mb)} Disk</span></div><div><Icon name="cpu" size={15}/><span>{server.cpu_limit || 0}% CPU</span></div></div><button className="primary-btn full" onClick={onClick}>Åbn server <Icon name="chevron" size={16}/></button></div>; }
function EmptyServers({ compact = false, onOpen }: { compact?: boolean; onOpen: () => void }) { return <div className={'empty-servers ' + (compact ? 'compact' : '')}><div className="empty-icon"><Icon name="server"/></div><strong>Ingen servere tilknyttet</strong><p>Når en server bliver tildelt din konto, vises den her automatisk.</p>{!compact && <button className="secondary-btn" onClick={onOpen}>Opdater visning</button>}</div>; }
function initials(name: string) { return name.split(/\s+/).filter(Boolean).slice(0,2).map(part => part[0]?.toUpperCase()).join('') || 'N'; }
