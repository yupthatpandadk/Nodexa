@extends('store.layout')
@section('title','Samlet checkout')
@section('content')
<section class="pagehero" style="padding-bottom:42px">
<div class="container">
    <div class="eyebrow">CART CHECKOUT</div>
    <h1>Betal samlet.</h1>
    <p class="lead">Alle varer i kurven samles under ét Nodexa-ordrenummer.</p>
    <div class="steps"><div class="step"><i>1</i><span>Kurv</span></div><i class="stepSep"></i><div class="step active"><i>2</i><span>Checkout</span></div><i class="stepSep"></i><div class="step"><i>3</i><span>Aktivering</span></div></div>
</div>
</section>

<section class="section" style="padding-top:35px">
<div class="container" style="max-width:1000px">
<div class="checkoutGrid">
    <div class="card">
        <div class="sectionNo">ORDER ITEMS</div>
        <h2>{{ $summary['count'] }} {{ $summary['count']===1 ? 'vare' : 'varer' }}</h2>

        @foreach($summary['items'] as $item)
            <div class="checkoutItem">
                <div>
                    <div class="miniLabel">{{ $item['type']==='flax_vps' ? 'VPS' : 'GAME SERVER' }}</div>
                    <strong>{{ $item['name'] }}</strong>
                    @if($item['type']==='flax_vps')
                        <span>{{ $item['configuration']['hostname'] }} · {{ $item['configuration']['os_label'] }}</span>
                    @else
                        <span>{{ $item['configuration']['server_name'] }} · {{ $item['meta']['game'] }}</span>
                    @endif
                </div>
                <b>{{ number_format((float)$item['price'],2,',','.') }} {{ $item['currency'] }}/md</b>
            </div>
        @endforeach
    </div>

    <aside class="card featured">
        <div class="miniLabel">SAMLET CHECKOUT</div>
        <div class="price">{{ number_format((float)$summary['total'],2,',','.') }} kr <small>/ måned samlet</small></div>
        <div class="orderLine"><span class="muted">Varer</span><strong>{{ $summary['count'] }}</strong></div>
        <div class="orderLine"><span class="muted">Valuta</span><strong>DKK</strong></div>
        <div class="notice" style="margin-top:14px">Nodexa opretter én samlet ordre. Når betalingen registreres, behandles alle services i ordren.</div>

        <form method="POST" action="{{ route('store.cart.place') }}">
            @csrf
            <button class="btn primary" style="width:100%;margin-top:14px">Opret samlet ordre →</button>
        </form>
        <a class="btn" style="width:100%;margin-top:8px" href="{{ route('store.cart') }}">← Tilbage til kurv</a>
    </aside>
</div>
</div>
</section>

@push('styles')
<style>
.checkoutGrid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px}.checkoutItem{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:15px 0;border-bottom:1px solid var(--line)}.checkoutItem:last-child{border-bottom:0}.checkoutItem strong,.checkoutItem span{display:block}.checkoutItem span{font-size:10px;color:#758da8;margin-top:3px}.checkoutItem>b{white-space:nowrap;font-size:12px}@media(max-width:760px){.checkoutGrid{grid-template-columns:1fr}.checkoutItem{align-items:flex-start;flex-direction:column}}
</style>
@endpush
@endsection