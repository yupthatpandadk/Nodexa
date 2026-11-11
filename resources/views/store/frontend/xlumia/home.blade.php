@extends('store.layout')
@section('title','Nodexa · Game Server Hosting')
@section('description','Nodexa serverhosting med integreret kontrolpanel, Minecraft, FiveM og VPS.')
@section('content')

<main class="nx-theme-page nx-home-xl">
    <section class="nx-xl-hero">
        <div class="container nx-xl-hero-inner">
            <div class="nx-xl-kicker">✦ NODEXA CLOUD EXPERIENCE</div>
            <h1>Dit univers.<br><span>Din server.</span></h1>
            <p class="nx-theme-lead">Hosting og serverstyring samlet i en platform, der giver dig plads til at bygge, spille og udvikle.</p>
            <div class="nx-theme-ctas"><a class="nx-theme-primary" href="{{route('store.hosting')}}">Find din server <span>↗</span></a><a class="nx-theme-secondary" href="{{route('store.vps')}}">Udforsk VPS</a></div>
            <div class="nx-xl-floating-cards" aria-label="Platformens funktioner"><div><b>⌘</b><span>Live Console</span><small>Serverkontrol</small></div><div><b>▣</b><span>File Manager</span><small>Filer & SFTP</small></div><div><b>◎</b><span>Backups</span><small>Gendannelse</small></div></div>
        </div>
        <div class="nx-xl-orbit nx-xl-orbit-a" aria-hidden="true"></div><div class="nx-xl-orbit nx-xl-orbit-b" aria-hidden="true"></div>
    </section>
    <section class="nx-xl-platform"><div class="container"><div class="nx-xl-showcase"><div class="nx-xl-showcase-copy"><span class="nx-theme-overline">ET SYSTEM, ALLE VÆRKTØJER</span><h2>Overblik med plads til det hele.</h2><p>Live konsol, serverfiler, databaser og automatiserede opgaver i dit Nodexa Control Panel.</p><a href="{{route('store.features')}}" class="nx-theme-textlink">Opdag Nodexa Control ↗</a></div><div class="nx-xl-dashboard"><div class="nx-xl-dashboard-top"><i></i><i></i><i></i><span>Nodexa Control / Session</span></div><div class="nx-xl-dashboard-content"><div class="nx-xl-dashboard-side"><span></span><span></span><span></span><span></span></div><div class="nx-xl-dashboard-main"><div class="nx-xl-dashboard-line"><span>SERVER OVERVIEW</span><b>● ONLINE</b></div><div class="nx-xl-dashboard-stats"><div><small>CONSOLE</small><strong>Ready</strong></div><div><small>STORAGE</small><strong>SFTP</strong></div><div><small>AUTOMATION</small><strong>Active</strong></div></div><div class="nx-xl-dashboard-code"><span>$ nodexa status</span><br>Server environment connected<br>Control panel ready<span> _</span></div></div></div></div></div></div></section>
    <section class="nx-theme-section nx-xl-pricing"><div class="container"><div class="nx-xl-section-intro"><p class="nx-theme-overline">EN PLAN TIL DIT PROJEKT</p><h2>Vælg din næste server.</h2><p>Gennemskuelige pakker, enkelt valg og central administration.</p></div>@include('store.frontend.parts.game-cards',['limit'=>3,'variant'=>'xlumia-home'])<a href="{{route('store.hosting')}}" class="nx-theme-textlink nx-xl-more">Sammenlign alle hostingpakker ↗</a></div></section>
    <section class="nx-theme-section nx-xl-bottom"><div class="container"><span>BUILD YOUR WORLD</span><h2>Servere, der følger dine idéer.</h2><a class="nx-theme-primary" href="{{route('store.hosting')}}">Kom i gang ↗</a></div></section>
</main>
@endsection
