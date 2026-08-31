<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nodexa</title>@viteReactRefresh @vite(['resources/js/main.tsx'])</head><body><div id="app"></div><script>
(function(){
 const token=localStorage.getItem('nodexa_panel_token'); if(!token)return;
 fetch('/api/me',{headers:{Authorization:'Bearer '+token,Accept:'application/json'}}).then(r=>r.ok?r.json():null).then(me=>{
  if(!me||!me.is_admin)return;
  const add=()=>{
   const nav=document.querySelector('aside nav'); if(!nav||document.getElementById('nodexa-db-hosts-link'))return false;
   const b=document.createElement('button'); b.id='nodexa-db-hosts-link'; b.textContent='Database Hosts'; b.onclick=()=>location.href='/admin/database-hosts';
   nav.appendChild(b); return true;
  };
  if(add())return; const o=new MutationObserver(()=>{if(add())o.disconnect()}); o.observe(document.getElementById('app'),{childList:true,subtree:true});
 }).catch(()=>{});
})();
</script></body></html>
