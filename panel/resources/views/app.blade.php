<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Nodexa</title>
    @viteReactRefresh
    @vite(['resources/js/main.tsx'])
    <style>
        html,body{margin:0;min-height:100%;background:#0b0d12}
        #nodexa-boot-fallback{position:fixed;inset:0;z-index:2147483647;display:grid;min-height:100vh;place-items:center;padding:24px;background:#0b0d12;color:#eef1f7;font-family:Inter,ui-sans-serif,system-ui,sans-serif;box-sizing:border-box}
        #nodexa-boot-fallback .box{width:min(560px,100%);padding:28px;border:1px solid #2a3140;border-radius:18px;background:#11151d;box-shadow:0 30px 100px rgba(0,0,0,.45);box-sizing:border-box}
        #nodexa-boot-fallback h1{margin:0 0 10px;font-size:24px}
        #nodexa-boot-fallback p{color:#9aa4b5;line-height:1.55}
        #nodexa-boot-fallback code{display:none;margin-top:14px;padding:12px;border-radius:10px;background:#090b0f;color:#f2a1b4;white-space:pre-wrap;word-break:break-word}
        #nodexa-boot-fallback button{display:none;margin-top:16px;padding:10px 14px;border:1px solid #786cff;border-radius:9px;background:#695cff;color:white;font:inherit;cursor:pointer}
        #nodexa-boot-fallback.failed code,#nodexa-boot-fallback.failed button{display:block}
        .nodexa-spinner{width:28px;height:28px;border:3px solid #2b3140;border-top-color:#786cff;border-radius:50%;animation:nodexa-spin .8s linear infinite;margin-bottom:16px}
        @keyframes nodexa-spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body>
<div id="app"></div>
<div id="nodexa-boot-fallback">
    <div class="box">
        <div class="nodexa-spinner"></div>
        <h1 data-nodexa-title>Nodexa indlæses…</h1>
        <p data-nodexa-description>Kontrolpanelet starter. Det tager normalt kun et øjeblik.</p>
        <code data-nodexa-error>Frontend bundle blev ikke indlæst eller React startede ikke inden for tidsgrænsen.</code>
        <button type="button" onclick="location.reload()">Genindlæs</button>
    </div>
</div>
<noscript><div style="position:fixed;inset:0;z-index:2147483647;padding:24px;color:white;background:#0b0d12">Nodexa kræver JavaScript.</div></noscript>
<script>
(function(){
 const fallback=document.getElementById('nodexa-boot-fallback');
 const errorBox=fallback&&fallback.querySelector('[data-nodexa-error]');
 const title=fallback&&fallback.querySelector('[data-nodexa-title]');
 const description=fallback&&fallback.querySelector('[data-nodexa-description]');
 const fail=(message)=>{
  if(!fallback)return;
  fallback.classList.add('failed');
  const spinner=fallback.querySelector('.nodexa-spinner'); if(spinner)spinner.remove();
  if(title)title.textContent='Nodexa kunne ikke indlæse frontenden';
  if(description)description.textContent='Selve webserveren svarer, men React-frontenden kunne ikke starte. Den tekniske fejl vises nedenfor.';
  if(errorBox&&message)errorBox.textContent=String(message);
 };
 const booted=()=>{ if(fallback)fallback.remove(); };
 window.addEventListener('nodexa:booted',booted,{once:true});
 if(window.__NODEXA_BOOTED__)booted();
 setTimeout(()=>{if(!window.__NODEXA_BOOTED__)fail(errorBox?.textContent)},5000);
 window.addEventListener('error',e=>{
  const source=e.filename?`\n${e.filename}${e.lineno?':'+e.lineno:''}`:'';
  fail((e.message||'JavaScript/frontend resource error')+source);
 });
 window.addEventListener('unhandledrejection',e=>{
  const r=e.reason;
  fail(r&&r.message?String(r.message):String(r||'Unhandled promise rejection'));
 });

 const token=localStorage.getItem('nodexa_panel_token');
 if(!token)return;
 const headers={Authorization:'Bearer '+token,Accept:'application/json','Content-Type':'application/json'};
 const report=(payload)=>fetch('/api/system-errors/client',{method:'POST',headers,body:JSON.stringify(payload)}).catch(()=>{});
 window.addEventListener('error',e=>report({message:e.message||'Ukendt JavaScript-fejl',source:e.filename||null,line:e.lineno||null,column:e.colno||null,stack:e.error&&e.error.stack?String(e.error.stack):null,url:location.href}));
 window.addEventListener('unhandledrejection',e=>{const r=e.reason;report({message:r&&r.message?String(r.message):String(r||'Unhandled promise rejection'),stack:r&&r.stack?String(r.stack):null,url:location.href})});

 fetch('/api/me',{headers}).then(r=>r.ok?r.json():null).then(me=>{
  if(!me||!me.is_admin)return;
  let updateAvailable=false;
  const add=()=>{
   const nav=document.querySelector('aside nav'); if(!nav)return false;
   if(!document.getElementById('nodexa-create-server-link')){
    const c=document.createElement('button'); c.id='nodexa-create-server-link'; c.textContent='Opret server'; c.onclick=()=>location.href='/admin/servers/create'; nav.appendChild(c);
   }
   if(!document.getElementById('nodexa-node-setup-link')){
    const n=document.createElement('button'); n.id='nodexa-node-setup-link'; n.textContent='Node Setup'; n.onclick=()=>location.href='/admin/nodes/setup'; nav.appendChild(n);
   }
   if(!document.getElementById('nodexa-db-hosts-link')){
    const b=document.createElement('button'); b.id='nodexa-db-hosts-link'; b.textContent='Database Hosts'; b.onclick=()=>location.href='/admin/database-hosts'; nav.appendChild(b);
   }
   if(!document.getElementById('nodexa-storefronts-link')){
    const s=document.createElement('button'); s.id='nodexa-storefronts-link'; s.textContent='Storefronts'; s.onclick=()=>location.href='/admin/storefronts'; nav.appendChild(s);
   }
   if(!document.getElementById('nodexa-errors-link')){
    const e=document.createElement('button'); e.id='nodexa-errors-link'; e.textContent='Fejl'; e.onclick=()=>location.href='/admin/errors'; nav.appendChild(e);
   }
   let u=document.getElementById('nodexa-update-link');
   if(!u){u=document.createElement('button');u.id='nodexa-update-link';u.onclick=()=>location.href='/admin/update';nav.appendChild(u)}
   u.textContent=updateAvailable?'Opdatering tilgængelig •':'Opdateringer';
   if(updateAvailable){u.style.color='#c4b5fd';u.style.fontWeight='700'}
   return true;
  };
  const app=document.getElementById('app');
  if(app){const observer=new MutationObserver(()=>add());observer.observe(app,{childList:true,subtree:true})}
  add();
  fetch('/api/admin/update/check',{headers}).then(r=>r.ok?r.json():null).then(d=>{if(!d)return;updateAvailable=!!d.available;add()}).catch(()=>{});
 }).catch(()=>{});
})();
</script>
</body>
</html>