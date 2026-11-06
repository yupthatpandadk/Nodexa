@extends('store.layout')
@section('title','VPS Checkout')
@section('content')
<section class="pagehero" style="padding-bottom:46px">
<div class="container">
    <div class="eyebrow">VPS CHECKOUT</div>
    <h1>Gennemgå din VPS-ordre.</h1>
    <p class="lead">VPS’en provisioneres hos Flax, når betalingen er registreret i Nodexa.</p>
    <div class="steps"><div class="step"><i>1</i><span>Konfiguration</span></div><i class="stepSep"></i><div class="step active"><i>2</i><span>Checkout</span></div><i class="stepSep"></i><div class="step"><i>3</i><span>Provisionering</span></div></div>
</div>
</section>

<section class="section" style="padding-top:38px">
<div class="container" style="max-width:1000px">
<div class="checkout-grid">
    <div class="card">
        <div class="sectionNo">VPS SERVICE #{{ $service->id }}</div>
        <h2>{{ $service->product_name }}</h2>
        <p class="muted">{{ $service->hostname }}</p>
        <div style="margin-top:20px">
            @foreach([
                'Hostname'=>$service->hostname,
                'Operativsystem'=>$service->os_label,
                'vCPU'=>$service->vcpu ?: '—',
                'RAM'=>$service->memory_mb ? number_format($service->memory_mb/1024,1,',','.') . ' GB' : '—',
                'Disk'=>$service->disk_gb ? $service->disk_gb . ' GB' : '—',
                'IPv4'=>$service->ipv4_count,
                'Bandwidth'=>$service->bandwidth ?: 'Efter pakke'
            ] as $label=>$value)
                <div class="orderLine"><span class="muted">{{ $label }}</span><strong>{{ $value }}</strong></div>
            @endforeach
        </div>
    </div>

    <aside class="card featured">
        <div class="miniLabel">ORDER SUMMARY</div>
        <div class="price">{{ number_format((float)$service->monthly_price,2,',','.') }} kr <small>/ måned</small></div>
        <div class="orderLine"><span class="muted">Faktura</span><strong>{{ $service->invoice_number ?: '—' }}</strong></div>
        <div class="orderLine"><span class="muted">Status</span><span class="statusBadge">{{ str_replace('_',' ',$service->invoice_status ?: $service->status) }}</span></div>
        <div class="notice" style="margin-top:14px">Når fakturaen markeres betalt, kalder Nodexa Flax API’et og opretter VPS’en automatisk.</div>
        <a class="btn primary" style="width:100%;margin-top:12px" href="{{ route('store.client.billing') }}">Åbn Billing →</a>
        <a class="btn" style="width:100%;margin-top:9px" href="{{ route('store.client.vps') }}">Mine VPS’er</a>
    </aside>
</div>
</div>
</section>
@push('styles')<style>.checkout-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px}@media(max-width:760px){.checkout-grid{grid-template-columns:1fr}}</style>@endpush
@endsection