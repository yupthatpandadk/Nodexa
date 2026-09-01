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
  const message = error instanceof Error ? error.message : String(error ?? 'Unknown frontend error');
  const fallback = document.getElementById('nodexa-boot-fallback');
  if (fallback) {
    fallback.style.display = 'grid';
    const details = fallback.querySelector('[data-nodexa-error]');
    if (details) details.textContent = message;
  }
  console.error('[Nodexa] Frontend boot failed:', error);
}

function NodexaRoot() {
  React.useEffect(() => {
    window.__NODEXA_BOOTED__ = true;
    window.dispatchEvent(new Event('nodexa:booted'));
  }, []);

  return (
    <BrowserRouter>
      <App />
    </BrowserRouter>
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
