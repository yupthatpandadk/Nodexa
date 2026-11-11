{{-- Fully separate footer layouts. All links reuse existing Nodexa routes. --}}
@if($nodexaFrontendLayout === 'latch')
<footer class="nx-layout-footer nx-footer-latch">
    <div class="container nx-latch-footer-top">
        <div><div class="nx-footer-kicker">NODEXA / CONTROL</div><h2>Serverhosting,<br>uden besvær.</h2><p>Fra planlægning til daglig drift. Din server og dine værktøjer samlet ét sted.</p></div>
        <a href="{{route('store.hosting')}}" class="nx-theme-primary">Vælg serverpakke ↗</a>
    </div>
    <div class="container nx-footer-main">
        <div class="nx-footer-brand"><a href="{{route('store.home')}}">N<span>.</span> NODEXA</a><p>Game Server Cloud • Hosting og kontrol samlet.</p></div>
        <div><h3>HOSTING</h3><a href="{{route('store.hosting')}}">Game Servers</a><a href="{{route('store.vps')}}">VPS Cloud</a><a href="{{route('store.features')}}">Control Panel</a></div>
        <div><h3>HJÆLP</h3><a href="{{route('store.support')}}">Support</a><a href="{{route('knowledgebase.index')}}">Knowledgebase</a><a href="{{route('store.status')}}">Driftsstatus</a></div>
        <div><h3>KONTO</h3><a href="{{route('store.client')}}">Kundeportal</a><a href="{{route('store.orders')}}">Ordrer</a><a href="/panel">Kontrolpanel</a></div>
    </div>
    <div class="container nx-footer-bottom"><span>© {{date('Y')}} Nodexa</span><span>OPERATIONS / GAME SERVER INFRASTRUCTURE</span></div>
</footer>
@elseif($nodexaFrontendLayout === 'xlumia')
<footer class="nx-layout-footer nx-footer-xl">
    <div class="container nx-xl-footer-cta"><span>✦ BUILD YOUR NEXT WORLD</span><h2>Din næste server starter her.</h2><p>Hosting og serveradministration i Nodexa.</p><a href="{{route('store.hosting')}}" class="nx-theme-primary">Udforsk hosting ↗</a></div>
    <div class="container nx-footer-main">
        <div class="nx-footer-brand"><a href="{{route('store.home')}}">✦ Nodexa</a><p>Et samlet univers for game hosting og VPS.</p></div>
        <div><h3>Produkter</h3><a href="{{route('store.hosting')}}">Game Servers</a><a href="{{route('store.vps')}}">VPS Hosting</a><a href="{{route('store.features')}}">Platform</a></div>
        <div><h3>Virksomhed</h3><a href="{{route('store.about')}}">Om os</a><a href="{{route('store.status')}}">Status</a><a href="{{route('store.support')}}">Kontakt</a></div>
        <div><h3>Din konto</h3><a href="{{route('store.client')}}">Kundeportal</a><a href="{{route('store.orders')}}">Ordrer</a><a href="/panel">Kontrolpanel</a></div>
    </div>
    <div class="container nx-footer-bottom"><span>© {{date('Y')}} Nodexa · Cloud Experience</span><a href="{{route('store.support')}}">Support ↗</a></div>
</footer>
@elseif($nodexaFrontendLayout === 'horizon')
<footer class="nx-layout-footer nx-footer-horizon">
    <div class="container nx-horizon-footer-banner"><div><small>NODEXA / CLOUD INFRASTRUCTURE</small><strong>Byg videre på en stærk platform.</strong></div><a href="{{route('store.hosting')}}">Se hostingprodukter →</a></div>
    <div class="container nx-footer-main">
        <div class="nx-footer-brand"><a href="{{route('store.home')}}">N/ NODEXA</a><p>Professionel hosting, serverdrift og kundeadministration.</p></div>
        <div><h3>01 / HOSTING</h3><a href="{{route('store.hosting')}}">Game Hosting</a><a href="{{route('store.vps')}}">VPS</a><a href="{{route('store.features')}}">System</a></div>
        <div><h3>02 / SUPPORT</h3><a href="{{route('store.support')}}">Support Center</a><a href="{{route('knowledgebase.index')}}">Dokumentation</a><a href="{{route('store.status')}}">Systemstatus</a></div>
        <div><h3>03 / ACCOUNT</h3><a href="{{route('store.client')}}">Client Area</a><a href="{{route('store.orders')}}">Billing & Ordrer</a><a href="/panel">Panel</a></div>
    </div>
    <div class="container nx-footer-bottom"><span>© {{date('Y')}} NODEXA HOSTING</span><span>SERVICE / CLOUD / OPERATIONS</span></div>
</footer>
@elseif($nodexaFrontendLayout === 'dinonode')
<footer class="nx-layout-footer nx-footer-dino">
    <div class="container nx-dino-footer-cta"><div><small>NODEXA GAME CLOUD</small><h2>Klar til den næste server?</h2><p>Vælg din spilverden – vi giver dig kontrollen.</p></div><a href="{{route('store.hosting')}}" class="nx-theme-primary">Vælg hosting ↗</a></div>
    <div class="container nx-footer-main">
        <div class="nx-footer-brand"><a href="{{route('store.home')}}">▣ Nodexa</a><p>Game hosting til Minecraft, FiveM og andre communities.</p></div>
        <div><h3>Spil & Hosting</h3><a href="{{route('store.hosting')}}">Minecraft & FiveM</a><a href="{{route('store.vps')}}">VPS Hosting</a><a href="{{route('store.features')}}">Control Panel</a></div>
        <div><h3>Community</h3><a href="{{route('store.about')}}">Om Nodexa</a><a href="{{route('store.status')}}">Status</a><a href="{{route('store.support')}}">Support</a></div>
        <div><h3>Min Konto</h3><a href="{{route('store.client')}}">Dashboard</a><a href="{{route('store.orders')}}">Ordrer</a><a href="/panel">Serverpanel</a></div>
    </div>
    <div class="container nx-footer-bottom"><span>© {{date('Y')}} Nodexa</span><span>GAME SERVER CLOUD</span></div>
</footer>
@endif
