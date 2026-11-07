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
        <div class="price">{{ number_format((float)$checkout->total,2,',','.') }} kr <small>/ måned samlet</small></div>
        <div class="orderLine"><span class="muted">Ordre</span><strong>{{ $checkout->number }}</strong></div>
        <div class="orderLine"><span class="muted">Status</span><span class="statusBadge {{ in_array($checkout->status,['paid','completed'])?'good':($checkout->status==='partial'?'bad':'warn') }}">{{ strtoupper(str_replace('_',' ',$checkout->status)) }}</span></div>

        @if($checkout->status==='awaiting_payment')
            <div class="notice" style="margin-top:14px">Betalingsgateway er endnu ikke aktiveret i Nodexa. Ordren er gemt som én samlet checkout og kan registreres betalt samlet af admin.</div>
            <button class="btn primary" style="width:100%;margin-top:12px;opacity:.5" disabled>Betal samlet</button>
        @elseif($checkout->status==='partial')
            <div class="notice cartError" style="margin-top:14px">Betalingen er registreret, men mindst én service kræver retry fra admin.</div>
        @else
            <div class="notice" style="margin-top:14px">Betalingen er registreret, og ordrelinjerne er behandlet.</div>
        @endif

        <a class="btn" style="width:100%;margin-top:8px" href="{{ route('store.client.billing') }}">Åbn Billing</a>
    </aside>
</div>
</div>
</section>

@push('styles')
<style>
.combinedGrid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px}.combinedItem{display:flex;justify-content:space-between;gap:15px;align-items:center;padding:15px 0;border-bottom:1px solid var(--line)}.combinedItem:last-child{border-bottom:0}.combinedItem strong,.combinedItem span{display:block}.combinedItem>div>span{font-size:10px;color:#758da8;margin-top:3px}.combinedRight{text-align:right}.combinedRight b{font-size:12px;margin-bottom:6px;display:block}.cartError{border-color:#69333d;color:#ffabb8}@media(max-width:760px){.combinedGrid{grid-template-columns:1fr}.combinedItem{align-items:flex-start;flex-direction:column}.combinedRight{text-align:left}}
</style>
@endpush
@endsection