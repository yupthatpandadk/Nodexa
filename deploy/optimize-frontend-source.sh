#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
APP="$PANEL_DIR/resources/js/App.tsx"

[[ -f "$APP" ]] || exit 0

python3 - "$APP" <<'PY'
from pathlib import Path
import sys

p = Path(sys.argv[1])
text = p.read_text()

old = """const TOKEN_KEY = 'nodexa_panel_token';
const savedToken = localStorage.getItem(TOKEN_KEY);
if (savedToken) axios.defaults.headers.common.Authorization = `Bearer ${savedToken}`;
"""
new = """const TOKEN_KEY = 'nodexa_panel_token';
const USER_CACHE_KEY = 'nodexa_panel_user';
const SERVER_CACHE_KEY = 'nodexa_panel_servers';
const savedToken = localStorage.getItem(TOKEN_KEY);
if (savedToken) axios.defaults.headers.common.Authorization = `Bearer ${savedToken}`;

function readCachedUser(): User | null {
  if (!savedToken) return null;
  try {
    const value = JSON.parse(localStorage.getItem(USER_CACHE_KEY) || 'null');
    if (!value || typeof value !== 'object') return null;
    const id = Number(value.id || 0);
    const name = typeof value.name === 'string' && value.name.trim() ? value.name.trim() : 'Nodexa User';
    const email = typeof value.email === 'string' ? value.email : '';
    if (!id) return null;
    return { ...value, id, name, email, is_admin: Boolean(value.is_admin) } as User;
  } catch {
    return null;
  }
}

function readCachedServers(): Server[] {
  if (!savedToken) return [];
  try {
    const value = JSON.parse(localStorage.getItem(SERVER_CACHE_KEY) || '[]');
    return Array.isArray(value) ? value as Server[] : [];
  } catch {
    return [];
  }
}

const cachedUser = readCachedUser();
const cachedServers = readCachedServers();
"""
if old in text:
    text = text.replace(old, new, 1)

old = """  const [me, setMe] = useState<User | null>(null);
  const [authReady, setAuthReady] = useState(false);
  const [servers, setServers] = useState<Server[]>([]);
"""
new = """  // Render the shell immediately from the last verified local snapshot. The
  // API validates the bearer token in the background, so a normal refresh no
  // longer waits for the server list before showing the dashboard.
  const [me, setMe] = useState<User | null>(() => cachedUser);
  const [authReady, setAuthReady] = useState(() => !savedToken || cachedUser !== null);
  const [servers, setServers] = useState<Server[]>(() => cachedServers);
"""
if old in text:
    text = text.replace(old, new, 1)

old = """  const loadServers = (user = me) => axios.get('/api/servers').then(response => {
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
"""
new = """  const loadServers = (user = me) => axios.get('/api/servers').then(response => {
    const list = toArray<Server>(response.data);
    setServers(list);
    try { localStorage.setItem(SERVER_CACHE_KEY, JSON.stringify(list)); } catch {}
    if (selected) {
      const refreshed = list.find(server => server.id === selected.id);
      if (refreshed) setSelected(refreshed);
    }
    return list;
  });

  const bootstrap = async () => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (!token) {
      setAuthReady(true);
      return;
    }

    try {
      const response = await axios.get('/api/me');
      const user = response.data as User;
      setMe(user);
      setAuthReady(true);
      try { localStorage.setItem(USER_CACHE_KEY, JSON.stringify(user)); } catch {}

      // Server cards are dashboard data, not an authentication dependency.
      // Refresh them after the shell is already visible.
      void loadServers(user).catch(() => {});
    } catch {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(USER_CACHE_KEY);
      localStorage.removeItem(SERVER_CACHE_KEY);
      delete axios.defaults.headers.common.Authorization;
      setMe(null);
      setServers([]);
      setSelected(null);
    } finally {
      setAuthReady(true);
    }
  };
"""
if old in text:
    text = text.replace(old, new, 1)

old = """  const login = (email: string, password: string) => axios.post('/api/login', { email, password }).then(response => {
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
"""
new = """  const login = (email: string, password: string) => axios.post('/api/login', { email, password }).then(response => {
    localStorage.setItem(TOKEN_KEY, response.data.token);
    try { localStorage.setItem(USER_CACHE_KEY, JSON.stringify(response.data.user)); } catch {}
    axios.defaults.headers.common.Authorization = `Bearer ${response.data.token}`;
    setMe(response.data.user);
    setAuthReady(true);
    setView('dashboard');
    void loadServers(response.data.user).catch(() => {});
    return response.data;
  });
  const logout = () => axios.post('/api/logout').catch(() => {}).finally(() => {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_CACHE_KEY);
    localStorage.removeItem(SERVER_CACHE_KEY);
    delete axios.defaults.headers.common.Authorization;
    setMe(null); setServers([]); setSelected(null); setStats(null); setView('dashboard');
  });
"""
if old in text:
    text = text.replace(old, new, 1)

text = text.replace('Nodexa v0.10.0', 'Nodexa v0.13.0')
p.write_text(text)
PY

NODEXA_PANEL_DIR="$PANEL_DIR" bash "$(dirname "$0")/enforce-nodexa-branding.sh"

echo "[Nodexa] Frontend fast-bootstrap optimization applied."
