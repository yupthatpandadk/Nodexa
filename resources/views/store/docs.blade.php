@extends('store.layout')

@section('title','Documentation')
@section('description','Nodexa Documentation — guides til servere, SFTP, databaser, backups, sikkerhed, API og fejlfinding.')

@push('styles')
<style>
.docsHero{padding:64px 0 34px;background:radial-gradient(720px 320px at 50% -10%,rgba(61,141,255,.24),transparent 70%),#06101a;border-bottom:1px solid #10243a}
.docsHeroGrid{display:grid;grid-template-columns:1fr auto;gap:34px;align-items:end}
.docsHero h1{font-size:clamp(38px,5vw,58px);margin:8px 0 10px}
.docsHero p{max-width:720px;margin:0;color:#8fa3bd}
.docsSearch{margin-top:26px;position:relative;max-width:760px}
.docsSearch input{width:100%;height:54px;padding:0 52px 0 18px;border-radius:14px;border:1px solid #23415f;background:#081522;color:#fff;font:inherit;outline:none;box-shadow:0 14px 40px rgba(0,0,0,.14)}
.docsSearch input:focus{border-color:#4b8fd1;box-shadow:0 0 0 3px rgba(61,141,255,.10)}
.docsSearch span{position:absolute;right:18px;top:50%;transform:translateY(-50%);color:#67809a;font-size:18px}
.docsBody{padding:42px 0 78px}
.docsLayout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:34px;align-items:start}
.docsSide{position:sticky;top:106px;border:1px solid #172b43;border-radius:16px;background:#081522;padding:14px}
.docsSideTitle{font-size:10px;color:#64809e;text-transform:uppercase;letter-spacing:.13em;font-weight:900;padding:8px 10px 10px}
.docsSide a{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 11px;border-radius:9px;color:#8fa5bf;font-size:12px;font-weight:750;transition:.15s}
.docsSide a:hover,.docsSide a.active{background:#102640;color:#fff}
.docsSide small{color:#587491;font-size:10px}
.docsMain{min-width:0}
.docsWelcome{display:grid;grid-template-columns:repeat(3,1fr);gap:13px;margin-bottom:32px}
.docsQuick{padding:20px;border:1px solid #18304a;border-radius:15px;background:linear-gradient(145deg,#0d1c2f,#091522);transition:.18s}
.docsQuick:hover{transform:translateY(-2px);border-color:#31577e}
.docsQuick b{display:block;font-size:14px;margin-bottom:5px}
.docsQuick span{color:#7f96b0;font-size:12px}
.docsSection{scroll-margin-top:120px;margin-bottom:26px;border:1px solid #172b43;border-radius:18px;background:#081522;overflow:hidden}
.docsSectionHead{padding:24px 26px 18px;border-bottom:1px solid #142a41;background:linear-gradient(180deg,#0b1929,#081522)}
.docsSectionHead .kicker{font-size:9px;color:#649bd0;text-transform:uppercase;letter-spacing:.13em;font-weight:900}
.docsSectionHead h2{font-size:27px;margin:6px 0 5px}
.docsSectionHead p{margin:0;color:#8097b1;font-size:13px}
.docsArticle{padding:24px 26px;border-bottom:1px solid #13283e}
.docsArticle:last-child{border-bottom:0}
.docsArticle h3{font-size:18px;margin:0 0 9px}
.docsArticle p,.docsArticle li{color:#91a7bf;font-size:13px}
.docsArticle ul,.docsArticle ol{margin:12px 0 0;padding-left:20px}
.docsArticle li+li{margin-top:7px}
.docsNote{margin-top:14px;padding:13px 15px;border:1px solid #244565;border-radius:11px;background:#0a1a2a;color:#a9c1d9;font-size:12px}
.docsCode{margin-top:14px;position:relative;border:1px solid #162d44;border-radius:12px;background:#03080d;overflow:hidden}
.docsCodeHead{height:34px;padding:0 12px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #12263b;color:#607994;font-size:9px;text-transform:uppercase;letter-spacing:.1em}
.docsCode pre{margin:0;padding:15px;overflow:auto;color:#9fc5e8;font:11px/1.75 ui-monospace,SFMono-Regular,Consolas,monospace}
.docsCode button{border:0;background:transparent;color:#78b7ff;font:800 9px/1 inherit;cursor:pointer;text-transform:uppercase;letter-spacing:.08em}
.docsTag{display:inline-flex;margin:3px 5px 3px 0;padding:5px 8px;border:1px solid #1f3f5e;border-radius:7px;background:#0d2135;color:#8dbde9;font-size:10px;font-weight:800}
.docsEmpty{display:none;padding:50px 20px;text-align:center;border:1px dashed #28435f;border-radius:16px;color:#768ea9}
.docsMeta{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;color:#66809d;font-size:10px}
@media(max-width:900px){.docsLayout{grid-template-columns:1fr}.docsSide{position:static;display:flex;overflow:auto;gap:4px}.docsSideTitle{display:none}.docsSide a{white-space:nowrap}.docsWelcome{grid-template-columns:1fr 1fr}.docsHeroGrid{grid-template-columns:1fr}}
@media(max-width:580px){.docsHero{padding:46px 0 28px}.docsBody{padding:28px 0 55px}.docsWelcome{grid-template-columns:1fr}.docsSectionHead,.docsArticle{padding:20px}.docsSide{margin:0 -2px}.docsHero h1{font-size:40px}}
</style>
@endpush

@section('content')
<section class="docsHero">
    <div class="container">
        <div class="docsHeroGrid">
            <div>
                <span class="pill"><span class="statusdot"></span>Nodexa Docs</span>
                <h1>Documentation</h1>
                <p>Alt du skal bruge for at komme i gang med Nodexa, administrere dine servere og finde løsninger hurtigt.</p>
                <div class="docsSearch">
                    <input id="docsSearch" type="search" placeholder="Søg i dokumentationen..." autocomplete="off" aria-label="Søg i dokumentationen">
                    <span>⌕</span>
                </div>
            </div>
            <a class="btn primary" href="{{ route('store.support') }}">Kontakt support</a>
        </div>
    </div>
</section>

<section class="docsBody">
    <div class="container docsLayout">
        <aside class="docsSide" aria-label="Dokumentationsmenu">
            <div class="docsSideTitle">Indhold</div>
            <a href="#getting-started" class="active">Kom godt i gang <small>01</small></a>
            <a href="#servers">Serverstyring <small>02</small></a>
            <a href="#files">Filer & SFTP <small>03</small></a>
            <a href="#databases">Databaser <small>04</small></a>
            <a href="#backups">Backups <small>05</small></a>
            <a href="#security">Sikkerhed <small>06</small></a>
            <a href="#api">API & integrationer <small>07</small></a>
            <a href="#troubleshooting">Fejlfinding <small>08</small></a>
        </aside>

        <main class="docsMain">
            <div class="docsWelcome">
                <a class="docsQuick" href="#getting-started"><b>🚀 Første server</b><span>Fra bestilling til første opstart.</span></a>
                <a class="docsQuick" href="#files"><b>📁 Upload filer</b><span>Brug File Manager eller SFTP.</span></a>
                <a class="docsQuick" href="#troubleshooting"><b>🛠 Fejlfinding</b><span>Find de mest almindelige løsninger.</span></a>
            </div>

            <div id="docsEmpty" class="docsEmpty">Ingen dokumentation matcher din søgning.</div>

            <section id="getting-started" class="docsSection" data-search="kom godt i gang login client area bestil server panel start">
                <div class="docsSectionHead"><span class="kicker">01 · Grundlæggende</span><h2>Kom godt i gang</h2><p>De første trin efter du har oprettet din Nodexa-konto.</p></div>
                <article class="docsArticle" data-search="login konto client area dashboard">
                    <h3>Log ind og åbn Client Area</h3>
                    <p>Log ind på Nodexa og åbn <strong>Client Area</strong>. Her finder du dine aktive services, fakturering, tickets og adgang til serverpanelet.</p>
                    <div class="docsMeta"><span class="docsTag">Client Area</span><span class="docsTag">Konto</span></div>
                </article>
                <article class="docsArticle" data-search="server bestilling oprettelse provisioning panel">
                    <h3>Din første server</h3>
                    <ol><li>Vælg en serverpakke under Server Hosting.</li><li>Gennemfør bestillingen.</li><li>Når servicen er oprettet, vises den under <strong>Services</strong>.</li><li>Åbn serveren i kontrolpanelet og start den.</li></ol>
                    <div class="docsNote">Hvis serveren stadig klargøres, kan den være synlig i Client Area før alle serverfunktioner er klar.</div>
                </article>
            </section>

            <section id="servers" class="docsSection" data-search="server styring start stop restart console ressourcer cpu ram disk">
                <div class="docsSectionHead"><span class="kicker">02 · Control Panel</span><h2>Serverstyring</h2><p>Administrer strøm, konsol, ressourcer og grundlæggende serverfunktioner.</p></div>
                <article class="docsArticle" data-search="start stop genstart restart kill strøm power">
                    <h3>Start, stop og genstart</h3>
                    <p>Brug power-knapperne i serverpanelet. Ved almindelige ændringer bør du bruge <strong>Genstart</strong> fremfor at tvinge processen til at stoppe.</p>
                </article>
                <article class="docsArticle" data-search="console logs log fejl kommando">
                    <h3>Konsol og logs</h3>
                    <p>Konsollen viser serverens output i realtid. Ved fejl er de sidste linjer før et crash normalt det vigtigste sted at starte.</p>
                    <div class="docsCode"><div class="docsCodeHead"><span>Eksempel på logkontrol</span><button type="button" data-copy="Se efter ERROR, FATAL, exception eller crash tæt på tidspunktet hvor serveren stoppede.">Kopiér</button></div><pre>Se efter: ERROR · FATAL · exception · crash
Kontrollér især de sidste 30-100 linjer før stoppet.</pre></div>
                </article>
                <article class="docsArticle" data-search="cpu ram disk storage netværk ressourcer">
                    <h3>Ressourceforbrug</h3>
                    <p>CPU, RAM, disk og netværk vises direkte i serverpanelet. Hvis en server rammer sin hukommelsesgrænse, kan processen blive stoppet automatisk.</p>
                </article>
            </section>

            <section id="files" class="docsSection" data-search="filer file manager sftp upload download ftp credentials host port">
                <div class="docsSectionHead"><span class="kicker">03 · Data</span><h2>Filer & SFTP</h2><p>Redigér filer i browseren eller forbind med en SFTP-klient.</p></div>
                <article class="docsArticle" data-search="file manager rediger filer upload browser">
                    <h3>File Manager</h3>
                    <p>File Manager er bedst til mindre ændringer, konfigurationsfiler og hurtige uploads. Stop helst serveren før du ændrer filer, som programmet aktivt skriver til.</p>
                </article>
                <article class="docsArticle" data-search="sftp host port username password winscp filezilla">
                    <h3>Forbind via SFTP</h3>
                    <p>Du finder serverens SFTP-oplysninger i kontrolpanelet. Brug altid <strong>SFTP</strong> — ikke almindelig ukrypteret FTP.</p>
                    <div class="docsCode"><div class="docsCodeHead"><span>SFTP opsætning</span><button type="button" data-copy="Protocol: SFTP&#10;Host: [vises i panelet]&#10;Port: [vises i panelet]&#10;Username: [vises i panelet]">Kopiér</button></div><pre>Protocol: SFTP
Host:     [vises i panelet]
Port:     [vises i panelet]
Username: [vises i panelet]</pre></div>
                </article>
            </section>

            <section id="databases" class="docsSection" data-search="database mysql mariadb host username password phpmyadmin heidisql">
                <div class="docsSectionHead"><span class="kicker">04 · Data</span><h2>Databaser</h2><p>Opret og administrer MySQL/MariaDB-databaser til dine services.</p></div>
                <article class="docsArticle" data-search="opret database mysql credentials">
                    <h3>Opret en database</h3>
                    <p>Opret databasen fra serverpanelets databaseområde. Gem host, port, databasenavn, brugernavn og adgangskode sikkert.</p>
                    <div class="docsNote">Brug ikke <code>localhost</code>, medmindre panelet specifikt viser det som databasehost. Brug den host Nodexa viser til databasen.</div>
                </article>
                <article class="docsArticle" data-search="connection string mysql uri">
                    <h3>Forbindelsesoplysninger</h3>
                    <div class="docsCode"><div class="docsCodeHead"><span>MySQL eksempel</span><button type="button" data-copy="mysql://USER:PASSWORD@HOST:3306/DATABASE">Kopiér</button></div><pre>mysql://USER:PASSWORD@HOST:3306/DATABASE</pre></div>
                </article>
            </section>

            <section id="backups" class="docsSection" data-search="backup sikkerhedskopi restore gendan scheduler automatisk">
                <div class="docsSectionHead"><span class="kicker">05 · Beskyttelse</span><h2>Backups</h2><p>Lav sikkerhedskopier før større ændringer og automatisér dem hvor muligt.</p></div>
                <article class="docsArticle" data-search="backup opret restore gendan">
                    <h3>Før større ændringer</h3>
                    <p>Lav altid en ny backup før opdateringer, mod/plugin-skift, databaseændringer eller større konfigurationsændringer.</p>
                </article>
                <article class="docsArticle" data-search="automatisk backup policy services hub">
                    <h3>Automatisk backup-policy</h3>
                    <p>På understøttede services kan backup-policy administreres fra <strong>Client Area → Services Hub</strong>. Kontrollér løbende at dine backups faktisk bliver oprettet.</p>
                </article>
            </section>

            <section id="security" class="docsSection" data-search="sikkerhed security adgangskode 2fa token api sftp permissions">
                <div class="docsSectionHead"><span class="kicker">06 · Security</span><h2>Sikkerhed</h2><p>Beskyt konto, servere, databaser og API-adgang.</p></div>
                <article class="docsArticle" data-search="password adgangskode 2fa konto">
                    <h3>Konto og adgang</h3>
                    <ul><li>Brug en unik adgangskode til Nodexa.</li><li>Del aldrig database- eller SFTP-login offentligt.</li><li>Fjern gamle brugere og tokens, som ikke længere skal have adgang.</li></ul>
                </article>
                <article class="docsArticle" data-search="api token revoke secret github discord webhook">
                    <h3>API-tokens og secrets</h3>
                    <p>Behandl API-tokens som adgangskoder. Læg dem ikke direkte i offentlige GitHub-repositories, screenshots eller supportbeskeder. Tilbagekald tokens, der kan være blevet delt.</p>
                </article>
            </section>

            <section id="api" class="docsSection" data-search="api integration token webhook services hub automation">
                <div class="docsSectionHead"><span class="kicker">07 · Udvikling</span><h2>API & integrationer</h2><p>Forbind eksterne værktøjer til din Nodexa-konto på en kontrolleret måde.</p></div>
                <article class="docsArticle" data-search="api token services hub opret revoke">
                    <h3>API-token</h3>
                    <p>Personlige API-tokens kan administreres fra <strong>Client Area → Services Hub</strong>. Giv kun integrationer den adgang de behøver, og tilbagekald tokens når de ikke længere bruges.</p>
                </article>
                <article class="docsArticle" data-search="webhook integration test endpoint url">
                    <h3>Webhooks</h3>
                    <p>Webhooks kan bruges til at sende hændelser til dine egne systemer. Brug HTTPS-endpoints og verificér altid, at modtageren er under din kontrol.</p>
                </article>
            </section>

            <section id="troubleshooting" class="docsSection" data-search="fejl fejlfinding server offline starter ikke database sftp 500 419 csrf">
                <div class="docsSectionHead"><span class="kicker">08 · Hjælp</span><h2>Fejlfinding</h2><p>De hurtigste kontroller når noget ikke virker som forventet.</p></div>
                <article class="docsArticle" data-search="server starter ikke crash console ram disk">
                    <h3>Serveren starter ikke</h3>
                    <ol><li>Åbn konsollen og find den første relevante fejl.</li><li>Kontrollér om RAM eller disk er fuld.</li><li>Kontrollér seneste fil- eller konfigurationsændring.</li><li>Gendan en kendt fungerende backup hvis nødvendigt.</li></ol>
                </article>
                <article class="docsArticle" data-search="database connection denied timeout host port credentials">
                    <h3>Databaseforbindelse fejler</h3>
                    <p>Kontrollér databasehost, port, brugernavn, adgangskode og databasenavn. En “connection established”-besked bekræfter kun forbindelsen — efterfølgende SQL-fejl skal stadig løses separat.</p>
                </article>
                <article class="docsArticle" data-search="support ticket hjælp log screenshot">
                    <h3>Opret en supportticket</h3>
                    <p>Hvis problemet fortsætter, send den præcise fejl, tidspunktet den opstod, hvad du ændrede lige før fejlen og relevante loglinjer. Undgå at sende adgangskoder eller tokens.</p>
                    <div style="margin-top:15px"><a class="btn primary" href="{{ route('store.support') }}">Gå til support</a> <a class="btn" href="{{ route('knowledgebase.index') }}">Åbn Vidensbase</a></div>
                </article>
            </section>
        </main>
    </div>
</section>

<script>
(function(){
    const input=document.getElementById('docsSearch');
    const sections=[...document.querySelectorAll('.docsSection')];
    const empty=document.getElementById('docsEmpty');
    const sideLinks=[...document.querySelectorAll('.docsSide a')];

    function normalize(v){return (v||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');}
    function filterDocs(){
        const q=normalize(input.value.trim());
        let visible=0;
        sections.forEach(section=>{
            if(!q){section.style.display='';section.querySelectorAll('.docsArticle').forEach(a=>a.style.display='');visible++;return;}
            const sectionText=normalize(section.dataset.search+' '+section.innerText);
            const articles=[...section.querySelectorAll('.docsArticle')];
            let articleVisible=0;
            articles.forEach(article=>{
                const match=normalize((article.dataset.search||'')+' '+article.innerText).includes(q);
                article.style.display=match?'':'none';
                if(match) articleVisible++;
            });
            const show=sectionText.includes(q)||articleVisible>0;
            section.style.display=show?'':'none';
            if(show) visible++;
        });
        empty.style.display=visible?'none':'block';
    }
    input.addEventListener('input',filterDocs);

    sideLinks.forEach(link=>link.addEventListener('click',()=>{
        sideLinks.forEach(x=>x.classList.remove('active'));
        link.classList.add('active');
    }));

    const observer=new IntersectionObserver(entries=>{
        const current=entries.filter(e=>e.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];
        if(!current)return;
        sideLinks.forEach(x=>x.classList.toggle('active',x.getAttribute('href')==='#'+current.target.id));
    },{rootMargin:'-130px 0px -65% 0px',threshold:[0,.2,.5]});
    sections.forEach(section=>observer.observe(section));

    document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{
        try{
            await navigator.clipboard.writeText(btn.dataset.copy);
            const old=btn.textContent;btn.textContent='Kopieret';setTimeout(()=>btn.textContent=old,1200);
        }catch(e){}
    }));
})();
</script>
@endsection
