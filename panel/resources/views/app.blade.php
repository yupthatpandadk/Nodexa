<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nodexa</title>@viteReactRefresh @vite(['resources/js/main.tsx'])</head><body><div id="app"></div><script>
(function(){
 const token=localStorage.getItem('nodexa_panel_token'); if(!token)return;
 const headers={Authorization:'Bearer '+token,Accept:'application/json','Content-Type':'application/json'};
 fetch('/api/me',{headers}).then(r=>r.ok?r.json():null).then(me=>{
  if(!me||!me.is_admin)return;
  const add=()=>{
   const nav=document.querySelector('aside nav'); if(!nav)return false;
   if(!document.getElementById('nodexa-db-hosts-link')){
    const b=document.createElement('button'); b.id='nodexa-db-hosts-link'; b.textContent='Database Hosts'; b.onclick=()=>location.href='/admin/database-hosts'; nav.appendChild(b);
   }
   if(!document.getElementById('nodexa-errors-link')){
    const e=document.createElement('button'); e.id='nodexa-errors-link'; e.textContent='Fejl'; e.onclick=()=>location.href='/admin/errors'; nav.appendChild(e);
   }
   return true;
  };
  if(add())return; const o=new MutationObserver(()=>{if(add())o.disconnect()}); o.observe(document.getElementById('app'),{childList:true,subtree:true});
 }).catch(()=>{});

 const report=(payload)=>fetch('/api/system-errors/client',{method:'POST',headers,body:JSON.stringify(payload)}).catch(()=>{});
 window.addEventListener('error',e=>report({message:e.message||'Ukendt JavaScript-fejl',source:e.filename||null,line:e.lineno||null,column:e.colno||null,stack:e.error&&e.error.stack?String(e.error.stack):null,url:location.href}));
 window.addEventListener('unhandledrejection',e=>{const r=e.reason;report({message:r&&r.message?String(r.message):String(r||'Unhandled promise rejection'),stack:r&&r.stack?String(r.stack):null,url:location.href})});
})();
</script></body></html>
