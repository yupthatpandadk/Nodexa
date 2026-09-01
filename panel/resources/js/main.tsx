import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import axios from 'axios';
import App from './App';
import './styles.css';
import './permissions.css';
import './phpmyadmin.css';
import './schedules.css';

declare global {
  interface Window {
    __NODEXA_BOOTED__?: boolean;
  }
}

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

// Old Nodexa accounts and older API payloads may miss `name`. Normalise every
// authenticated user payload before App.tsx sees it, so rendering can never
// crash on string operations such as split().
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
