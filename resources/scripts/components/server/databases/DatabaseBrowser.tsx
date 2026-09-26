import React, { useEffect, useState } from 'react';
import http from '@/api/http';
import { ServerContext } from '@/state/server';
import { ServerDatabase } from '@/api/server/databases/getServerDatabases';

interface Props { database: ServerDatabase; onBack: () => void; }
interface TableInfo { name: string; rows: number; engine?: string; }
interface Column { Field: string; Type: string; Null: string; Key: string; Default: any; Extra: string; }

export default ({ database, onBack }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [tables, setTables] = useState<TableInfo[]>([]);
    const [table, setTable] = useState('');
    const [rows, setRows] = useState<any[]>([]);
    const [columns, setColumns] = useState<Column[]>([]);
    const [primary, setPrimary] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [mode, setMode] = useState<'browse' | 'sql' | 'import'>('browse');
    const [sql, setSql] = useState('SELECT * FROM ');
    const [sqlResult, setSqlResult] = useState<any>(null);
    const [sqlError, setSqlError] = useState('');
    const [runningSql, setRunningSql] = useState(false);
    const [importing, setImporting] = useState(false);
    const [tablesError, setTablesError] = useState('');
    const [editingRow, setEditingRow] = useState<any | null>(null);
    const [editValues, setEditValues] = useState<Record<string, string>>({});
    const [savingEdit, setSavingEdit] = useState(false);

    const base = `/api/client/servers/${uuid}/databases/${database.id}/browser`;
    const loadTables = () => {
        setLoading(true);
        setTablesError('');
        http.get(`${base}/tables`)
            .then(r => setTables(Array.isArray(r.data?.data) ? r.data.data : []))
            .catch((e: any) => {
                setTables([]);
                setTablesError(e?.response?.data?.message || e?.response?.data?.error || 'Kunne ikke hente tabeller.');
            })
            .finally(() => setLoading(false));
    };
    const loadRows = (name = table) => {
        if (!name) return;
        setLoading(true);
        http.get(`${base}/tables/${encodeURIComponent(name)}`, { params: { search } }).then(r => {
            setRows(r.data.data || []); setColumns(r.data.meta?.columns || []); setPrimary(r.data.meta?.primary_key || null);
        }).finally(() => setLoading(false));
    };
    useEffect(loadTables, []);
    useEffect(() => { if (table) loadRows(table); }, [table]);

    const runSql = async () => {
        if (!sql.trim()) return;
        setRunningSql(true); setSqlError(''); setSqlResult(null);
        try {
            const r = await http.post(`${base}/sql`, { sql });
            setSqlResult(r.data);
            loadTables();
        } catch (e: any) {
            setSqlError(e?.response?.data?.error || e?.response?.data?.message || 'SQL kunne ikke køres.');
        } finally { setRunningSql(false); }
    };
    const importSql = async (file?: File) => {
        if (!file) return;
        setImporting(true); setSqlError(''); setSqlResult(null);
        const form = new FormData(); form.append('file', file);
        try {
            const r = await http.post(`${base}/import`, form, { headers: { 'Content-Type': 'multipart/form-data' } });
            setSqlResult(r.data); loadTables();
        } catch (e: any) {
            setSqlError(e?.response?.data?.error || e?.response?.data?.message || 'SQL-filen kunne ikke importeres.');
        } finally { setImporting(false); }
    };

    const edit = (row: any) => {
        if (!primary) return alert('Tabellen har ingen primary key og kan derfor ikke redigeres sikkert.');
        const values: Record<string, string> = {};
        columns.forEach(c => { values[c.Field] = row[c.Field] == null ? '' : String(row[c.Field]); });
        setEditValues(values);
        setEditingRow(row);
    };
    const saveEdit = async () => {
        if (!editingRow || !primary) return;
        setSavingEdit(true);
        try {
            await http.put(base + '/tables/' + encodeURIComponent(table) + '/rows', { key: primary, key_value: editingRow[primary], values: editValues });
            setEditingRow(null);
            loadRows();
        } catch (e: any) {
            alert(e?.response?.data?.error || e?.response?.data?.message || 'Ændringerne kunne ikke gemmes.');
        } finally { setSavingEdit(false); }
    };
    const remove = async (row: any) => {
        if (!primary || !window.confirm('Slet denne række permanent?')) return;
        await http.delete(`${base}/tables/${encodeURIComponent(table)}/rows`, { data: { key: primary, key_value: row[primary] } });
        loadRows();
    };
    const add = async () => {
        const values: any = {};
        for (const c of columns) {
            if (c.Extra?.includes('auto_increment')) continue;
            const v = window.prompt(c.Field, c.Default == null ? '' : String(c.Default));
            if (v === null) return;
            values[c.Field] = v;
        }
        await http.post(`${base}/tables/${encodeURIComponent(table)}/rows`, { values });
        loadRows();
    };

    return <div style={{display:'grid',gap:16}}>
        {editingRow && <div onClick={()=>!savingEdit&&setEditingRow(null)} style={{position:'fixed',inset:0,zIndex:10000,background:'rgba(2,6,23,.78)',display:'flex',alignItems:'center',justifyContent:'center',padding:16}}><div onClick={e=>e.stopPropagation()} style={{width:'min(760px,100%)',maxHeight:'86vh',overflow:'auto',background:'#101c2d',border:'1px solid #30445e',borderRadius:16,boxShadow:'0 24px 80px rgba(0,0,0,.55)'}}><div style={{padding:'18px 20px',borderBottom:'1px solid #243650'}}><div style={{fontSize:18,fontWeight:800,color:'#fff'}}>Rediger række</div><div style={{marginTop:4,fontSize:12,color:'#8294ad'}}>Rediger alle felter på én gang og gem derefter ændringerne.</div></div><div style={{padding:20,display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(220px,1fr))',gap:14}}>{columns.map(c=><label key={c.Field} style={{display:'grid',gap:6,minWidth:0}}><span style={{color:'#dbeafe',fontSize:12,fontWeight:700}}>{c.Field} <small style={{color:'#64748b',fontWeight:400}}>{c.Type}</small></span><input value={editValues[c.Field] ?? ''} onChange={e=>setEditValues(v=>({...v,[c.Field]:e.target.value}))} disabled={c.Extra?.includes('auto_increment')} style={{width:'100%',boxSizing:'border-box',background:'#0a1422',border:'1px solid #30445e',borderRadius:9,padding:'10px 11px',color:'#fff',opacity:c.Extra?.includes('auto_increment') ? .6 : 1}} /></label>)}</div><div style={{padding:'14px 20px 20px',display:'flex',justifyContent:'flex-end',gap:8}}><button disabled={savingEdit} onClick={()=>setEditingRow(null)} style={{background:'#18283d',border:'1px solid #30445e',borderRadius:9,padding:'10px 16px',color:'#dbeafe'}}>Annuller</button><button disabled={savingEdit} onClick={saveEdit} style={{background:'#2563eb',border:'1px solid #3b82f6',borderRadius:9,padding:'10px 18px',color:'#fff',fontWeight:800}}>{savingEdit?'Gemmer…':'Gem alle ændringer'}</button></div></div></div>}
        <div style={{background:'#101c2d',border:'1px solid #243650',borderRadius:16,padding:18}}>
            <button onClick={onBack} style={{background:'#18283d',border:'1px solid #31445e',borderRadius:9,padding:'9px 13px',color:'#dbeafe'}}>← Databaser</button>
            <div style={{marginTop:14,fontSize:22,fontWeight:700,color:'#fff'}}>{database.name}</div>
            <div style={{color:'#8294ad',fontSize:12,marginTop:4}}>{database.connectionString} · Database Manager</div>
        </div>
        <div style={{display:'grid',gridTemplateColumns:'minmax(180px,240px) minmax(0,1fr)',gap:16}}>
            <aside style={{background:'#101c2d',border:'1px solid #243650',borderRadius:16,padding:12}}>
                <div style={{fontWeight:700,color:'#fff',padding:8}}>Tabeller</div>
                {loading && tables.length === 0 && <div style={{padding:8,color:'#8294ad',fontSize:12}}>Indlæser tabeller…</div>}
                {tablesError && <div style={{margin:'6px 0',padding:9,borderRadius:8,background:'#3a1420',border:'1px solid #7f1d35',color:'#fecdd3',fontSize:11,wordBreak:'break-word'}}>{tablesError}<button onClick={loadTables} style={{display:'block',marginTop:7,background:'#18283d',border:'1px solid #30445e',borderRadius:7,padding:'6px 9px',color:'#fff'}}>Prøv igen</button></div>}
                {!loading && !tablesError && tables.length === 0 && <div style={{padding:8,color:'#8294ad',fontSize:12}}>Ingen tabeller fundet i databasen.</div>}
                {tables.map(t => <button key={t.name} onClick={()=>setTable(t.name)} style={{width:'100%',textAlign:'left',marginTop:5,padding:10,borderRadius:9,border:'1px solid '+(table===t.name?'#22d3ee':'#26384f'),background:table===t.name?'#123149':'#0c1727',color:'#dbeafe'}}>{t.name}<small style={{display:'block',opacity:.55}}>{t.rows} rækker</small></button>)}
            </aside>
            <main style={{background:'#101c2d',border:'1px solid #243650',borderRadius:16,padding:14,minWidth:0}}>
                <div style={{display:'flex',gap:8,flexWrap:'wrap',marginBottom:14}}>
                    <button onClick={()=>setMode('browse')} style={{background:mode==='browse'?'#0891b2':'#18283d',border:'1px solid #30445e',borderRadius:9,padding:'9px 13px',color:'#fff'}}>Tabeldata</button>
                    <button onClick={()=>setMode('sql')} style={{background:mode==='sql'?'#6d4aff':'#18283d',border:'1px solid #30445e',borderRadius:9,padding:'9px 13px',color:'#fff'}}>SQL Editor</button>
                    <button onClick={()=>setMode('import')} style={{background:mode==='import'?'#6d4aff':'#18283d',border:'1px solid #30445e',borderRadius:9,padding:'9px 13px',color:'#fff'}}>Importér .sql</button>
                </div>
                {mode==='sql' ? <div>
                    <div style={{color:'#fff',fontWeight:700,fontSize:16,marginBottom:8}}>SQL Editor</div>
                    <textarea value={sql} onChange={e=>setSql(e.target.value)} spellCheck={false} style={{width:'100%',minHeight:220,resize:'vertical',boxSizing:'border-box',background:'#050b13',border:'1px solid #30445e',borderRadius:10,padding:14,color:'#dbeafe',fontFamily:'monospace',fontSize:13}} />
                    <div style={{display:'flex',gap:8,marginTop:10}}><button disabled={runningSql} onClick={runSql} style={{background:'#6d4aff',border:0,borderRadius:9,padding:'10px 16px',color:'#fff'}}>{runningSql?'Kører…':'▶ Kør SQL'}</button><button onClick={()=>setSql('')} style={{background:'#18283d',border:'1px solid #30445e',borderRadius:9,padding:'10px 16px',color:'#fff'}}>Ryd</button></div>
                    {sqlError && <div style={{marginTop:12,padding:12,borderRadius:9,background:'#3a1420',border:'1px solid #7f1d35',color:'#fecdd3',whiteSpace:'pre-wrap'}}>{sqlError}</div>}
                    {sqlResult && <div style={{marginTop:12,padding:12,borderRadius:9,background:'#0a1724',border:'1px solid #28405d',color:'#cbd5e1',overflowX:'auto'}}><b style={{color:'#86efac'}}>SQL udført</b> · {sqlResult.meta?.affected_rows ?? 0} påvirket · {sqlResult.meta?.duration_ms ?? 0} ms{sqlResult.data?.length>0 && <table style={{width:'100%',marginTop:12,borderCollapse:'collapse',fontSize:12}}><thead><tr>{Object.keys(sqlResult.data[0]).map(k=><th key={k} style={{textAlign:'left',padding:8,borderBottom:'1px solid #30445e'}}>{k}</th>)}</tr></thead><tbody>{sqlResult.data.map((r:any,i:number)=><tr key={i}>{Object.keys(r).map(k=><td key={k} style={{padding:8,borderBottom:'1px solid #1c2b3f',whiteSpace:'nowrap'}}>{r[k]===null?'NULL':String(r[k])}</td>)}</tr>)}</tbody></table>}</div>}
                </div> : mode==='import' ? <div>
                    <div style={{color:'#fff',fontWeight:700,fontSize:16}}>Importér SQL-fil</div><div style={{color:'#8294ad',fontSize:12,margin:'6px 0 16px'}}>Vælg en .sql-fil på op til 10 MB. Den køres kun mod denne database.</div>
                    <label style={{display:'block',padding:28,border:'1px dashed #3b526f',borderRadius:12,textAlign:'center',color:'#dbeafe',background:'#0a1422',cursor:'pointer'}}>{importing?'Importerer…':'Vælg .sql-fil'}<input disabled={importing} type="file" accept=".sql,text/sql,application/sql" onChange={e=>importSql(e.target.files?.[0])} style={{display:'none'}} /></label>
                    {sqlError && <div style={{marginTop:12,padding:12,borderRadius:9,background:'#3a1420',border:'1px solid #7f1d35',color:'#fecdd3',whiteSpace:'pre-wrap'}}>{sqlError}</div>}
                    {sqlResult && <div style={{marginTop:12,padding:12,borderRadius:9,background:'#0b2a21',border:'1px solid #166534',color:'#bbf7d0'}}>Import gennemført{sqlResult.meta?.filename?' · '+sqlResult.meta.filename:''}{sqlResult.meta?.duration_ms!=null?' · '+sqlResult.meta.duration_ms+' ms':''}</div>}
                </div> : !table ? <div style={{padding:30,textAlign:'center',color:'#8294ad'}}>{loading?'Indlæser…':'Vælg en tabel til venstre'}</div> : <>
                    <div style={{display:'flex',gap:8,flexWrap:'wrap',marginBottom:12}}>
                        <input value={search} onChange={e=>setSearch(e.target.value)} onKeyDown={e=>e.key==='Enter'&&loadRows()} placeholder="Søg i tabellen…" style={{flex:1,minWidth:180,background:'#0a1422',border:'1px solid #30445e',borderRadius:9,padding:'10px 12px',color:'#fff'}} />
                        <button onClick={()=>loadRows()} style={{background:'#0891b2',border:0,borderRadius:9,padding:'10px 15px',color:'#fff'}}>Søg</button>
                        <button onClick={add} style={{background:'#6d4aff',border:0,borderRadius:9,padding:'10px 15px',color:'#fff'}}>+ Ny række</button>
                    </div>
                    <div style={{overflowX:'auto'}}>
                        <table style={{width:'100%',borderCollapse:'collapse',fontSize:12,color:'#cbd5e1'}}>
                            <thead><tr>{columns.map(c=><th key={c.Field} style={{textAlign:'left',padding:10,borderBottom:'1px solid #30445e'}}>{c.Field}<small style={{display:'block',opacity:.45}}>{c.Type}</small></th>)}<th/></tr></thead>
                            <tbody>{rows.map((row,i)=><tr key={i}>{columns.map(c=><td key={c.Field} style={{padding:10,borderBottom:'1px solid #1c2b3f',whiteSpace:'nowrap',maxWidth:240,overflow:'hidden',textOverflow:'ellipsis'}}>{row[c.Field]===null?<i>NULL</i>:String(row[c.Field])}</td>)}<td style={{whiteSpace:'nowrap',padding:'7px 8px',borderBottom:'1px solid #1c2b3f'}}>
                                    <div style={{display:'flex',gap:6,alignItems:'center',justifyContent:'flex-end'}}>
                                        <button
                                            onClick={()=>edit(row)}
                                            title="Rediger række"
                                            aria-label="Rediger række"
                                            disabled={!primary}
                                            style={{display:'inline-flex',alignItems:'center',justifyContent:'center',gap:6,minWidth:74,height:32,padding:'0 10px',borderRadius:8,border:'1px solid #2563eb',background:'#172554',color:'#bfdbfe',fontSize:11,fontWeight:700,cursor:primary?'pointer':'not-allowed',opacity:primary?1:.45}}
                                        >
                                            <span style={{fontSize:13}}>✎</span><span>Rediger</span>
                                        </button>
                                        <button
                                            onClick={()=>remove(row)}
                                            title="Slet række"
                                            aria-label="Slet række"
                                            disabled={!primary}
                                            style={{display:'inline-flex',alignItems:'center',justifyContent:'center',gap:6,minWidth:62,height:32,padding:'0 10px',borderRadius:8,border:'1px solid #7f1d1d',background:'#3a1420',color:'#fecaca',fontSize:11,fontWeight:700,cursor:primary?'pointer':'not-allowed',opacity:primary?1:.45}}
                                        >
                                            <span style={{fontSize:13}}>🗑</span><span>Slet</span>
                                        </button>
                                    </div>
                                </td></tr>)}</tbody>
                        </table>
                    </div>
                </>}
            </main>
        </div>
    </div>;
};
