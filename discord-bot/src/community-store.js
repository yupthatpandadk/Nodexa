import fs from 'node:fs';import path from 'node:path';
const stateFile=process.env.NODEXA_COMMUNITY_STORE||path.resolve('data/community.json');
const read=()=>{try{return JSON.parse(fs.readFileSync(stateFile,'utf8'))}catch{return {xp:{},afk:{},counting:{},invites:{},reminders:[]}}};
const write=d=>{fs.mkdirSync(path.dirname(stateFile),{recursive:true});const t=stateFile+'.tmp';fs.writeFileSync(t,JSON.stringify(d,null,2));fs.renameSync(t,stateFile)};
export function addXp(userId,min=10,max=20){const d=read(),gain=Math.floor(Math.random()*(Math.max(min,max)-Math.min(min,max)+1))+Math.min(min,max);d.xp[userId]=(d.xp[userId]||0)+gain;write(d);const xp=d.xp[userId],level=Math.floor(Math.sqrt(xp/100));return {xp,gain,level}}
export function setAfk(userId,reason){const d=read();d.afk[userId]={reason,at:Date.now()};write(d);return d.afk[userId]}
export function clearAfk(userId){const d=read(),old=d.afk[userId];delete d.afk[userId];write(d);return old}
export function getAfk(userId){return read().afk[userId]||null}
export function count(channelId,userId,value,start=1){const d=read();d.counting[channelId]??={value:start-1,last:null,streak:0};const s=d.counting[channelId],ok=value===s.value+1&&s.last!==userId;if(ok){s.value=value;s.last=userId;s.streak++;}else{s.value=start-1;s.last=null;s.streak=0}write(d);return {ok,...s}}
export function addReminder(userId,text,at){const d=read(),item={id:`REM-${Date.now()}`,userId,text,at};d.reminders.push(item);write(d);return item}
export function dueReminders(){const d=read(),now=Date.now(),due=d.reminders.filter(x=>x.at<=now);if(due.length){d.reminders=d.reminders.filter(x=>x.at>now);write(d)}return due}
