import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
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
