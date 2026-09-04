@extends('storefront.layout')

@section('title', 'Game Server Hosting')
@section('description', 'Nodexa Game Server Cloud samler console, filer, backups, databaser og serverstyring i ét moderne panel.')

@section('content')
<section class="nx-hero">
    <div class="nx-shell nx-hero-grid">
        <div class="nx-hero-copy">
            <span class="nx-eyebrow">NODEXA GAME SERVER CLOUD</span>
            <h1>Game servers.<br><em>Uden besværet.</em></h1>
            <p>Start, administrér og skalér game servers fra ét hurtigt kontrolpanel. Nodexa samler console, filer, backups, databaser og drift i en enkel oplevelse.</p>
            <div class="nx-hero-actions">
                <a class="nx-btn nx-btn-primary" href="{{ route('storefront.games') }}">Se game hosting <span>→</span></a>
                <a class="nx-btn nx-btn-ghost" href="{{ route('storefront.features') }}">Udforsk funktioner</a>
            </div>
            <div class="nx-hero-proof">
                <span class="nx-proof"><b>✓</b> Live console</span>
                <span class="nx-proof"><b>✓</b> Backups & filer</span>
                <span class="nx-proof"><b>✓</b> Mobilvenligt panel</span>
            </div>
        </div>

        <div class="nx-panel-preview" aria-label="Nodexa panel preview">
            <div class="nx-preview-bar"><i></i><i></i><i></i><span>Nodexa Control</span></div>
            <div class="nx-preview-server">
                <div class="nx-server-head">
                    <div class="nx-server-icon">◈</div>
                    <div class="nx-server-title"><strong>Game Server</strong><small>Managed by Nodexa</small></div>
                    <span class="nx-live">● ONLINE</span>
                </div>
                <div class="nx-console"><span class="accent">nodexa@server~</span> Server marked as running...<br>container@runtime~ java -Xms256M -jar server.jar<br>[INFO] Starting game server...<br>[INFO] Loading world data...<br><span class="accent">[INFO] Done. Server is ready.</span><br><br>Players can now connect.</div>
                <div class="nx-resource-row">
                    <div class="nx-mini-stat"><span>CPU</span><strong>12.4%</strong></div>
                    <div class="nx-mini-stat"><span>Memory</span><strong>1.8 GiB</strong></div>
                    <div class="nx-mini-stat"><span>Status</span><strong>Running</strong></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="nx-section">
    <div class="nx-shell">
        <div class="nx-section-head">
            <div><span class="nx-kicker">ALT SAMLET ÉT STED</span><h2>Kontrol uden unødvendig kompleksitet.</h2></div>
            <p>Nodexa er bygget til både små private servers og communities, der har brug for mere kontrol over drift og ressourcer.</p>
        </div>
        <div class="nx-card-grid">
            <article class="nx-card"><div class="nx-card-icon">›_</div><h3>Realtime console</h3><p>Følg serveroutput live, send kommandoer og styr start, restart og stop direkte fra browseren.</p></article>
            <article class="nx-card"><div class="nx-card-icon">▤</div><h3>Filhåndtering</h3><p>Upload, redigér, flyt og download serverfiler uden at skulle åbne en separat FTP-klient.</p></article>
            <article class="nx-card"><div class="nx-card-icon">↻</div><h3>Backups</h3><p>Opret og gendan backups fra panelet, så du hurtigt kan komme tilbage efter ændringer.</p></article>
            <article class="nx-card"><div class="nx-card-icon">⌁</div><h3>Databaser</h3><p>Hold serverens databaser tæt på driften og administrér adgang fra samme platform.</p></article>
            <article class="nx-card"><div class="nx-card-icon">◷</div><h3>Schedules</h3><p>Automatisér kommandoer, genstarter og tilbagevendende serveropgaver med planlægninger.</p></article>
            <article class="nx-card"><div class="nx-card-icon">◇</div><h3>Egg-baseret</h3><p>Understøt forskellige game servertyper gennem Eggs og genbrug gennemtestede installationer.</p></article>
        </div>
    </div>
</section>

<section class="nx-section nx-section-tight">
    <div class="nx-shell">
        <div class="nx-section-head">
            <div><span class="nx-kicker">GAME HOSTING</span><h2>En platform til flere servertyper.</h2></div>
            <a class="nx-btn nx-btn-ghost" href="{{ route('storefront.games') }}">Se alle servertyper →</a>
        </div>
        <div class="nx-card-grid">
            <article class="nx-card"><div class="nx-card-icon">⛏</div><h3>Minecraft</h3><p>Vanilla, Paper, plugins og modded servere med ressourcestyring og fuld filadgang.</p><a class="nx-card-link" href="{{ route('storefront.games') }}">Se muligheder →</a></article>
            <article class="nx-card"><div class="nx-card-icon">V</div><h3>FiveM</h3><p>FXServer-hosting med de værktøjer der skal til for at drive og vedligeholde en community-server.</p><a class="nx-card-link" href="{{ route('storefront.games') }}">Se muligheder →</a></article>
            <article class="nx-card"><div class="nx-card-icon">+</div><h3>Flere games</h3><p>Nodexa kan udvides med flere Eggs, så platformen ikke er låst til én bestemt servertype.</p><a class="nx-card-link" href="{{ route('storefront.games') }}">Udforsk hosting →</a></article>
        </div>
    </div>
</section>

<section class="nx-cta">
    <div class="nx-shell">
        <div class="nx-cta-inner">
            <span class="nx-kicker">KLAR TIL AT KOMME I GANG?</span>
            <h2>Din server. Dit community. Ét Nodexa-panel.</h2>
            <p>Vælg den servertype og de ressourcer der passer til dit setup. Tilgængelighed og endelig pris afhænger af den konkrete Nodexa-hostingkonfiguration.</p>
            <div class="nx-hero-actions">
                <a class="nx-btn nx-btn-primary" href="{{ route('storefront.pricing') }}">Se planer →</a>
                @auth<a class="nx-btn nx-btn-ghost" href="{{ route('index') }}">Åbn mit panel</a>@else<a class="nx-btn nx-btn-ghost" href="{{ route('auth.login') }}">Log ind</a>@endauth
            </div>
        </div>
    </div>
</section>
@endsection
