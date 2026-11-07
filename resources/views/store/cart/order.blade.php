@extends('store.layout')
@section('title','Samlet ordre '.$checkout->number)
@section('content')
<section class="pagehero" style="padding-bottom:42px">
<div class="container">
    <div class="eyebrow">COMBINED ORDER</div>
    <h1>{{ $checkout->number }}</h1>
    <p class="lead">Din samlede Nodexa-checkout med {{ $items->count() }} vare(r).</p>
</div>
</section>

<section class="section" style="padding-top:34px">
<div class="container" style="max-width:1000px">
@if(session('success'))<div class="notice" style="margin-bottom:15px">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="notice orderWarn" style="margin-bottom:15px">{{ session('warning') }}</div>@endif
@if(request('stripe')==='cancelled')<div class="notice orderWarn" style="margin-bottom:15px">Stripe-betalingen blev annulleret. Ordren er gemt, og du kan prøve igen.</div>@endif
@if($errors->any())<div class="notice cartError" style="margin-bottom:15px">{{ $errors->first() }}</div>@endif

<div class="combinedGrid">
    <div class="card">
        <div class="sectionNo">SERVICES</div>
        <h2>Ordrelinjer</h2>
        @foreach($items as $item)
            <div class="combinedItem">
                <div>
                    <div class="miniLabel">{{ $item->type==='flax_vps' ? 'VPS HOSTING' : 'GAME SERVER' }}</div>
                    <strong>{{ $item->name }}</strong>
                    @if($item->type==='flax_vps')
                        <span>{{ $item->configuration_data['hostname'] ?? 'VPS' }} · {{ $item->configuration_data['os_label'] ?? 'OS' }}</span>
                    @else
                        <span>{{ $item->configuration_data['server_name'] ?? 'Server' }}</span>
                    @endif
                </div>
                <div class="combinedRight">
                    <b>{{ number_format((float)$item->unit_price,2,',','.') }} {{ $item->currency }}/md</b>
                    <span class="statusBadge {{ $item->status==='completed'?'good':($item->status==='failed'?'bad':'warn') }}">{{ strtoupper($item->status) }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <aside class="card featured">
        <div class="miniLabel">PAYMENT</div>
        <div class="price">{{ number_format((float)$checkout->total,2,',','.') }} kr <small>/ første betaling</small></div>
        <div class="orderLine"><span class="muted">Ordre</span><strong>{{ $checkout->number }}</strong></div>
        <div class="orderLine"><span class="muted">Subtotal</span><strong>{{ number_format((float)$checkout->subtotal,2,',','.') }} DKK</strong></div>
        @if((float)$checkout->discount > 0)
        <div class="orderLine"><span class="muted">Rabat{{$checkout->coupon_code?' · '.$checkout->coupon_code:''}}</span><strong style="color:#67d9a2">-{{ number_format((float)$checkout->discount,2,',','.') }} DKK</strong></div>
        @endif
        <div class="orderLine"><span class="muted">Stripe / ordre-total</span><strong>{{ number_format((float)$checkout->total,2,',','.') }} DKK</strong></div>
        <div class="orderLine"><span class="muted">Status</span><span class="statusBadge {{ in_array($checkout->status,['paid','completed'])?'good':($checkout->status==='partial'?'bad':'warn') }}">{{ strtoupper(str_replace('_',' ',$checkout->status)) }}</span></div>

        @if($checkout->status==='awaiting_payment')
            @if($stripeEnabled)
                <div class="notice" style="margin-top:14px">Stripe trækker præcis <strong>{{ number_format((float)$checkout->total,2,',','.') }} DKK</strong>. Nodexa godkender ikke betalingen, hvis Stripe returnerer et andet beløb eller en anden valuta.</div>
                <form method="POST" action="{{ route('store.cart.stripe.pay',$checkout->id) }}">
                    @csrf
                    <button class="btn primary stripePayButton" style="width:100%;margin-top:12px" type="submit">Betal {{ number_format((float)$checkout->total,2,',','.') }} DKK med Stripe →</button>
                </form>
            @else
                <div class="notice" style="margin-top:14px">Stripe er ikke aktiveret endnu. Ordren kan registreres betalt af admin.</div>
                <button class="btn primary" style="width:100%;margin-top:12px;opacity:.5" disabled>Stripe ikke konfigureret</button>
            @endif
        @elseif($checkout->status==='partial')
            <div class="notice cartError" style="margin-top:14px">Betalingen er registreret, men mindst én service kræver retry fra admin.</div>
        @else
            <div class="notice" style="margin-top:14px">Betalingen er registreret{{ $checkout->payment_method ? ' via '.ucfirst($checkout->payment_method) : '' }}, og ordrelinjerne er behandlet.</div>
        @endif

        <a class="btn" style="width:100%;margin-top:8px" href="{{ route('store.client.billing') }}">Åbn Billing</a>
    </aside>
</div>
</div>
</section>

@push('styles')
<style>
.combinedGrid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px}.combinedItem{display:flex;justify-content:space-between;gap:15px;align-items:center;padding:15px 0;border-bottom:1px solid var(--line)}.combinedItem:last-child{border-bottom:0}.combinedItem strong,.combinedItem span{display:block}.combinedItem>div>span{font-size:10px;color:#758da8;margin-top:3px}.combinedRight{text-align:right}.combinedRight b{font-size:12px;margin-bottom:6px;display:block}.cartError{border-color:#69333d;color:#ffabb8}.orderWarn{border-color:#675126;background:#241c0e;color:#f0d39b}.stripePayButton{background:linear-gradient(135deg,#635bff,#7b70ff);border-color:#7b70ff}@media(max-width:760px){.combinedGrid{grid-template-columns:1fr}.combinedItem{align-items:flex-start;flex-direction:column}.combinedRight{text-align:left}}
</style>
@endpush
@endsection