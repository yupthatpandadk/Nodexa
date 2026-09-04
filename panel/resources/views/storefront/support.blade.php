@extends('storefront.layout')

@section('title', 'Support')
@section('description', 'Få hjælp til Nodexa game server hosting, paneladgang og serveropsætning.')

@section('content')
<section class="nx-page-hero">
    <div class="nx-shell">
        <span class="nx-eyebrow">SUPPORT & HJÆLP</span>
        <h1>Hjælp når du<br><em>har brug for den.</em></h1>
        <p>Brug Nodexa-panelet som udgangspunkt for serverdrift og support. Her kan du kontrollere serverens status og samle de oplysninger, der gør fejlsøgning hurtigere.</p>
    </div>
</section>

<section class="nx-section nx-section-tight">
    <div class="nx-shell nx-support-grid">
        <div class="nx-support-panel">
            <span class="nx-kicker">KOM GODT VIDERE</span>
            <h2>De vigtigste steder at starte.</h2>
            <p>Ved problemer med en game server er console, Activity og ressourceforbrug typisk de første steder at kigge. Nodexa holder dem tæt på serveren.</p>
            <div class="nx-check-grid">
                <div class="nx-check">Kontrollér live console</div>
                <div class="nx-check">Se CPU og RAM</div>
                <div class="nx-check">Kontrollér serverfiler</div>
                <div class="nx-check">Se seneste aktivitet</div>
                <div class="nx-check">Kontrollér backups</div>
                <div class="nx-check">Tjek node-status</div>
            </div>
            <div class="nx-hero-actions">
                @auth<a class="nx-btn nx-btn-primary" href="{{ route('index') }}">Åbn mit panel →</a>@else<a class="nx-btn nx-btn-primary" href="{{ route('auth.login') }}">Log ind →</a>@endauth
                <a class="nx-btn nx-btn-ghost" href="{{ route('storefront.features') }}">Se funktioner</a>
            </div>
        </div>
        <aside class="nx-status-card">
            <div class="nx-status-orb">⌁</div>
            <h3>Systemstatus</h3>
            <p>Storefronten viser ikke en opdigtet live-status. Den faktiske server- og node-status findes i Nodexa-panelet, hvor den kommer fra den tilknyttede infrastruktur.</p>
            <a class="nx-card-link" href="{{ route('auth.login') }}">Gå til Nodexa →</a>
        </aside>
    </div>
</section>

<section class="nx-section">
    <div class="nx-shell">
        <div class="nx-section-head">
            <div><span class="nx-kicker">FØR DU KONTAKTER SUPPORT</span><h2>Information der gør fejlsøgning hurtigere.</h2></div>
            <p>Del ikke tokens, passwords eller andre hemmeligheder. Et kort logudsnit og tidspunktet for fejlen er normalt langt mere nyttigt.</p>
        </div>
        <div class="nx-card-grid">
            <article class="nx-card"><div class="nx-card-icon">1</div><h3>Beskriv problemet</h3><p>Fortæl hvad du forsøgte at gøre, hvad du forventede, og hvad der skete i stedet.</p></article>
            <article class="nx-card"><div class="nx-card-icon">2</div><h3>Medtag relevante logs</h3><p>Send de seneste relevante consolelinjer omkring fejlen — uden credentials eller hemmelige tokens.</p></article>
            <article class="nx-card"><div class="nx-card-icon">3</div><h3>Servertype & tidspunkt</h3><p>Angiv game/servertype og cirka hvornår fejlen skete, så hændelsen er lettere at finde.</p></article>
        </div>
    </div>
</section>

<section class="nx-cta">
    <div class="nx-shell"><div class="nx-cta-inner">
        <span class="nx-kicker">NY SERVER?</span>
        <h2>Find den rigtige serverprofil først.</h2>
        <p>Se game hosting og de typiske ressourceprofiler, før du vælger setup.</p>
        <div class="nx-hero-actions"><a class="nx-btn nx-btn-primary" href="{{ route('storefront.games') }}">Se game hosting →</a><a class="nx-btn nx-btn-ghost" href="{{ route('storefront.pricing') }}">Se planer</a></div>
    </div></div>
</section>
@endsection
