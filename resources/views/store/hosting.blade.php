@extends('store.layout')
@section('title','Server Hosting')
@section('description','Sammenlign Nodexa serverpakker og vælg den konfiguration, der passer til dit game server setup.')
@section('content')
<section class="pagehero"><div class="container"><div class="pill"><span class="statusdot"></span> Server catalog</div><h1>Hosting bygget omkring<br><span class="gradient">din server.</span></h1><p class="lead">Sammenlign ressourcer og pris. Vælg en pakke og fortsæt direkte til konfiguration.</p></div></section>
<section class="section"><div class="container"><div class="sectionhead"><div><div class="eyebrow">Server catalog</div><h2>Vælg din konfiguration.</h2><p class="muted">Alle pakker administreres fra Nodexa Storefront & Billing.</p></div><div class="hostingTabs" id="hostingPackageFilters" role="group" aria-label="Filtrér serverpakker"><button type="button" class="hostingFilter is-active" data-hosting-filter="all" aria-pressed="true">Alle servere</button><button type="button" class="hostingFilter" data-hosting-filter="minecraft" aria-pressed="false">Minecraft</button><button type="button" class="hostingFilter" data-hosting-filter="fivem" aria-pressed="false">FiveM</button><button type="button" class="hostingFilter" data-hosting-filter="other" aria-pressed="false">Andre spil</button><a href="{{route('store.vps')}}" style="padding:8px 12px;border:1px solid var(--line);border-radius:9px;color:#8fc8ff;font-size:12px;font-weight:750;background:#0b1c2d">VPS Hosting →</a></div></div>
<div class="grid" id="hostingPackageGrid">@forelse($products as $p)<article class="card {{$p->featured?'featured':''}}" data-hosting-game="{{ mb_strtolower($p->game) }}"><div class="productTop"><span class="tag">{{$p->game}}</span>@if($p->featured)<span class="miniLabel" style="color:#a996ff">★ ANBEFALET</span>@endif</div><h3 style="font-size:25px;margin-bottom:6px">{{$p->name}}</h3><p class="muted" style="min-height:46px">{{$p->description ?: 'Serverkonfiguration klar til Nodexa Control Panel.'}}</p>@if($globalSale)
<div class="miniLabel" style="color:#75e3b1;margin:8px 0 4px">{{ $globalSale->label }} · -{{ number_format($globalSale->percent,0,',','.') }}%</div>
<div style="font-size:11px;color:#637b96;text-decoration:line-through">{{number_format($p->price_monthly,2,',','.')}} kr</div>
<div class="price">{{number_format(round((float)$p->price_monthly*(1-((float)$globalSale->percent/100)),2),2,',','.')}} kr <small>/ måned</small></div>
@else
<div class="price">{{number_format($p->price_monthly,0,',','.')}} kr <small>/ måned</small></div>
@endif<div class="specs"><span class="resource">{{number_format($p->memory / 1024, $p->memory % 1024 ? 1 : 0, ',', '.')}} GB RAM</span><span class="resource">{{$p->cpu}}% CPU</span><span class="resource">{{number_format($p->disk / 1024, $p->disk % 1024 ? 1 : 0, ',', '.')}} GB SSD</span><span class="resource">{{$p->backups}} backups</span><span class="resource">{{$p->databases}} databaser</span><span class="resource">{{$p->allocations}} allocations</span></div><div class="cardAction"><a class="btn primary" style="width:100%" href="{{route('store.product',$p)}}">Konfigurér server →</a></div></article>@empty<article class="card" style="grid-column:1/-1;text-align:center;padding:55px"><div class="miniLabel">SERVER CATALOG</div><h3 style="font-size:25px">Catalog er tomt</h3><p class="muted">Aktivér et produkt i Admin → Storefront & Billing for at vise det her.</p></article>@endforelse
@if($products->isNotEmpty())
<article id="hostingNoMatches" class="card" style="display:none;grid-column:1/-1;text-align:center;padding:38px" role="status"><h3>Ingen pakker i denne kategori endnu</h3><p class="muted">Vælg en anden spiltype for at se tilgængelige pakker.</p></article>
@endif
</div></div></section>
<section class="section alt"><div class="container"><div class="sectionhead"><div><div class="eyebrow">Inkluderet</div><h2>Samme platform. Uanset pakke.</h2></div></div><div class="grid"><div class="card"><div class="miniLabel">CONTROL</div><h3>Nodexa Control Panel</h3><p class="muted">Live konsol, serverstatus og ressourcer fra ét workspace.</p></div><div class="card"><div class="miniLabel">ACCESS</div><h3>Filer & SFTP</h3><p class="muted">Administrér filer gennem browseren eller forbind direkte via SFTP.</p></div><div class="card"><div class="miniLabel">PROTECTION</div><h3>Backups & automation</h3><p class="muted">Brug backups og schedules til at gøre den daglige serverdrift enklere.</p></div></div></div></section>
<script>
(function () {
    const filters = document.querySelectorAll('#hostingPackageFilters [data-hosting-filter]');
    const cards = document.querySelectorAll('#hostingPackageGrid [data-hosting-game]');
    const empty = document.getElementById('hostingNoMatches');
    if (!filters.length) return;

    filters.forEach(function (button) {
        button.addEventListener('click', function () {
            const selected = button.dataset.hostingFilter;
            filters.forEach(function (item) {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            let count = 0;
            cards.forEach(function (card) {
                const game = (card.dataset.hostingGame || '').toLowerCase();
                const visible = selected === 'all'
                    || (selected === 'other' && game !== 'minecraft' && game !== 'fivem')
                    || selected === game;
                card.hidden = !visible;
                card.style.display = visible ? '' : 'none';
                if (visible) count++;
            });
            if (empty) empty.style.display = count ? 'none' : '';
        });
    });
})();
</script>
@push('styles')
<style>
.hostingTabs .hostingFilter{padding:8px 12px;border:1px solid var(--line);border-radius:9px;background:transparent;color:#7890aa;font:inherit;font-size:12px;font-weight:750;cursor:pointer;min-height:36px}
.hostingTabs .hostingFilter:hover{color:#d8e9fc;background:#0c2137}
.hostingTabs .hostingFilter.is-active{background:#102640;border-color:#28517a;color:#c7ddf5}
.hostingTabs .hostingFilter:focus-visible{outline:2px solid #8bbdfb;outline-offset:2px}
#hostingPackageGrid [hidden]{display:none!important}
</style>
@endpush
@endsection
