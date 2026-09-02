import React, { useEffect, useState } from 'react';
import axios from 'axios';

type ServerLike = { id: string; name: string };
type SftpDetails = { host: string; port: number; username: string };

function message(error: any): string {
  return error?.response?.data?.message ?? 'Kunne ikke hente SFTP-oplysningerne.';
}

export function SftpAccessCard({ server }: { server: ServerLike }) {
  const [details, setDetails] = useState<SftpDetails | null>(null);
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    setDetails(null); setError(''); setSuccess(''); setPassword('');
    axios.get(`/api/servers/${server.id}/sftp`).then(response => setDetails(response.data)).catch(e => setError(message(e)));
  }, [server.id]);

  const sync = async () => {
    if (!password || busy) return;
    setBusy(true); setError(''); setSuccess('');
    try {
      await axios.post(`/api/servers/${server.id}/sftp/sync`, { password });
      setPassword(''); setSuccess('SFTP-adgangen er aktiveret og synkroniseret.');
    } catch (e) { setError(message(e)); } finally { setBusy(false); }
  };

  return <section className="panel-card sftp-card">
    <div className="sftp-card-heading"><div><div className="eyebrow">SFTP ACCESS</div><h2>SFTP-adgang</h2><p>Forbind direkte til filerne på {server.name}.</p></div><span className="role-badge">Krypteret</span></div>
    {error && <div className="auth-error">{error}</div>}
    {success && <div className="sftp-success">{success}</div>}
    {details && <div className="sftp-details"><label>Serveradresse<input readOnly value={`sftp://${details.host}:${details.port}`}/></label><label>Brugernavn<input readOnly value={details.username}/></label><label className="sftp-password">Bekræft dit Nodexa-password<input type="password" autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} onKeyDown={e => e.key === 'Enter' && sync()} placeholder="••••••••"/></label><button className="primary-btn" disabled={busy || !password} onClick={sync}>{busy ? 'Synkroniserer…' : 'Aktivér / synkronisér'}</button></div>}
    <small className="sftp-note">SFTP bruger dit nuværende Nodexa-password. Adgangen gælder kun denne server.</small>
  </section>;
}
