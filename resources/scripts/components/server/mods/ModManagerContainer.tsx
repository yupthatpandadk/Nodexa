import React,{useEffect,useState} from 'react';
import tw from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import {ServerContext} from '@/state/server';
import {Button} from '@/components/elements/button/index';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import {httpErrorToHuman} from '@/api/http';
import {FileObject} from '@/api/server/files/loadDirectory';
import {installedMods,installMod,ModrinthMod,searchMods,uninstallMod} from '@/api/server/mods/modManager';

const Card=tw.div`rounded-xl border border-neutral-700 bg-neutral-800 p-5 shadow-md`;
export default()=>{
 const id=ServerContext.useStoreState(s=>s.server.data!.id);
 const variables=ServerContext.useStoreState(s=>s.server.data!.variables);
 const image=ServerContext.useStoreState(s=>s.server.data!.dockerImage);
 const invocation=ServerContext.useStoreState(s=>s.server.data!.invocation);
 const val=(names:string[])=>{const v=variables.find(x=>names.includes(x.envVariable.toUpperCase()));return v?.serverValue||v?.defaultValue||undefined;};
 const configured=val(['MINECRAFT_VERSION','MC_VERSION','VERSION','SERVER_VERSION'])?.replace(/^v/i,'');
 const mc=configured&&configured.toLowerCase()!=='latest'?configured:undefined;
 const source=`${image} ${invocation} ${val(['SERVER_TYPE','SERVER_JARFILE','MOD_LOADER'])||''}`.toLowerCase();
 const loader=source.includes('neoforge')?'neoforge':source.includes('forge')?'forge':source.includes('quilt')?'quilt':source.includes('fabric')?'fabric':undefined;
 const [query,setQuery]=useState(''); const [mods,setMods]=useState<ModrinthMod[]>([]); const [files,setFiles]=useState<FileObject[]>([]);
 const [tab,setTab]=useState<'browse'|'installed'>('browse'); const [loading,setLoading]=useState(true); const [busy,setBusy]=useState<string|null>(null); const [error,setError]=useState('');
 const refresh=async()=>setFiles(await installedMods(id));
 const browse=async(q=query)=>{setLoading(true);setError('');try{setMods(await searchMods(q.trim(),mc,loader));}catch(e){setError(httpErrorToHuman(e));}finally{setLoading(false);}};
 useEffect(()=>{browse('');refresh();},[id]);
 const install=async(m:ModrinthMod)=>{setBusy(m.project_id);setError('');try{await installMod(id,m.project_id,mc,loader);await refresh();}catch(e){setError(httpErrorToHuman(e));}finally{setBusy(null);}};
 const remove=async(f:FileObject)=>{if(!window.confirm(`Afinstallér ${f.name}?`))return;setBusy(f.name);try{await uninstallMod(id,f.name);await refresh();}catch(e){setError(httpErrorToHuman(e));}finally{setBusy(null);}};
 return <ServerContentBlock title={'Mod Manager'}>
  <div css={tw`mb-6 rounded-xl border border-neutral-700 bg-neutral-800 p-5 shadow-lg`}>
   <div css={tw`flex flex-col gap-4 md:flex-row md:items-center md:justify-between`}>
    <div><h1 css={tw`text-xl font-bold text-white`}>Minecraft Mod Manager</h1><p css={tw`mt-1 text-sm text-neutral-400`}>Mods fra Modrinth filtreres automatisk til serverens mod-loader.</p>
     <div css={tw`mt-3 flex flex-wrap gap-2 text-xs`}><span css={tw`rounded-full bg-neutral-900 px-3 py-1 text-neutral-300`}>Minecraft: {mc||'Latest'}</span><span css={tw`rounded-full bg-neutral-900 px-3 py-1 text-cyan-300`}>Loader: {loader?loader.charAt(0).toUpperCase()+loader.slice(1):'Ukendt'}</span><span css={tw`rounded-full bg-neutral-900 px-3 py-1 text-neutral-300`}>Modrinth</span></div>
    </div>
    <div css={tw`flex rounded-lg bg-neutral-900 p-1`}><button css={[tw`rounded-md px-4 py-2 text-sm`,tab==='browse'?tw`bg-cyan-700 text-white`:tw`text-neutral-400`]} onClick={()=>setTab('browse')}>Find mods</button><button css={[tw`rounded-md px-4 py-2 text-sm`,tab==='installed'?tw`bg-cyan-700 text-white`:tw`text-neutral-400`]} onClick={()=>setTab('installed')}>Installeret ({files.length})</button></div>
   </div>
  </div>
  {!loader&&<div css={tw`mb-5 rounded-xl border border-yellow-700 bg-yellow-900 bg-opacity-20 p-4 text-sm text-yellow-200`}>Nodexa kunne ikke registrere Forge, NeoForge, Fabric eller Quilt på denne server. Mod Manager er derfor ikke klar til installation.</div>}
  {error&&<div css={tw`mb-5 rounded-xl border border-red-700 bg-red-900 bg-opacity-20 p-4 text-sm text-red-200`}>{error}</div>}
  {tab==='browse'?<><form css={tw`mb-5 flex flex-col gap-3 rounded-xl border border-neutral-700 bg-neutral-800 p-4 sm:flex-row`} onSubmit={e=>{e.preventDefault();browse();}}><div css={tw`flex-1`}><Input value={query} onChange={e=>setQuery(e.currentTarget.value)} placeholder={loader?`Søg efter ${loader} mods...`:'Søg efter mods...'}/></div><Button type={'submit'} disabled={!loader}>Søg mods</Button></form>
   {loading?<Spinner size={'large'} centered/>:<div css={tw`grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3`}>{mods.map(m=><Card key={m.project_id}><div css={tw`flex gap-4`}>{m.icon_url?<img src={m.icon_url} css={tw`h-14 w-14 rounded-xl object-cover`} alt={''}/>:<div css={tw`h-14 w-14 rounded-xl bg-neutral-700`}/>}<div css={tw`min-w-0`}><h2 css={tw`truncate font-bold text-white`}>{m.title}</h2><p css={tw`text-xs text-neutral-500`}>af {m.author} · {m.downloads.toLocaleString()} downloads</p></div></div><p css={tw`mt-4 h-10 overflow-hidden text-sm text-neutral-400`}>{m.description}</p><div css={tw`mt-4 flex justify-end border-t border-neutral-700 pt-4`}><Button size={Button.Sizes.Small} disabled={!loader||busy!==null} onClick={()=>install(m)}>{busy===m.project_id?'Installerer...':'Installér'}</Button></div></Card>)}</div>}</>
  :<div css={tw`space-y-3`}>{files.length?files.map(f=><Card key={f.key}><div css={tw`flex items-center justify-between gap-4`}><div><p css={tw`font-semibold text-white`}>{f.name}</p><p css={tw`text-xs text-neutral-500`}>{(f.size/1024/1024).toFixed(2)} MiB · /mods</p></div><Button.Danger size={Button.Sizes.Small} disabled={busy!==null} onClick={()=>remove(f)}>Afinstallér</Button.Danger></div></Card>):<Card><p css={tw`text-center text-sm text-neutral-400`}>Ingen mods fundet i /mods.</p></Card>}</div>}
 </ServerContentBlock>;
};
