import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import axios from 'axios';
import App from './App';
import './server-address';
import './styles.css';
import './permissions.css';
import './phpmyadmin.css';
import './schedules.css';

declare global {
  interface Window {
    __NODEXA_BOOTED__?: boolean;
  }
}

axios.defaults.timeout = 10000;

function abortSignal(ms: number): AbortSignal | undefined {
  try {
    if (typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
      return AbortSignal.timeout(ms);
    }
  } catch {}
  return undefined;
}

// Authentication/bootstrap must always fail fast. Axios' timeout alone is not
// enough on every mobile browser/proxy combination, so also attach a native
// AbortSignal deadline. This guarantees App.tsx reaches its .catch/.finally
// path instead of leaving the user on "Indlæser kontrolpanel…" forever.
axios.interceptors.request.use(config => {
  const url = String(config.url ?? '');
  const method = String(config.method ?? 'get').toLowerCase();
  if (method === 'get' && (url === '/api/me' || /^\/api\/servers(?:\?.*)?$/.test(url))) {
    config.timeout = 3500;
    if (!config.signal) config.signal = abortSignal(4000);
    config.headers = config.headers ?? {};
    config.headers['Cache-Control'] = 'no-cache';
    config.headers.Pragma = 'no-cache';
  }
  return config;
});

function safeText(value: unknown, fallback = ''): string {
  if (typeof value === 'string') {
    const text = value.trim();
    if (text) return text;
  }
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return fallback;
}

function normalizeUser(value: any) {
  if (!value || typeof value !== 'object') {
    return { id: 0, name: 'Nodexa User', email: '', username: '', first_name: '', last_name: '', is_admin: false };
  }

  const firstName = safeText(value.first_name);
  const lastName = safeText(value.last_name);
  const fullName = [firstName, lastName].filter(Boolean).join(' ');
  const email = safeText(value.email);
  const username = safeText(value.username);
  const name = safeText(value.name, fullName || username || email || 'Nodexa User');

  return {
    ...value,
    name,
    email,
    username,
    first_name: firstName,
    last_name: lastName,
    is_admin: Boolean(value.is_admin),
  };
}

axios.interceptors.response.use(response => {
  const url = String(response.config?.url ?? '');

  if (url.includes('/api/me')) {
    const payload = response.data?.data && typeof response.data.data === 'object'
      ? response.data.data
      : response.data;
    response.data = normalizeUser(payload);
  }

  if (url.includes('/api/login') && response.data?.user) {
    response.data.user = normalizeUser(response.data.user);
  }

  if (/\/api\/servers\/[^/]+\/users/.test(url)) {
    const container = Array.isArray(response.data)
      ? response.data
      : Array.isArray(response.data?.data)
        ? response.data.data
        : null;

    if (container) {
      for (const entry of container) {
        if (entry && typeof entry === 'object' && entry.user) {
          entry.user = normalizeUser(entry.user);
        }
      }
    }
  }

  return response;
}, error => {
  const url = String(error?.config?.url ?? '');
  const method = String(error?.config?.method ?? 'get').toLowerCase();

  if (method === 'get' && /^\/api\/servers(?:\?.*)?$/.test(url)) {
    console.error('[Nodexa] Server list unavailable; continuing panel boot.', error);
    return Promise.resolve({
      data: [],
      status: 200,
      statusText: 'Nodexa fallback',
      headers: {},
      config: error.config,
      request: error.request,
    });
  }

  return Promise.reject(error);
});

function showBootError(error: unknown) {
  const message = error instanceof Error ? `${error.name}: ${error.message}` : String(error ?? 'Unknown frontend error');
  const fallback = document.getElementById('nodexa-boot-fallback');
  if (fallback) {
    fallback.style.display = 'grid';
    fallback.classList.add('failed');
    const title = fallback.querySelector('[data-nodexa-title]');
    const description = fallback.querySelector('[data-nodexa-description]');
    const spinner = fallback.querySelector('.nodexa-spinner');
    if (spinner) spinner.remove();
    if (title) title.textContent = 'Nodexa frontend-fejl';
    if (description) description.textContent = 'React startede, men en komponent fejlede. Kopiér fejlen nedenfor eller send et screenshot.';
    const details = fallback.querySelector('[data-nodexa-error]');
    if (details) details.textContent = message;
  }
  console.error('[Nodexa] Frontend boot/render failed:', error);
}

