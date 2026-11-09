@extends('store.layout')
@section('title','VPS Hosting')
@section('description','Bestil VPS hosting gennem Nodexa. VPS’en provisioneres automatisk via Nodexas VPS-platform.')
@section('content')
<section class="pagehero">
    <div class="container">
        <div class="pill"><span class="statusdot"></span> VPS HOSTING</div>
        <h1>VPS hosting.<br><span class="gradient">Direkte fra Nodexa.</span></h1>
        <p class="lead">Vælg din VPS-pakke, operativsystem og hostname. Efter betaling provisionerer Nodexa automatisk VPS’en på Nodexas serverplatform.</p>
    </div>
</section>

<section class="section">
<div class="container">
    <div class="sectionhead">
        <div><div class="eyebrow">VPS CATALOG</div><h2>Vælg din VPS.</h2><p class="muted">Månedlig billing gennem Nodexa. Provisionering og lifecycle håndteres automatisk.</p></div>
    </div>

    @if(!$configured)
        <div class="notice" style="margin-bottom:20px">VPS-bestilling er midlertidigt utilgængelig, fordi VPS-tjenesten ikke er tilgængelig.</div>
    @endif

    <div class="grid">
        @forelse($products as $product)
        <article class="card {{ $product->featured ? 'featured' : '' }}">
            <div class="productTop">
                <span class="tag">VPS</span>
                @if($product->featured)<span class="miniLabel" style="color:#a996ff">★ ANBEFALET</span>@endif
            </div>
            <h3 style="font-size:25px;margin-bottom:6px">{{ \Pterodactyl\Services\Nodexa\CustomerBranding::label($product->name) }}</h3>
            <p class="muted" style="min-height:48px">{{ \Pterodactyl\Services\Nodexa\CustomerBranding::label($product->description, 'Virtuel privat server med automatisk provisionering.') }}</p>
            @if($globalSale)
            <div class="miniLabel" style="color:#75e3b1;margin:8px 0 4px">{{ $globalSale->label }} · -{{ number_format($globalSale->percent,0,',','.') }}%</div>
            <div style="font-size:11px;color:#637b96;text-decoration:line-through">{{ number_format((float)$product->monthly_price,2,',','.') }} kr</div>
            <div class="price">{{ number_format(round((float)$product->monthly_price*(1-((float)$globalSale->percent/100)),2),2,',','.') }} kr <small>/ måned</small></div>
            @else
            <div class="price">{{ number_format((float)$product->monthly_price,2,',','.') }} kr <small>/ måned</small></div>
            @endif
            <div class="specs">
                <span class="resource">{{ $product->vcpu ?: '—' }} vCPU</span>
                <span class="resource">{{ $product->memory_mb ? number_format($product->memory_mb/1024, $product->memory_mb % 1024 ? 1 : 0, ',', '.') . ' GB' : '—' }} RAM</span>
                <span class="resource">{{ $product->disk_gb ?: '—' }} GB SSD</span>
                <span class="resource">{{ $product->ipv4_count }} IPv4</span>
                <span class="resource">{{ $product->bandwidth ?: 'Bandwidth efter pakke' }}</span>
                <span class="resource">Månedlig billing</span>
            </div>
            <div class="cardAction"><a class="btn primary" style="width:100%" href="{{ route('store.vps.product',$product->slug) }}">Konfigurér VPS →</a></div>
        </article>
        @empty
        <article class="card" style="grid-column:1/-1;text-align:center;padding:55px">
            <div class="miniLabel">VPS CATALOG</div><h3 style="font-size:25px">Ingen VPS-pakker endnu</h3>
            <p class="muted">VPS-produkter bliver vist her, når de er aktiveret i Nodexas VPS-katalog.</p>
        </article>
        @endforelse
    </div>
</div>
</section>

<section class="section alt">
<div class="container">
    <div class="sectionhead"><div><div class="eyebrow">AUTOMATION</div><h2>Bestil, betal og administrér hos Nodexa.</h2></div></div>
    <div class="grid">
        <div class="card"><div class="miniLabel">01 · ORDER</div><h3>Nodexa checkout</h3><p class="muted">Kunden bestiller VPS’en fra Nodexa og får Nodexa-fakturaer.</p></div>
        <div class="card"><div class="miniLabel">02 · PROVISION</div><h3>Automatisk VPS-provisionering</h3><p class="muted">Efter registreret betaling opretter Nodexa automatisk VPS’en på Nodexas serverplatform.</p></div>
        <div class="card"><div class="miniLabel">03 · MANAGE</div><h3>Én kundeportal</h3><p class="muted">Start, shutdown, restart, status og billing håndteres fra Nodexa Client Area.</p></div>
    </div>
</div>
</section>
@endsection