@extends('storefront.layout')

@section('title', 'Funktioner')
@section('description', 'Se Nodexa-funktioner til console, filer, backups, databaser, schedules, brugere og serveradministration.')

@section('content')
<section class="nx-page-hero">
    <div class="nx-shell">
        <span class="nx-eyebrow">NODEXA CONTROL</span>
        <h1>Alt det du bruger.<br><em>Samlet i ét panel.</em></h1>
        <p>Fra den første serverstart til backups, databaseadgang og daglig drift. Nodexa er designet til at holde de vigtigste værktøjer tæt på serveren.</p>
    </div>
</section>

<section class="nx-section nx-section-tight">
    <div class="nx-shell">
        <div class="nx-feature-list">
            <article class="nx-feature-row"><div class="nx-card-icon">›_</div><div><h3>Realtime console</h3><p>Følg output live, send kommandoer og styr serverens power-state uden at forlade siden.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">▤</div><div><h3>File Manager</h3><p>Upload, download, redigér, flyt og administrér filer direkte i browseren med tematilpasset interface.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">↻</div><div><h3>Backups</h3><p>Opret og administrér backups, lås dem og gendan serverdata når det er nødvendigt.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">⌁</div><div><h3>Databaser</h3><p>Opret serverdatabaser og hold credentials og databaseadgang samlet med serveren.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">◷</div><div><h3>Schedules</h3><p>Automatisér genstarter, kommandoer, backups og andre tilbagevendende opgaver.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">⇄</div><div><h3>Network & allocations</h3><p>Hold styr på serverens primære adresse, ekstra allocations og netværksrelaterede indstillinger.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">♙</div><div><h3>Users & permissions</h3><p>Giv andre adgang til en server med afgrænsede permissions i stedet for at dele en hovedkonto.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">◇</div><div><h3>Eggs & game logos</h3><p>Servertyper defineres via Eggs, som også kan have deres eget game-logo i server-headeren.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">◉</div><div><h3>Globalt tema</h3><p>Vælg Nodexa-accentfarve én gang og brug den gennem kunde-, server-, admin- og storefront-oplevelsen.</p></div></article>
            <article class="nx-feature-row"><div class="nx-card-icon">⌘</div><div><h3>Admin & Nodes</h3><p>Administrér nodes, servers, brugere, locations og systemopdateringer fra Nodexas adminområde.</p></div></article>
        </div>
    </div>
</section>

<section class="nx-cta">
    <div class="nx-shell"><div class="nx-cta-inner">
        <span class="nx-kicker">SAMME OPLEVELSE PÅ MOBIL</span>
        <h2>Administrér serveren fra den enhed du har ved hånden.</h2>
        <p>Nodexa-layoutet er responsivt, så console, filer og de vigtigste kontrolfunktioner også er anvendelige fra telefonen.</p>
        <div class="nx-hero-actions">@auth<a class="nx-btn nx-btn-primary" href="{{ route('index') }}">Åbn mit panel →</a>@else<a class="nx-btn nx-btn-primary" href="{{ route('auth.login') }}">Log ind →</a>@endauth<a class="nx-btn nx-btn-ghost" href="{{ route('storefront.pricing') }}">Se planer</a></div>
    </div></div>
</section>
@endsection
