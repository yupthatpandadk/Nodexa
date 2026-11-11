@extends('store.layout')
@section('title','Nodexa · Game Server Hosting')
@section('description','Nodexa serverhosting med integreret kontrolpanel, Minecraft, FiveM og VPS.')
@section('content')

<main class="nx-theme-page nx-home-latch">
    <section class="nx-latch-hero">
        <div class="container nx-latch-hero-grid">
            <div class="nx-latch-maincopy">
                <p class="nx-theme-overline">N/ PLATFORM · GAME SERVER INFRASTRUCTURE</p>
                <h1>Byg din server.<br><em>Styr hele driften.</em></h1>
                <p class="nx-theme-lead">Professionel serverhosting uden unødig kompleksitet. Vælg ressourcer, deploy og administrér alt fra ét kontrolpanel.</p>
                <div class="nx-theme-ctas"><a class="nx-theme-primary" href="{{route('store.hosting')}}">Se hostingpakker <span>↗</span></a><a class="nx-theme-secondary" href="{{route('store.features')}}">Udforsk funktioner</a></div>
                <div class="nx-latch-proof"><span>01 / Web Console</span><span>02 / SFTP & filer</span><span>03 / Backups</span></div>
            </div>
            <div class="nx-latch-terminal">
                <div class="nx-latch-term-head"><span>NODEXA / SYSTEM OVERVIEW</span><small>LIVE CONTROL</small></div>
                <div class="nx-latch-term-content">
                    <div class="nx-latch-readout"><span>01</span><div><small>RUNTIME</small><strong>Server workspace</strong></div><b>READY</b></div>
                    <div class="nx-latch-readout"><span>02</span><div><small>ACCESS</small><strong>File manager + SFTP</strong></div><b>AVAILABLE</b></div>
                    <div class="nx-latch-readout"><span>03</span><div><small>AUTOMATION</small><strong>Scheduled tasks</strong></div><b>CONFIGURE</b></div>
                    <pre class="nx-latch-console" aria-label="Eksempel på serversession">$ nodexa deploy --game minecraft
&gt; preparing environment
&gt; initializing server
✓ ready for management<span> _</span></pre>
                </div>
            </div>
        </div>
    </section>
    <section class="nx-latch-index"><div class="container"><div class="nx-latch-index-title">ET KOMPLET WORKSPACE</div><div><strong>01</strong><span>Serverkontrol</span></div><div><strong>02</strong><span>Databaser</span></div><div><strong>03</strong><span>Backups</span></div><div><strong>04</strong><span>Automatisering</span></div></div></section>
    <section class="nx-theme-section nx-latch-products">
        <div class="container">
            <div class="nx-latch-sectiontop"><p class="nx-theme-overline">SERVER CATALOG / 001</p><h2>Vælg kapaciteten.<br><em>Vi klarer resten.</em></h2><a href="{{route('store.hosting')}}" class="nx-theme-textlink">Se alle pakker ↗</a></div>
            @include('store.frontend.parts.game-cards',['limit'=>3,'variant'=>'latch-home'])
        </div>
    </section>
    <section class="nx-latch-workflow nx-theme-section"><div class="container nx-latch-workflow-grid"><div><p class="nx-theme-overline">SÅDAN FUNGERER DET</p><h2>Fra valg til<br>kontrolpanel.</h2><p>Et overskueligt flow fra serverpakke til drift og vedligeholdelse.</p><a class="nx-theme-secondary" href="{{route('store.docs')}}">Se dokumentation ↗</a></div><ol><li><b>01</b><span><strong>Vælg en pakke</strong><small>RAM, CPU og disk efter behov</small></span></li><li><b>02</b><span><strong>Konfigurér serveren</strong><small>Vælg software og indstillinger</small></span></li><li><b>03</b><span><strong>Administrér i Nodexa</strong><small>Konsol, filer og backups</small></span></li></ol></div></section>
</main>
@endsection
