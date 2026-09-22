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

    const base = `/api/client/servers/${uuid}/databases/${database.id}/browser`;
    const loadTables = () => { setLoading(true); http.get(`${base}/tables`).then(r => setTables(r.data.data || [])).finally(() => setLoading(false)); };
    const loadRows = (name = table) => {
        if (!name) return;
        setLoading(true);
        http.get(`${base}/tables/${encodeURIComponent(name)}`, { params: { search } }).then(r => {
            setRows(r.data.data || []); setColumns(r.data.meta?.columns || []); setPrimary(r.data.meta?.primary_key || null);
        }).finally(() => setLoading(false));
    };
    useEffect(loadTables, []);
    useEffect(() => { if (table) loadRows(table); }, [table]);

    const edit = async (row: any) => {
        if (!primary) return alert('Tabellen har ingen primary key og kan derfor ikke redigeres sikkert.');
        const values: any = {};
        for (const c of columns) {
            const v = window.prompt(c.Field, row[c.Field] == null ? '' : String(row[c.Field]));
            if (v === null) return;
            values[c.Field] = v;
        }
        await http.put(`${base}/tables/${encodeURIComponent(table)}/rows`, { key: primary, key_value: row[primary], values });
        loadRows();
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
        <div style={{background:'#101c2d',border:'1px solid #243650',borderRadius:16,padding:18}}>
            <button onClick={onBack} style={{background:'#18283d',border:'1px solid #31445e',borderRadius:9,padding:'9px 13px',color:'#dbeafe'}}>← Databaser</button>
            <div style={{marginTop:14,fontSize:22,fontWeight:700,color:'#fff'}}>{database.name}</div>
            <div style={{color:'#8294ad',fontSize:12,marginTop:4}}>{database.connectionString} · Database Manager</div>
        </div>
        <div style={{display:'grid',gridTemplateColumns:'minmax(180px,240px) minmax(0,1fr)',gap:16}}>
            <aside style={{background:'#101c2d',border:'1px solid #243650',borderRadius:16,padding:12}}>
                <div style={{fontWeight:700,color:'#fff',padding:8}}>Tabeller</div>
                {tables.map(t => <button key={t.name} onClick={()=>setTable(t.name)} style={{width:'100%',textAlign:'left',marginTop:5,padding:10,borderRadius:9,border:'1px solid '+(table===t.name?'#22d3ee':'#26384f'),background:table===t.name?'#123149':'#0c1727',color:'#dbeafe'}}>{t.name}<small style={{display:'block',opacity:.55}}>{t.rows} rækker</small></button>)}
            </aside>
            <main style={{background:'#101c2d',border:'1px solid #243650',borderRadius:16,padding:14,minWidth:0}}>
                {!table ? <div style={{padding:30,textAlign:'center',color:'#8294ad'}}>{loading?'Indlæser…':'Vælg en tabel til venstre'}</div> : <>
                    <div style={{display:'flex',gap:8,flexWrap:'wrap',marginBottom:12}}>
                        <input value={search} onChange={e=>setSearch(e.target.value)} onKeyDown={e=>e.key==='Enter'&&loadRows()} placeholder="Søg i tabellen…" style={{flex:1,minWidth:180,background:'#0a1422',border:'1px solid #30445e',borderRadius:9,padding:'10px 12px',color:'#fff'}} />
                        <button onClick={()=>loadRows()} style={{background:'#0891b2',border:0,borderRadius:9,padding:'10px 15px',color:'#fff'}}>Søg</button>
                        <button onClick={add} style={{background:'#6d4aff',border:0,borderRadius:9,padding:'10px 15px',color:'#fff'}}>+ Ny række</button>
                    </div>
                    <div style={{overflowX:'auto'}}>
                        <table style={{width:'100%',borderCollapse:'collapse',fontSize:12,color:'#cbd5e1'}}>
                            <thead><tr>{columns.map(c=><th key={c.Field} style={{textAlign:'left',padding:10,borderBottom:'1px solid #30445e'}}>{c.Field}<small style={{display:'block',opacity:.45}}>{c.Type}</small></th>)}<th/></tr></thead>
                            <tbody>{rows.map((row,i)=><tr key={i}>{columns.map(c=><td key={c.Field} style={{padding:10,borderBottom:'1px solid #1c2b3f',whiteSpace:'nowrap',maxWidth:240,overflow:'hidden',textOverflow:'ellipsis'}}>{row[c.Field]===null?<i>NULL</i>:String(row[c.Field])}</td>)}<td style={{whiteSpace:'nowrap'}}><button onClick={()=>edit(row)}>✎</button> <button onClick={()=>remove(row)}>🗑</button></td></tr>)}</tbody>
                        </table>
                    </div>
                </>}
            </main>
        </div>
    </div>;
};
