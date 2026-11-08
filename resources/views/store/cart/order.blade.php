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
        <div class="orderLine"><span class="muted">Normalpris</span><strong>{{ number_format((float)$checkout->subtotal,2,',','.') }} DKK</strong></div>
        @if((float)($checkout->global_sale_discount ?? 0) > 0)
        <div class="orderLine"><span class="muted">Globalt udsalg · {{ number_format((float)$checkout->global_sale_percent,0,',','.') }}%</span><strong style="color:#67d9a2">-{{ number_format((float)$checkout->global_sale_discount,2,',','.') }} DKK</strong></div>
        <div class="orderLine"><span class="muted">Udsalgsperiode</span><strong>{{ $checkout->global_sale_duration_months }} måned(er)</strong></div>
        @endif
        @if((float)$checkout->discount > 0)
        <div class="orderLine"><span class="muted">Rabat{{$checkout->coupon_code?' · '.$checkout->coupon_code:''}}</span><strong style="color:#67d9a2">-{{ number_format((float)$checkout->discount,2,',','.') }} DKK</strong></div>
        <div class="orderLine"><span class="muted">Rabatperiode</span><strong>{{ $checkout->coupon_duration_months ?? 1 }} måned(er)</strong></div>
        @endif
        @php
            $orderCents = (int) round((float) $checkout->total * 100);
            $orderWalletCents = $checkout->payment_method === 'wallet'
                ? $orderCents
                : (int) ($checkout->wallet_applied_cents ?? 0);
            $orderStripeCents = max(0, $orderCents - $orderWalletCents);
        @endphp
        @if($orderWalletCents > 0)
            <div class="orderLine"><span class="muted">Nodexa Wallet {{ $checkout->wallet_hold_state === 'held' ? '(reserveret)' : '' }}</span><strong style="color:#78ddaf">−{{ number_format($orderWalletCents / 100,2,',','.') }} DKK</strong></div>
        @endif
        <div class="orderLine"><span class="muted">{{ $orderStripeCents > 0 ? 'Resterende betaling med Stripe' : 'Resterende betaling' }}</span><strong>{{ number_format($orderStripeCents / 100,2,',','.') }} DKK</strong></div>
        <div class="orderLine"><span class="muted">Status</span><span class="statusBadge {{ in_array($checkout->status,['paid','completed'])?'good':($checkout->status==='partial'?'bad':'warn') }}">{{ strtoupper(str_replace('_',' ',$checkout->status)) }}</span></div>

        @if($checkout->status==='awaiting_payment')
            @if(!$checkout->stripe_session_id && $walletBalanceCents >= (int) round(((float) $checkout->total) * 100) && $walletBalanceCents > 0)
                <form method="POST" action="{{ route('store.cart.wallet.pay',$checkout->id) }}">
                    @csrf
                    <button class="btn primary" type="submit" style="width:100%;margin-top:12px;background:#164f54;border:1px solid #3c8a83">Betal med Wallet · {{ number_format($checkout->total,2,',','.') }} DKK</button>
                </form>
            @else
                <div class="micro" style="margin:12px 0 4px">Wallet-saldo: {{ number_format($walletBalanceCents/100,2,',','.') }} DKK. <a href="{{route('store.client.wallet')}}" style="color:#a1cfff">Se Wallet</a></div>
            @endif
            @if($stripeEnabled)
                <div class="notice" style="margin-top:14px">Wallet-kredit bruges først. Stripe betaler det resterende beløb på <strong>{{ number_format($orderStripeCents / 100,2,',','.') }} DKK</strong>. Betalingen bliver først færdigregistreret, når Stripe bekræfter restbeløbet.</div>
                <form method="POST" action="{{ route('store.cart.stripe.pay',$checkout->id) }}">
                    @csrf
                    <button class="btn primary stripePayButton" style="width:100%;margin-top:12px" type="submit">Betal resten med Stripe →</button>
                </form>
            @if($checkout->stripe_session_id && !$checkout->paid_at)
                <form method="POST" action="{{ route('store.cart.stripe.cancel', $checkout->id) }}" style="margin-top:10px" onsubmit="return confirm('Afbryd Stripe-betalingen og frigiv reserveret Wallet-kredit?');">
                    @csrf
                    <button class="btn" type="submit" style="width:100%">Annullér Stripe-betaling og frigiv kredit</button>
                </form>
            @endif
            @else
                <div class="notice" style="margin-top:14px">Stripe er ikke aktiveret endnu. Ordren kan registreres betalt af admin.</div>
                <button class="btn primary" style="width:100%;margin-top:12px;opacity:.5" disabled>Stripe ikke konfigureret</button>
            @endif
        @elseif($checkout->status==='partial')
            <div class="notice cartError" style="margin-top:14px">Betalingen er registreret, men mindst én service kræver retry fra admin.</div>
        @else
            <div class="notice" style="margin-top:14px">Betalingen er registreret{{ $checkout->payment_method ? ' via '.ucfirst($checkout->payment_method) : '' }}, og ordrelinjerne er behandlet.</div>
        @endif

        @if($checkout->status === 'awaiting_payment' && !$checkout->paid_at)
            <form method="POST" action="{{ route('store.cart.order.remove', $checkout->id) }}" style="margin-top:10px" onsubmit="return confirm('Vil du annullere og fjerne denne ubetalte ordre? En eventuel Stripe-betalingsside bliver lukket.');">
                @csrf
                @method('DELETE')
                <button class="btn" type="submit" style="width:100%;border-color:#6d3544;color:#f5a5b5;background:#261722">Slet ubetalt ordre</button>
            </form>
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