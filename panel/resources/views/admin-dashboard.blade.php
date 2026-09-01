<!doctype html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Administration · Nodexa</title>
<style>
:root{color-scheme:dark;--bg:#07090e;--panel:#10141d;--panel2:#0b0f16;--line:#252c3a;--text:#f4f6fb;--muted:#929cad;--purple:#765cff;--purple2:#986cff;--green:#43d6a2;--red:#fb7185}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 15% -10%,rgba(118,92,255,.22),transparent 32%),radial-gradient(circle at 90% 90%,rgba(152,108,255,.12),transparent 28%),var(--bg);color:var(--text);font:14px Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.hidden{display:none!important}.loading{min-height:100vh;display:grid;place-items:center;color:var(--muted)}.spinner{width:34px;height:34px;border:3px solid #262d3a;border-top-color:var(--purple);border-radius:50%;animation:spin .8s linear infinite;margin:auto auto 12px}@keyframes spin{to{transform:rotate(360deg)}}.login{min-height:100vh;display:grid;place-items:center;padding:22px}.login-card{width:min(470px,100%);border:1px solid var(--line);background:linear-gradient(180deg,rgba(17,22,32,.97),rgba(10,14,21,.97));border-radius:22px;padding:34px;box-shadow:0 30px 100px rgba(0,0,0,.38)}.brand{display:flex;align-items:center;gap:13px}.mark{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,var(--purple),var(--purple2));font-weight:950;font-size:22px;box-shadow:0 12px 36px rgba(118,92,255,.27)}.brand strong{font-size:21px;letter-spacing:.1em}.brand strong span{color:#9278ff}.brand small{display:block;color:#737d8f;font-size:10px;letter-spacing:.18em;text-transform:uppercase;margin-top:3px}.login-copy{border-top:1px solid var(--line);margin-top:24px;padding-top:25px}.login-copy h1{font-size:29px;margin:0 0 7px}.login-copy p{margin:0 0 24px;color:var(--muted);line-height:1.55}.field{display:block;margin:14px 0;color:#aeb6c5;font-size:12px;font-weight:750}.field input{display:block;width:100%;margin-top:7px;border:1px solid #2a3241;background:#080c12;color:white;border-radius:11px;padding:13px 14px;font:inherit;outline:none}.field input:focus{border-color:#7763e3;box-shadow:0 0 0 3px rgba(119,99,227,.12)}.btn{border:1px solid var(--line);background:#151a24;color:#fff;border-radius:11px;padding:12px 15px;font-weight:850;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}.btn.primary{width:100%;border:0;background:linear-gradient(135deg,var(--purple),var(--purple2));margin-top:8px}.btn:disabled{opacity:.55;cursor:not-allowed}.error{display:none;margin:15px 0 0;padding:12px 14px;border-radius:10px;background:#28131b;border:1px solid #633044;color:#ffb1c0;line-height:1.5}.error.show{display:block}.shell{min-height:100vh}.top{height:72px;border-bottom:1px solid var(--line);background:rgba(7,9,14,.9);backdrop-filter:blur(18px);display:flex;align-items:center;justify-content:space-between;padding:0 max(18px,calc((100vw - 1160px)/2));position:sticky;top:0;z-index:20}.top .brand .mark{width:36px;height:36px;border-radius:10px;font-size:16px}.top .brand strong{font-size:15px}.user{display:flex;align-items:center;gap:10px}.user-text{text-align:right}.user-text strong{display:block;font-size:12px}.user-text small{color:var(--muted);font-size:10px}.logout{padding:9px 11px}.wrap{width:min(1160px,calc(100% - 30px));margin:34px auto 80px}.eyebrow{font-size:10px;letter-spacing:.16em;color:#8c7cff;font-weight:900;text-transform:uppercase}.heading{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin:8px 0 25px}.heading h1{font-size:34px;margin:0 0 7px}.heading p{margin:0;color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}.card{border:1px solid var(--line);background:linear-gradient(180deg,#111620,#0d1119);border-radius:15px;padding:20px;text-decoration:none;color:inherit;min-height:168px;display:flex;flex-direction:column;transition:.18s ease}.card:hover{border-color:#4d456f;transform:translateY(-2px);box-shadow:0 18px 50px rgba(0,0,0,.23)}.icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:#1b1730;color:#b3a4ff;font-size:19px;margin-bottom:18px}.card strong{font-size:17px}.card p{color:var(--muted);font-size:12px;line-height:1.5;margin:7px 0 18px;flex:1}.card span{font-size:11px;color:#aa9cff;font-weight:850}.notice{margin-bottom:20px;border:1px solid #2a3342;background:#0c1119;border-radius:13px;padding:14px 16px;color:#a9b3c3;display:flex;justify-content:space-between;gap:15px;align-items:center}.notice b{color:#fff}@media(max-width:850px){.grid{grid-template-columns:1fr 1fr}.heading{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.login-card{padding:26px 20px}.grid{grid-template-columns:1fr}.wrap{margin-top:24px}.heading h1{font-size:28px}.top{height:64px}.user-text{display:none}.notice{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<div id="loading" class="loading"><div><div class="spinner"></div><div>Kontrollerer administratoradgang…</div></div></div>

<section id="login" class="login hidden">
<form id="loginForm" class="login-card">
  <div class="brand"><span class="mark">N</span><div><strong>NOD<span>EXA</span></strong><small>Administration</small></div></div>
  <div class="login-copy"><h1>Administrator login</h1><p>Log ind med din Nodexa administrator-konto for at åbne administrationspanelet.</p></div>
  <label class="field">E-mail eller brugernavn<input id="identifier" type="text" autocomplete="username" required></label>
  <label class="field">Adgangskode<input id="password" type="password" autocomplete="current-password" required></label>
  <div id="loginError" class="error"></div>
  <button id="loginButton" class="btn primary" type="submit">Log ind som administrator</button>
</form>
</section>

<section id="admin" class="shell hidden">
<header class="top">
  <div class="brand"><span class="mark">N</span><div><strong>NOD<span>EXA</span></strong><small>Admin Panel</small></div></div>
  <div class="user"><div class="user-text"><strong id="userName">Administrator</strong><small id="userEmail"></small></div><button id="logoutButton" class="btn logout" type="button">Log ud</button></div>
</header>
<main class="wrap">
  <div class="eyebrow">Nodexa Administration</div>
  <div class="heading"><div><h1>Admin Panel</h1><p>Administrer hosting-platformen, Nodes, servere og storefronts fra ét sted.</p></div><a class="btn" href="/">Kundeportal</a></div>
  <div class="notice"><div><b>Administratoradgang aktiv</b><br><span>Du er logget ind med en konto, der har administratorrettigheder.</span></div><span id="adminEmail"></span></div>
  <div class="grid">
    <a class="card" href="/admin/servers/create"><div class="icon">▣</div><strong>Opret server</strong><p>Provisionér en ny game server på en Nodexa Node og vælg ejer og ressourcer.</p><span>Åbn →</span></a>
    <a class="card" href="/admin/nodes/setup"><div class="icon">◇</div><strong>Node Setup</strong><p>Opret og konfigurer nye Nodes, Agent, HTTPS, firewall og database-host.</p><span>Åbn →</span></a>
    <a class="card" href="/admin/database-hosts"><div class="icon">◫</div><strong>Database Hosts</strong><p>Administrer MariaDB/MySQL hosts, credentials og forbindelsestest.</p><span>Åbn →</span></a>
    <a class="card" href="/admin/storefronts"><div class="icon">◆</div><strong>Storefronts</strong><p>Administrer multisite storefronts, domæner, branding og produkter.</p><span>Åbn →</span></a>
    <a class="card" href="/admin/errors"><div class="icon">!</div><strong>Fejl & diagnostik</strong><p>Se Node-, Agent-, database- og systemfejl og kør diagnostiske scanninger.</p><span>Åbn →</span></a>
    <a class="card" href="/admin/update"><div class="icon">↻</div><strong>Opdater Nodexa</strong><p>Tjek GitHub for nye versioner og start en sikker Nodexa-opdatering.</p><span>Åbn →</span></a>
  </div>
</main>
</section>

<script>
const TOKEN_KEY='nodexa_panel_token';
const loading=document.getElementById('loading');
const loginView=document.getElementById('login');
const adminView=document.getElementById('admin');
const loginForm=document.getElementById('loginForm');
const loginError=document.getElementById('loginError');
const loginButton=document.getElementById('loginButton');
const logoutButton=document.getElementById('logoutButton');
const headers=token=>({Authorization:`Bearer ${token}`,Accept:'application/json','Content-Type':'application/json'});
function showLogin(message=''){
  loading.classList.add('hidden');adminView.classList.add('hidden');loginView.classList.remove('hidden');
  loginError.textContent=message;loginError.classList.toggle('show',Boolean(message));
}
function showAdmin(user){
  loading.classList.add('hidden');loginView.classList.add('hidden');adminView.classList.remove('hidden');
  const name=user?.name||user?.username||user?.email||'Administrator';
  document.getElementById('userName').textContent=name;
  document.getElementById('userEmail').textContent=user?.email||'';
  document.getElementById('adminEmail').textContent=user?.email||'';
}
async function parseJson(response){try{return await response.json()}catch{return {}}}
async function verify(token){
  if(!token)return showLogin();
  try{
    const response=await fetch('/api/me',{headers:headers(token),signal:AbortSignal.timeout(5000)});
    const user=await parseJson(response);
    if(!response.ok){localStorage.removeItem(TOKEN_KEY);return showLogin('Din session er udløbet. Log ind igen.');}
    if(!user?.is_admin)return showLogin('Denne konto har ikke administratorrettigheder.');
    showAdmin(user);
  }catch{showLogin('Kunne ikke kontakte login-systemet. Prøv igen.');}
}
loginForm.addEventListener('submit',async event=>{
  event.preventDefault();loginError.classList.remove('show');loginButton.disabled=true;loginButton.textContent='Logger ind…';
  const identifier=document.getElementById('identifier').value.trim();
  const password=document.getElementById('password').value;
  try{
    const response=await fetch('/api/login',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({login:identifier,password}),signal:AbortSignal.timeout(8000)});
    const data=await parseJson(response);
    if(!response.ok)throw new Error(data?.message||'Login mislykkedes.');
    if(!data?.user?.is_admin)throw new Error('Denne konto har ikke administratorrettigheder.');
    localStorage.setItem(TOKEN_KEY,data.token);
    showAdmin(data.user);
  }catch(error){loginError.textContent=error?.message||'Kunne ikke logge ind.';loginError.classList.add('show');}
  finally{loginButton.disabled=false;loginButton.textContent='Log ind som administrator';}
});
logoutButton.addEventListener('click',async()=>{
  const token=localStorage.getItem(TOKEN_KEY);
  try{if(token)await fetch('/api/logout',{method:'POST',headers:headers(token),signal:AbortSignal.timeout(4000)});}catch{}
  localStorage.removeItem(TOKEN_KEY);showLogin();
});
verify(localStorage.getItem(TOKEN_KEY));
</script>
</body>
</html>
