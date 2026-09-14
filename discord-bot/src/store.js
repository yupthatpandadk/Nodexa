import fs from 'node:fs';import path from 'node:path';
const file=process.env.NODEXA_MODERATION_STORE||path.resolve('data/moderation.json');
function read(){try{return JSON.parse(fs.readFileSync(file,'utf8'))}catch{return {sequence:0,cases:[],warnings:{}}}}
function write(data){fs.mkdirSync(path.dirname(file),{recursive:true});const tmp=file+'.tmp';fs.writeFileSync(tmp,JSON.stringify(data,null,2));fs.renameSync(tmp,file)}
export function addCase(entry){const d=read();d.sequence=(d.sequence||0)+1;const item={id:`CASE-${String(d.sequence).padStart(6,'0')}`,created_at:new Date().toISOString(),...entry};d.cases.push(item);if(entry.type==='warn'){d.warnings[entry.user_id]??=[];d.warnings[entry.user_id].push(item)}write(d);return item}
export function warnings(userId,expiryDays=0){const d=read(),list=d.warnings[userId]||[];if(!expiryDays)return list;const min=Date.now()-expiryDays*86400000;return list.filter(x=>Date.parse(x.created_at)>=min)}
export function allCases(){return read().cases}
