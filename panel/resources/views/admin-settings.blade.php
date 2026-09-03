<!doctype html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Kontrolpanel · Nodexa</title>
<style>
:root{color-scheme:dark;--bg:#07090e;--panel:#10141d;--panel2:#0b0f16;--line:#252c3a;--text:#f4f6fb;--muted:#929cad;--purple:#765cff;--purple2:#986cff;--green:#43d6a2;--red:#fb7185;--yellow:#f8c75c}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 14% -10%,rgba(118,92,255,.18),transparent 30%),radial-gradient(circle at 90% 90%,rgba(152,108,255,.09),transparent 28%),var(--bg);color:var(--text);font:14px Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.hidden{display:none!important}.loading{min-height:100vh;display:grid;place-items:center;color:var(--muted)}.spinner{width:34px;height:34px;border:3px solid #262d3a;border-top-color:var(--purple);border-radius:50%;animation:spin .8s linear infinite;margin:auto auto 12px}@keyframes spin{to{transform:rotate(360deg)}}.top{height:72px;border-bottom:1px solid var(--line);background:rgba(7,9,14,.92);backdrop-filter:blur(18px);display:flex;align-items:center;justify-content:space-between;padding:0 max(18px,calc((100vw - 1180px)/2));position:sticky;top:0;z-index:20}.brand{display:flex;align-items:center;gap:12px}.mark{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(135deg,var(--purple),var(--purple2));font-weight:950;font-size:17px;box-shadow:0 12px 34px rgba(118,92,255,.24)}.brand strong{font-size:15px;letter-spacing:.1em}.brand small{display:block;color:#737d8f;font-size:9px;letter-spacing:.18em;text-transform:uppercase;margin-top:2px}.top-actions{display:flex;gap:9px}.btn{border:1px solid var(--line);background:#151a24;color:#fff;border-radius:10px;padding:10px 13px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px}.btn:hover{border-color:#4e4771}.btn.primary{border:0;background:linear-gradient(135deg,var(--purple),var(--purple2));box-shadow:0 10px 28px rgba(118,92,255,.2)}.btn:disabled{opacity:.55;cursor:not-allowed}.wrap{width:min(1180px,calc(100% - 30px));margin:32px auto 80px}.eyebrow{font-size:10px;letter-spacing:.17em;color:#8c7cff;font-weight:900;text-transform:uppercase}.heading{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;margin:8px 0 26px}.heading h1{font-size:32px;margin:0 0 7px}.heading p{margin:0;color:var(--muted);line-height:1.5}.layout{display:grid;grid-template-columns:230px minmax(0,1fr);gap:18px}.side{position:sticky;top:92px;height:max-content;border:1px solid var(--line);background:linear-gradient(180deg,#10151e,#0c1017);border-radius:15px;padding:9px}.side a{display:flex;align-items:center;gap:10px;color:#a9b2c2;text-decoration:none;padding:11px 12px;border-radius:9px;font-size:12px;font-weight:760}.side a:hover,.side a.active{background:#181629;color:#c8bdff}.side .dot{width:7px;height:7px;border-radius:50%;background:#4a5262}.side a.active .dot{background:#8f76ff;box-shadow:0 0 0 4px rgba(143,118,255,.1)}.stack{display:grid;gap:16px}.card{border:1px solid var(--line);background:linear-gradient(180deg,#111620,#0d1119);border-radius:15px;overflow:hidden}.card-head{padding:18px 20px;border-bottom:1px solid var(--line);display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.card-head h2{font-size:16px;margin:0 0 5px}.card-head p{color:var(--muted);font-size:12px;line-height:1.5;margin:0}.badge{font-size:9px;letter-spacing:.08em;text-transform:uppercase;color:#ad9fff;background:#211c39;border:1px solid #3c315f;border-radius:999px;padding:6px 8px;font-weight:900;white-space:nowrap}.card-body{padding:20px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:15px}.field{display:block;color:#abb4c3;font-size:11px;font-weight:800}.field span{display:block;margin:0 0 7px}.field small{display:block;color:#6f798a;font-weight:500;line-height:1.45;margin-top:7px}.field input,.field select{width:100%;border:1px solid #2a3241;background:#080c12;color:white;border-radius:10px;padding:12px 13px;font:inherit;outline:none}.field input:focus,.field select:focus{border-color:#7763e3;box-shadow:0 0 0 3px rgba(119,99,227,.12)}.field.wide{grid-column:1/-1}.info{border:1px solid #293243;background:#0b111a;border-radius:11px;padding:13px 14px;color:#929cad;font-size:11px;line-height:1.55;margin-top:15px}.info b{color:#dfe4ee}.footer{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:17px 20px;border-top:1px solid var(--line);background:#0c1017}.status{font-size:11px;color:var(--muted)}.status.ok{color:var(--green)}.status.bad{color:#ff9cae}.alert{display:none;border-radius:11px;padding:13px 15px;margin-bottom:16px;line-height:1.5}.alert.show{display:block}.alert.error{background:#28131b;border:1px solid #633044;color:#ffb1c0}.alert.success{background:#0d2821;border:1px solid #235a49;color:#8ce8c8}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}.summary-card{border:1px solid var(--line);background:#0d121a;border-radius:13px;padding:15px}.summary-card small{display:block;color:#788295;font-size:9px;letter-spacing:.1em;text-transform:uppercase;margin-bottom:7px}.summary-card strong{font-size:13px;word-break:break-word}.muted{color:var(--muted)}@media(max-width:820px){.layout{grid-template-columns:1fr}.side{position:static;display:flex;overflow:auto}.side a{white-space:nowrap}.summary{grid-template-columns:1fr}.grid2{grid-template-columns:1fr}.field.wide{grid-column:auto}.heading{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.top{height:64px}.brand small{display:none}.wrap{margin-top:23px}.heading h1{font-size:27px}.top-actions .back-label{display:none}.card-head{padding:16px}.card-body{padding:16px}.footer{align-items:stretch;flex-direction:column}.footer .btn{width:100%}}
</style>
</head>
<body>
<div id="loading" class="loading"><div><div class="spinner"></div><div>Indlæser kontrolpanel…</div></div></div>
<section id="content" class="hidden">
<header class="top">
  <div class="brand"><span class="mark">N</span><div><strong>NODEXA</strong><small>Control Panel</small></div></div>
  <div class="top-actions"><a class="btn" href="/admin">← <span class="back-label">Tilbage til admin</span></a></div>
</header>
<main class="wrap">
  <div class="eyebrow">Administration / System</div>
  <div class="heading"><div><h1>Kontrolpanel</h1><p>Konfigurer Nodexa globalt. Indstillingerne gemmes centralt og bruges direkte af Laravel og nye Node-konfigurationer.</p></div></div>
  <div id="error" class="alert error"></div><div id="success" class="alert success"></div>
  <div class="summary">
    <div class="summary-card"><small>Panel</small><strong id="summaryName">Nodexa</strong></div>
    <div class="summary-card"><small>Adresse</small><strong id="summaryUrl" class="muted">–</strong></div>
    <div class="summary-card"><small>Lokalisering</small><strong id="summaryLocale" class="muted">–</strong></div>
  </div>
  <div class="layout">
    <nav class="side">
      <a class="active" href="#general"><span class="dot"></span>Generelt</a>
      <a href="#branding"><span class="dot"></span>Branding</a>
      <a href="#localization"><span class="dot"></span>Lokalisering</a>
    </nav>
    <form id="settingsForm" class="stack">
      <section id="general" class="card">
        <div class="card-head"><div><h2>Generelle indstillinger</h2><p>Panelidentitet og den offentlige URL som Nodes og systemlinks skal bruge.</p></div><span class="badge">Global</span></div>
        <div class="card-body grid2">
          <label class="field"><span>Panelnavn</span><input id="panel_name" maxlength="120" required><small>Navnet på kontrolpanelet, fx Nodexa.</small></label>
          <label class="field"><span>Panel URL</span><input id="panel_url" type="url" placeholder="https://panel.example.com" maxlength="255" required><small>Bruges bl.a. i nye Nodexa Agent/Node-konfigurationer.</small></label>
        </div>
        <div class="footer"><span class="status">Ændringer gælder på nye requests efter gem.</span><button class="btn primary save" type="submit">Gem indstillinger</button></div>
      </section>

      <section id="branding" class="card">
        <div class="card-head"><div><h2>Organisation & support</h2><p>Grundlæggende branding-information til Nodexa-platformen.</p></div><span class="badge">Branding</span></div>
        <div class="card-body grid2">
          <label class="field"><span>Virksomhedsnavn</span><input id="company_name" maxlength="120" placeholder="Nodexa Hosting"><small>Navn på virksomheden eller hosting-brandet.</small></label>
          <label class="field"><span>Support e-mail</span><input id="support_email" type="email" maxlength="255" placeholder="support@example.com"><small>Central supportadresse til kommende mail- og supportfunktioner.</small></label>
          <div class="info field wide"><b>Nodexa-design bevares.</b> Denne opsætning følger samme idé som et professionelt game-hosting adminpanel, men ændrer ikke Nodexa til Pterodactyl-branding eller kopierer Pterodactyl-kode.</div>
        </div>
        <div class="footer"><span class="status">Branding-data gemmes centralt i databasen.</span><button class="btn primary save" type="submit">Gem indstillinger</button></div>
      </section>

      <section id="localization" class="card">
        <div class="card-head"><div><h2>Lokalisering</h2><p>Standard timezone og sprog for backend og tidsbaserede funktioner.</p></div><span class="badge">Runtime</span></div>
        <div class="card-body grid2">
          <label class="field"><span>Timezone</span><select id="timezone" required></select><small>Påvirker Laravel runtime og planlagte tidsfunktioner.</small></label>
          <label class="field"><span>Standardsprog</span><select id="locale" required></select><small>Standard locale for platformen. Flere sprog kan tilføjes senere.</small></label>
        </div>
        <div class="footer"><span id="saveStatus" class="status">Ingen ikke-gemte ændringer.</span><button id="saveButton" class="btn primary" type="submit">Gem alle indstillinger</button></div>
      </section>
    </form>
  </div>
</main>
</section>
<script>
const TOKEN_KEY='nodexa_panel_token';
const token=localStorage.getItem(TOKEN_KEY);
const loading=document.getElementById('loading');
const content=document.getElementById('content');
const errorBox=document.getElementById('error');
const successBox=document.getElementById('success');
const form=document.getElementById('settingsForm');
const saveButton=document.getElementById('saveButton');
const saveStatus=document.getElementById('saveStatus');
const headers=()=>({Authorization:`Bearer ${token}`,Accept:'application/json','Content-Type':'application/json'});
const fields=['panel_name','company_name','panel_url','timezone','locale','support_email'];
function showError(message){errorBox.textContent=message;errorBox.classList.add('show');successBox.classList.remove('show');window.scrollTo({top:0,behavior:'smooth'});}
function showSuccess(message){successBox.textContent=message;successBox.classList.add('show');errorBox.classList.remove('show');window.scrollTo({top:0,behavior:'smooth'});}
async function json(response){try{return await response.json()}catch{return {}}}
function updateSummary(){
  document.getElementById('summaryName').textContent=document.getElementById('panel_name').value||'Nodexa';
  document.getElementById('summaryUrl').textContent=document.getElementById('panel_url').value||'–';
  const locale=document.getElementById('locale');const timezone=document.getElementById('timezone');
  document.getElementById('summaryLocale').textContent=`${locale.options[locale.selectedIndex]?.text||'–'} · ${timezone.value||'–'}`;
}
async function load(){
  if(!token){location.href='/admin';return;}
  try{
    const meResponse=await fetch('/api/me',{headers:headers(),signal:AbortSignal.timeout(6000)});const me=await json(meResponse);
    if(!meResponse.ok||!me?.is_admin){location.href='/admin';return;}
    const response=await fetch('/api/admin/settings',{headers:headers(),signal:AbortSignal.timeout(8000)});const data=await json(response);
    if(!response.ok)throw new Error(data?.message||'Kunne ikke hente kontrolpanel-indstillinger.');
    const timezone=document.getElementById('timezone');timezone.innerHTML=(data.timezones||[]).map(value=>`<option value="${String(value).replaceAll('"','&quot;')}">${value}</option>`).join('');
    const locale=document.getElementById('locale');locale.innerHTML=(data.locales||[]).map(item=>`<option value="${item.value}">${item.label}</option>`).join('');
    for(const key of fields){const element=document.getElementById(key);if(element)element.value=data.settings?.[key]??'';}
    updateSummary();loading.classList.add('hidden');content.classList.remove('hidden');
  }catch(error){loading.innerHTML=`<div style="max-width:540px;padding:25px;text-align:center;color:#ffb1c0">${error?.message||'Kontrolpanel kunne ikke indlæses.'}<br><br><a href="/admin" style="color:#ad9fff">Tilbage til admin</a></div>`;}
}
form.addEventListener('input',()=>{saveStatus.textContent='Du har ikke-gemte ændringer.';saveStatus.className='status';updateSummary();});
form.addEventListener('submit',async event=>{
  event.preventDefault();errorBox.classList.remove('show');successBox.classList.remove('show');
  saveButton.disabled=true;document.querySelectorAll('.save').forEach(button=>button.disabled=true);saveButton.textContent='Gemmer…';
  const payload={};for(const key of fields)payload[key]=document.getElementById(key).value.trim();
  try{
    const response=await fetch('/api/admin/settings',{method:'PUT',headers:headers(),body:JSON.stringify(payload),signal:AbortSignal.timeout(10000)});const data=await json(response);
    if(!response.ok){const validation=data?.errors?Object.values(data.errors).flat().join(' '):'';throw new Error(validation||data?.message||'Indstillingerne kunne ikke gemmes.');}
    saveStatus.textContent='Alle ændringer er gemt.';saveStatus.className='status ok';showSuccess(data?.message||'Kontrolpanel-indstillingerne er gemt.');updateSummary();
  }catch(error){saveStatus.textContent='Kunne ikke gemme ændringer.';saveStatus.className='status bad';showError(error?.message||'Indstillingerne kunne ikke gemmes.');}
  finally{saveButton.disabled=false;document.querySelectorAll('.save').forEach(button=>button.disabled=false);saveButton.textContent='Gem alle indstillinger';}
});
document.querySelectorAll('.side a').forEach(link=>link.addEventListener('click',()=>{document.querySelectorAll('.side a').forEach(x=>x.classList.remove('active'));link.classList.add('active');}));
load();
</script>
</body>
</html>
