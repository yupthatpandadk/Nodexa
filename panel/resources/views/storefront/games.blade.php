@extends('storefront.layout')

@section('title', 'Game Hosting')
@section('description', 'Udforsk game servertyper på Nodexa — fra Minecraft og FiveM til Rust, CS2 og flere Egg-baserede servers.')

@section('content')
<section class="nx-page-hero">
    <div class="nx-shell">
        <span class="nx-eyebrow">GAME HOSTING</span>
        <h1>Vælg spillet.<br><em>Nodexa klarer kontrollen.</em></h1>
        <p>Samme enkle panel på tværs af game servertyper. Console, filer, backups og ressourcer følger med oplevelsen, mens den konkrete installation styres af serverens Egg.</p>
    </div>
</section>

<section class="nx-section nx-section-tight">
    <div class="nx-shell">
        <div class="nx-games-grid">
            @foreach ($games as $game)
                <article class="nx-game-card">
                    <div class="nx-game-card-head">
                        <div class="nx-game-logo">{{ $game['icon'] }}</div>
                        <div><h3>{{ $game['name'] }}</h3><small>{{ $game['tag'] }}</small></div>
                    </div>
                    <p>{{ $game['description'] }}</p>
                    <a class="nx-card-link" href="{{ route('storefront.pricing') }}">Se planer →</a>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="nx-section">
    <div class="nx-shell">
        <div class="nx-section-head">
            <div><span class="nx-kicker">SAMME PLATFORM</span><h2>Skift game — ikke arbejdsgang.</h2></div>
            <p>Nodexa bruger Egg-baserede serverdefinitioner, så servertyper kan have deres egne installations- og startup-indstillinger uden at ændre selve kontrolpanelet.</p>
        </div>
        <div class="nx-card-grid">
            <article class="nx-card"><div class="nx-card-icon">◈</div><h3>Automatisk installation</h3><p>Egg'et definerer serverens runtime, startup og installation, så opsætningen er reproducerbar.</p></article>
            <article class="nx-card"><div class="nx-card-icon">▣</div><h3>Game-logo pr. Egg</h3><p>Hvert Egg kan have sit eget logo, som automatisk vises på serverens Nodexa-dashboard.</p></article>
            <article class="nx-card"><div class="nx-card-icon">↗</div><h3>Skalerbare ressourcer</h3><p>CPU, RAM, disk og netværksgrænser administreres centralt, uanset hvilken game server der kører.</p></article>
        </div>
    </div>
</section>

<section class="nx-cta">
    <div class="nx-shell"><div class="nx-cta-inner">
        <span class="nx-kicker">MANGLER DIT GAME?</span>
        <h2>Nodexa er ikke låst til kataloget.</h2>
        <p>Andre servertyper kan tilføjes gennem Eggs. Tilgængelighed afhænger af de Eggs og ressourcer, der er aktiveret hos din Nodexa-udbyder.</p>
        <div class="nx-hero-actions"><a class="nx-btn nx-btn-primary" href="{{ route('storefront.support') }}">Kontakt support →</a><a class="nx-btn nx-btn-ghost" href="{{ route('storefront.features') }}">Se funktioner</a></div>
    </div></div>
</section>
@endsection