class NodexaErrorBoundary extends React.Component<React.PropsWithChildren, { error: Error | null }> {
  state: { error: Error | null } = { error: null };

  static getDerivedStateFromError(error: Error) {
    return { error };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    const message = `${error.name}: ${error.message}\n\n${info.componentStack ?? ''}`;
    showBootError(message);
  }

  render() {
    if (this.state.error) {
      return (
        <div className="auth-screen">
          <div className="auth-card">
            <div className="brand auth-brand">NOD<span>EXA</span></div>
            <h1>Frontend-fejl</h1>
            <p>Panelet kunne starte, men React stoppede under rendering.</p>
            <code style={{display:'block',whiteSpace:'pre-wrap',wordBreak:'break-word',marginTop:12}}>
              {this.state.error.name}: {this.state.error.message}
            </code>
            <button className="primary auth-submit" onClick={() => location.reload()}>Genindlæs</button>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}

function NodexaRoot() {
  React.useEffect(() => {
    window.__NODEXA_BOOTED__ = true;
    window.dispatchEvent(new Event('nodexa:booted'));

    // Keep the live console pinned to the newest output. React updates the
    // existing <pre> node every time fresh logs arrive, so watch the app DOM
    // and only scroll when the console text has actually changed.
    let lastConsoleText = '';
    let scrollFrame = 0;
    const syncConsoleScroll = () => {
      const consoleOutput = document.querySelector<HTMLElement>('.console-panel pre');
      if (!consoleOutput) {
        lastConsoleText = '';
        return;
      }
      const nextText = consoleOutput.textContent ?? '';
      if (nextText === lastConsoleText) return;
      lastConsoleText = nextText;
      if (scrollFrame) window.cancelAnimationFrame(scrollFrame);
      scrollFrame = window.requestAnimationFrame(() => {
        consoleOutput.scrollTop = consoleOutput.scrollHeight;
      });
    };
    const consoleObserver = new MutationObserver(syncConsoleScroll);
    const appRoot = document.getElementById('app');
    if (appRoot) {
      consoleObserver.observe(appRoot, { subtree: true, childList: true, characterData: true });
      syncConsoleScroll();
    }

    // Last-resort watchdog for stale cached bundles or unusual browser/network
    // behaviour. Never leave a permanent splash screen. If App still displays
    // its loading shell after 7 seconds, remove the stale bearer token once and
    // reload into the login screen. sessionStorage prevents reload loops.
    const watchdog = window.setTimeout(() => {
      const loading = document.querySelector('.loading-screen');
      if (!loading) {
        sessionStorage.removeItem('nodexa_boot_recovery');
        return;
      }

      const alreadyRecovered = sessionStorage.getItem('nodexa_boot_recovery') === '1';
      if (!alreadyRecovered) {
        sessionStorage.setItem('nodexa_boot_recovery', '1');
        localStorage.removeItem('nodexa_panel_token');
        delete axios.defaults.headers.common.Authorization;
        location.reload();
        return;
      }

      loading.innerHTML = '<div class="loading-logo"><div class="brand-mark large">N</div><strong>NOD<span>EXA</span></strong></div><div style="max-width:420px;text-align:center;color:#9aa4b5;line-height:1.55;padding:16px">Panelets API svarer ikke. Genindlæs siden eller kontrollér PHP/Nginx under Admin → Fejl.</div><button onclick="location.reload()" style="padding:10px 14px;border:0;border-radius:9px;background:#745cff;color:white;font-weight:700">Genindlæs</button>';
    }, 7000);

    return () => {
      window.clearTimeout(watchdog);
      consoleObserver.disconnect();
      if (scrollFrame) window.cancelAnimationFrame(scrollFrame);
    };
  }, []);

  return (
    <NodexaErrorBoundary>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </NodexaErrorBoundary>
  );
}

try {
  const mount = document.getElementById('app');
  if (!mount) throw new Error('Missing #app mount element.');

  ReactDOM.createRoot(mount).render(
    <React.StrictMode>
      <NodexaRoot />
    </React.StrictMode>,
  );
} catch (error) {
  showBootError(error);
}
