@extends('storefront.layout')

@section('title', 'Planer')
@section('description', 'Sammenlign fleksible Nodexa game server-planer og vælg ressourcer efter dit community.')

@section('content')
<section class="nx-page-hero">
    <div class="nx-shell">
        <span class="nx-eyebrow">RESSOURCER & PLANER</span>
        <h1>Start passende.<br><em>Skalér når du vokser.</em></h1>
        <p>Planerne nedenfor viser typiske ressourceprofiler. Endelig pris og tilgængelighed sættes af den konkrete Nodexa-hostingudbyder, så storefronten ikke lover en pris der ikke er konfigureret.</p>
    </div>
</section>

<section class="nx-section nx-section-tight">
    <div class="nx-shell">
        <div class="nx-plan-grid">
            @foreach ($plans as $plan)
                <article class="nx-plan {{ !empty($plan['featured']) ? 'featured' : '' }}">
                    @if (!empty($plan['featured']))<span class="nx-plan-badge">POPULÆR</span>@endif
                    <small>{{ $plan['eyebrow'] }}</small>
                    <h3>{{ $plan['name'] }}</h3>
                    <p>{{ $plan['description'] }}</p>
                    <div class="nx-plan-list">
                        <span>{{ $plan['memory'] }}</span>
                        <span>{{ $plan['cpu'] }}</span>
                        <span>{{ $plan['storage'] }}</span>
                        <span>Nodexa kontrolpanel</span>
                    </div>
                    <div class="nx-plan-price">Pris konfigureres af udbyderen</div>
                    <a class="nx-btn {{ !empty($plan['featured']) ? 'nx-btn-primary' : 'nx-btn-ghost' }}" style="width:100%;margin-top:12px" href="{{ route('storefront.support') }}">Få en pris →</a>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="nx-section">
    <div class="nx-shell">
        <div class="nx-section-head">
            <div><span class="nx-kicker">INKLUDERET I PLATFORMEN</span><h2>Mere end bare RAM.</h2></div>
            <p>Serverens ressourcer er kun én del af oplevelsen. Nodexa samler de daglige værktøjer i samme interface.</p>
        </div>
        <div class="nx-card-grid">
            <article class="nx-card"><div class="nx-card-icon">›_</div><h3>Console & power</h3><p>Start, restart, stop og live serveroutput fra browser eller mobil.</p></article>
            <article class="nx-card"><div class="nx-card-icon">↻</div><h3>Backups</h3><p>Backupværktøjer og restore-flow direkte fra den enkelte server.</p></article>
            <article class="nx-card"><div class="nx-card-icon">▤</div><h3>Files & SFTP</h3><p>Webbaseret filhåndtering og SFTP-adgang til mere avancerede workflows.</p></article>
        </div>
    </div>
</section>

<section class="nx-cta">
    <div class="nx-shell"><div class="nx-cta-inner">
        <span class="nx-kicker">HAR DU SÆRLIGE KRAV?</span>
        <h2>Byg en løsning omkring serveren — ikke omvendt.</h2>
        <p>Større RAM-mængder, mere disk eller en særlig serverprofil kan håndteres som en custom løsning, hvis infrastrukturen understøtter det.</p>
        <div class="nx-hero-actions"><a class="nx-btn nx-btn-primary" href="{{ route('storefront.support') }}">Tal med os →</a><a class="nx-btn nx-btn-ghost" href="{{ route('storefront.games') }}">Se game hosting</a></div>
    </div></div>
</section>
@endsection
