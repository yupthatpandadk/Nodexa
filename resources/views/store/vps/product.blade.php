@extends('store.layout')
@section('title',$product->name.' VPS')
@section('description',$product->description ?: 'Konfigurér og bestil din VPS.')
@section('content')
<section class="pagehero">
<div class="container">
    <span class="tag">VPS · FLAX RESELLER</span>
    <h1>{{ $product->name }}</h1>
    <p class="lead">{{ $product->description ?: 'Vælg VPS-navn og operativsystem. VPS’en oprettes automatisk efter betaling.' }}</p>
    <div class="steps"><div class="step active"><i>1</i><span>Konfiguration</span></div><i class="stepSep"></i><div class="step"><i>2</i><span>Checkout</span></div><i class="stepSep"></i><div class="step"><i>3</i><span>Provisionering</span></div></div>
</div>
</section>

<section class="section">
<div class="container" style="max-width:1050px">
<div class="product-layout">
    <div>
        <div class="card">
            <div class="sectionNo">VPS SPECIFICATION</div>
            <h2>Din VPS-pakke</h2>
            <div class="specs" style="font-size:14px;gap:13px">
                <span class="resource"><b>{{ $product->vcpu ?: '—' }}</b>&nbsp; vCPU</span>
                <span class="resource"><b>{{ $product->memory_mb ? number_format($product->memory_mb/1024, $product->memory_mb % 1024 ? 1 : 0, ',', '.') . ' GB' : '—' }}</b>&nbsp; RAM</span>
                <span class="resource"><b>{{ $product->disk_gb ?: '—' }} GB</b>&nbsp; SSD</span>
                <span class="resource"><b>{{ $product->ipv4_count }}</b>&nbsp; IPv4</span>
                <span class="resource"><b>{{ $product->bandwidth ?: 'Efter pakke' }}</b>&nbsp; bandwidth</span>
                <span class="resource"><b>Flax</b>&nbsp; upstream</span>
            </div>
        </div>
        <div class="card" style="margin-top:15px">
            <div class="sectionNo">LIFECYCLE</div>
            <h3>Automatisk reseller provisioning</h3>
            <p class="muted">Første betaling opretter VPS’en hos Flax. Efterfølgende Nodexa-fakturaer forlænger upstream-servicen automatisk. Forfaldne fakturaer kan suspendere VPS’en.</p>
        </div>
    </div>

    <aside class="card featured">
        <div class="productTop"><span class="tag">Månedlig VPS</span><span class="miniLabel">{{ $product->currency }}</span></div>
        <div class="price">{{ number_format((float)$product->monthly_price,2,',','.') }} kr <small>/ måned</small></div>

        @if(!$configured)
            <div class="notice">VPS-reseller-integrationen er ikke aktiv lige nu.</div>
        @elseif($templates->isEmpty())
            <div class="notice">Der er endnu ikke synkroniseret OS-templates fra Flax.</div>
        @endif

        @auth
        <form method="POST" action="{{ route('store.cart.add-vps',$product->slug) }}">
            @csrf
            <label class="formLabel" for="hostname">VPS-navn</label>
            <input class="input" id="hostname" name="hostname" value="{{ old('hostname') }}" required minlength="3" maxlength="190" placeholder="Min VPS">

            <label class="formLabel" for="os_template_id">Operativsystem</label>
            <select class="input" id="os_template_id" name="os_template_id" required>
                <option value="">Vælg OS…</option>
                @foreach($templates as $template)
                    <option value="{{ $template->upstream_id }}" {{ (string)old('os_template_id')===(string)$template->upstream_id?'selected':'' }}>{{ $template->label }}</option>
                @endforeach
            </select>

            @if($errors->any())<div class="notice" style="margin-top:12px;border-color:#69333d;color:#ffabb8">{{ $errors->first() }}</div>@endif
            <button class="btn primary" style="width:100%;margin-top:14px" {{ !$configured || $templates->isEmpty() ? 'disabled' : '' }}>Læg i kurv →</button>
        </form>
        @else
        <a class="btn primary" style="width:100%;margin-top:18px" href="/auth/register">Register for at bestille →</a>
        <a class="btn" style="width:100%;margin-top:9px" href="/auth/login">Har du allerede en konto? Log ind</a>
        @endauth
    </aside>
</div>
</div>
</section>
@push('styles')<style>.product-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px}.input select{appearance:auto}@media(max-width:760px){.product-layout{grid-template-columns:1fr}}</style>@endpush
@endsection