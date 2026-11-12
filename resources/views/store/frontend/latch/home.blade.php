@extends('store.layout')
@section('title','Game Server Hosting · Nodexa')
@section('description','Serverhosting til Minecraft, FiveM og andre spil med Nodexa Control Panel.')
@section('content')
<main class="nx-theme-page nx-home-latch">
    <section class="nx-latch-landing-hero">
        <div class="container nx-latch-landing-content">
            <div class="nx-latch-hero-label"><span class="nx-latch-pulse"></span> GAME SERVER HOSTING · NODEXA</div>
            <h1>Din server.<br><span>Byg dit fællesskab.</span></h1>
            <p>Game server hosting til Minecraft, FiveM og andre spil. Vælg din pakke, kom i gang og administrér serveren fra Nodexa Control Panel.</p>
            <div class="nx-latch-landing-actions">
                <a class="nx-theme-primary" href="#nxLatchPricing">Find din server <span aria-hidden="true">↗</span></a>
                <a class="nx-theme-secondary" href="{{route('store.features')}}">Udforsk platformen</a>
            </div>
            <div class="nx-latch-landing-highlights">
                <div><strong>Live</strong><small>Server Console</small></div>
                <div><strong>SFTP</strong><small>Filhåndtering</small></div>
                <div><strong>Backups</strong><small>Gendannelse</small></div>
            </div>
        </div>
        <div class="nx-latch-game-art" aria-hidden="true"><span class="nx-latch-art-orb"></span><span class="nx-latch-art-grid"></span></div>
    </section>

    <section class="nx-latch-landing-products nx-theme-section" id="nxLatchPricing">
        <div class="container">
            <div class="nx-latch-landing-heading">
                <span class="nx-theme-overline">PRISER OG SERVERPAKKER</span>
                <h2>Vælg dit spil og din pakke.</h2>
                <p>Her ser du de faktiske aktive serverpakker og priser i Nodexa. Lokation og eventuelle rabatter vises kun, når de understøttes af bestillingen.</p>
            </div>
            @include('store.frontend.parts.latch-game-selector')
            <div class="nx-latch-pricing-heading"><strong>Tilgængelige serverpakker</strong><span>PRISER PR. MÅNED · DKK</span></div>
            <div id="nxLatchCatalog">
                @include('store.frontend.parts.game-cards', ['variant' => 'latch-home'])
            </div>
            <p class="nx-theme-empty" id="nxLatchNoMatch" hidden>Ingen aktive pakker til det valgte spil.</p>
            <div class="nx-latch-viewall"><a href="{{route('store.hosting')}}" class="nx-theme-secondary">Se alle hostingpakker ↗</a></div>
        </div>
    </section>

    <section class="nx-latch-landing-solutions nx-theme-section">
        <div class="container">
            <div class="nx-latch-landing-heading">
                <span class="nx-theme-overline">FLERE HOSTINGLØSNINGER</span>
                <h2>Alt samlet ét sted.</h2>
            </div>
            <div class="nx-latch-solution-grid">
                <a href="{{route('store.hosting')}}"><span>01 / GAME HOSTING</span><h3>Minecraft og FiveM</h3><p>Pakker, softwarevalg og central serveradministration.</p><b>Se game hosting ↗</b></a>
                <a href="{{route('store.vps')}}"><span>02 / VIRTUAL SERVERS</span><h3>VPS Hosting</h3><p>Virtuelle servere til projekter med egne systemkrav.</p><b>Se VPS ↗</b></a>
                <a href="{{route('store.features')}}"><span>03 / MANAGEMENT</span><h3>Nodexa Control</h3><p>Filer, backups, konsol og værktøjer i ét samlet dashboard.</p><b>Se funktioner ↗</b></a>
            </div>
        </div>
    </section>

    <section class="nx-latch-landing-platform nx-theme-section">
        <div class="container nx-latch-platform-grid">
            <div><span class="nx-theme-overline">NODEXA CONTROL PANEL</span><h2>Brug tiden på spillet.<br>Ikke på opsætningen.</h2><p>Administrér serveren i browseren med filhåndtering, opstartsværdier, konsol og sikkerhedskopier.</p><a class="nx-theme-primary" href="{{route('store.features')}}">Se kontrolpanelet ↗</a></div>
            <div class="nx-latch-panel-preview" aria-label="Eksempel på Nodexa funktioner">
                <div class="nx-latch-panel-top"><span>● ● ●</span> NODEXA / CONTROL</div>
                <div class="nx-latch-panel-row"><strong>Console</strong><span>Realtid</span></div>
                <div class="nx-latch-panel-row"><strong>Files & SFTP</strong><span>Filstyring</span></div>
                <div class="nx-latch-panel-row"><strong>Backups</strong><span>Gendannelse</span></div>
                <div class="nx-latch-panel-row"><strong>Schedules</strong><span>Automatisering</span></div>
            </div>
        </div>
    </section>
</main>
@endsection
