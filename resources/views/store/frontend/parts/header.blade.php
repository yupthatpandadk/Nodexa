{{-- Each frontend pack has a distinct header structure, not only a palette. --}}
<div class="nx-global-header nx-global-header--{{ $nodexaFrontendLayout }}">
@if($nodexaFrontendLayout === 'latch')
    <header class="nx-head-shell nx-head-latch" role="banner">
        <div class="container nx-latch-top">
            <a class="nx-head-brand" href="{{route('store.home')}}"><span class="nx-head-logo">N.</span><span>NODEXA<small>HOSTING PLATFORM</small></span></a>
            <div class="nx-head-actions">@include('store.frontend.parts.nav-actions')</div>
        </div>
        <div class="nx-latch-navrow">
            <nav class="container nx-head-links" aria-label="Hovednavigation">@include('store.frontend.parts.nav-links')</nav>
        </div>
    </header>
@elseif($nodexaFrontendLayout === 'xlumia')
    <header class="nx-head-shell nx-head-xl" role="banner">
        <div class="container nx-xl-floating">
            <a class="nx-head-brand" href="{{route('store.home')}}"><span class="nx-head-logo">✦</span><span>Nodexa<small>Cloud & Game Servers</small></span></a>
            <nav class="nx-head-links" aria-label="Hovednavigation">@include('store.frontend.parts.nav-links')</nav>
            <div class="nx-head-actions">@include('store.frontend.parts.nav-actions')</div>
        </div>
    </header>
@elseif($nodexaFrontendLayout === 'horizon')
    <header class="nx-head-shell nx-head-horizon" role="banner">
        <div class="nx-horizon-utility">
            <div class="container"><span>NODEXA // CLOUD INFRASTRUCTURE</span><a href="{{route('store.status')}}">Driftsstatus</a><a href="{{route('store.support')}}">Hjælp & support</a></div>
        </div>
        <div class="container nx-horizon-main">
            <a class="nx-head-brand" href="{{route('store.home')}}"><span class="nx-head-logo">N</span><span>Nodexa<small>GAME SERVER CLOUD</small></span></a>
            <div class="nx-head-actions">@include('store.frontend.parts.nav-actions')</div>
        </div>
        <nav class="nx-horizon-navigation" aria-label="Hovednavigation"><div class="container nx-head-links">@include('store.frontend.parts.nav-links')</div></nav>
    </header>
@elseif($nodexaFrontendLayout === 'dinonode')
    <header class="nx-head-shell nx-head-dino" role="banner">
        <div class="container nx-dino-primary">
            <a class="nx-head-brand" href="{{route('store.home')}}"><span class="nx-head-logo">N</span><span>Nodexa<small>POWERED GAME HOSTING</small></span></a>
            <nav class="nx-head-links" aria-label="Hovednavigation">@include('store.frontend.parts.nav-links')</nav>
            <div class="nx-head-actions">@include('store.frontend.parts.nav-actions')</div>
        </div>
        <div class="nx-dino-games"><div class="container"><span>SERVER KATEGORIER</span><a href="{{route('store.hosting')}}">Minecraft & Games</a><a href="{{route('store.vps')}}">VPS Cloud</a><a href="{{route('store.features')}}">Kontrolpanel</a></div></div>
    </header>
@endif
    <details class="nx-mobile-menu">
        <summary aria-label="Åbn navigation"><span>☰</span> Menu</summary>
        <nav aria-label="Mobilnavigation">@include('store.frontend.parts.nav-links')</nav>
        <div class="nx-mobile-actions">@include('store.frontend.parts.nav-actions')</div>
    </details>
</div>
