import axios from 'axios';
import http from '@/api/http';
import loadDirectory, { FileObject } from '@/api/server/files/loadDirectory';
import deleteFiles from '@/api/server/files/deleteFiles';

export interface ModrinthMod {
    project_id: string; title: string; description: string; author: string; downloads: number;
    icon_url: string | null; versions: string[]; categories: string[];
}
interface ModrinthVersion { version_number: string; version_type: 'release'|'beta'|'alpha'; loaders: string[]; files: { url:string; filename:string; primary:boolean }[]; }

const modrinth=axios.create({baseURL:'https://api.modrinth.com/v2',timeout:15000,headers:{Accept:'application/json'}});

export const searchMods=async(query:string,minecraftVersion?:string,loader?:string):Promise<ModrinthMod[]>=>{
    const facets:string[][]=[['project_type:mod']];
    if(minecraftVersion) facets.push([`versions:${minecraftVersion}`]);
    if(loader) facets.push([`categories:${loader}`]);
    const {data}=await modrinth.get('/search',{params:{query,facets:JSON.stringify(facets),limit:24,index:query?'relevance':'downloads'}});
    return data.hits||[];
};

const versions=async(projectId:string,minecraftVersion?:string,loader?:string):Promise<ModrinthVersion[]>=>{
    const params:Record<string,string>={};
    if(minecraftVersion) params.game_versions=JSON.stringify([minecraftVersion]);
    if(loader) params.loaders=JSON.stringify([loader]);
    const {data}=await modrinth.get(`/project/${projectId}/version`,{params});
    return data||[];
};

export const installMod=async(serverId:string,projectId:string,minecraftVersion?:string,loader?:string)=>{
    const list=await versions(projectId,minecraftVersion,loader);
    const version=list.find(v=>v.version_type==='release')||list[0];
    if(!version) throw new Error(`Ingen kompatibel ${loader||''} mod-version blev fundet.`);
    const file=version.files.find(f=>f.primary)||version.files[0];
    if(!file?.filename.toLowerCase().endsWith('.jar')) throw new Error('Mod-versionen indeholder ikke en gyldig JAR-fil.');
    await http.post(`/api/client/servers/${serverId}/files/pull`,{url:file.url,directory:'/mods',filename:file.filename,foreground:true});
    return file.filename;
};

export const installedMods=async(serverId:string):Promise<FileObject[]>=>{
    try{return (await loadDirectory(serverId,'/mods')).filter(f=>f.isFile&&f.name.toLowerCase().endsWith('.jar'));}catch{return [];}
};
export const uninstallMod=(serverId:string,filename:string)=>deleteFiles(serverId,'/mods',[filename]);
