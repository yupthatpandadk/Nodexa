@extends('store.layout')
@section('title','Billing · Client Area')
@section('content')
<div class="container clientShell">
<aside class="clientSide">
    <div class="clientIdentity"><div class="avatar">{{strtoupper(substr(auth()->user()->name_first ?: auth()->user()->username,0,1))}}</div><div><strong>{{auth()->user()->name}}</strong><small>{{auth()->user()->email}}</small></div></div>
    <nav>
        <a href="{{route('store.client')}}">⌂ <span>Oversigt</span></a>
        <a href="{{route('store.client.servers')}}">▣ <span>Mine servere</span></a><a href="{{route('store.client.vps')}}">☁ <span>Mine VPS’er</span></a>
        <a class="active" href="{{route('store.client.billing')}}">▤ <span>Billing & ordrer</span></a>
        <a href="{{route('store.client.hub')}}">◆ <span>Services Hub</span></a>
        <a href="{{route('store.client.profile')}}">◎ <span>Min profil</span></a>
        <a href="{{route('store.client.tickets')}}">? <span>Support Tickets</span></a>
        <a href="{{route('store.hosting')}}">＋ <span>Bestil server</span></a>
    </nav>
    <div class="clientPanel"><div class="miniLabel">CONTROL PANEL</div><p>Avanceret serveradministration</p><a class="btn" href="{{rtrim(config('app.url'), '/')}}/panel">Åbn panel ↗</a></div>
</aside>
<main>
    <div class="clientHead"><div><div class="eyebrow">Client Area / Billing</div><h1>Billing & fakturaer</h1><p class="muted">Abonnementer, fakturaer, ordrer og checkout samlet ét sted.</p></div></div>
    @if(session('success'))<div class="notice" style="margin:12px 0">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice" style="margin:12px 0;border-color:#764454;color:#ffb9c5">{{ $errors->first() }}</div>@endif

    <div class="clientCards">
        <div class="clientStat"><span>BETALINGER</span><strong>{{$invoices->count()+$cartCheckouts->count()}}</strong></div>
        <div class="clientStat"><span>FORFALDNE</span><strong>{{$invoices->where('status','overdue')->count()}}</strong></div>
        <div class="clientStat"><span>ABONNEMENTER</span><strong>{{$subscriptions->where('status','active')->count()}}</strong></div>
    </div>

    <div class="clientSection">
        <div class="sectionTitleRow"><div><div class="miniLabel">COMBINED CHECKOUTS</div><h2>Samlede ordrer</h2></div></div>
        @forelse($cartCheckouts as $cartCheckout)
        <div class="clientRow">
            <div>
                <div class="miniLabel">{{ $cartCheckout->number }}</div>
                <h3>{{ number_format((float)$cartCheckout->total,2,',','.') }} {{ $cartCheckout->currency }}</h3>
                <p>Oprettet {{ \Carbon\Carbon::parse($cartCheckout->created_at)->format('d.m.Y H:i') }} · Én samlet checkout</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <span class="statusBadge {{ in_array($cartCheckout->status,['paid','completed'])?'good':($cartCheckout->status==='partial'?'bad':'warn') }}">{{ strtoupper(str_replace('_',' ',$cartCheckout->status)) }}</span>
                <a class="btn" href="{{ route('store.cart.order',$cartCheckout->id) }}">Se ordre</a>
                @if($cartCheckout->status === 'awaiting_payment' && !$cartCheckout->paid_at)
                    <form method="POST" action="{{ route('store.cart.order.remove',$cartCheckout->id) }}" onsubmit="return confirm('Slet denne ubetalte ordre?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn" style="color:#f3a0b0;border-color:#633447;background:#251621">Slet</button>
                    </form>
                @endif
            </div>
        </div>
        @empty<div class="notice">Ingen samlede ordrer endnu.</div>@endforelse
    </div>

    <div class="clientSection">
        <div class="sectionTitleRow"><div><div class="miniLabel">INVOICES</div><h2>Fakturaer</h2></div></div>
        @forelse($invoices as $invoice)
        <div class="clientRow">
            <div><div class="miniLabel">{{$invoice->number}}</div><h3>{{number_format($invoice->total,2,',','.')}} {{$invoice->currency}}</h3><p>Oprettet {{\Carbon\Carbon::parse($invoice->created_at)->format('d.m.Y')}} · Forfalder {{$invoice->due_at ? \Carbon\Carbon::parse($invoice->due_at)->format('d.m.Y') : '—'}}@if((float)($invoice->discount ?? 0)>0) · <span class="discountInline">Rabat -{{number_format($invoice->discount,2,',','.')}} {{$invoice->currency}}</span>@endif</p></div>
            <div style="display:flex;gap:8px;align-items:center"><span class="statusBadge {{$invoice->status==='paid'?'good':($invoice->status==='overdue'?'bad':'warn')}}">{{strtoupper($invoice->status)}}</span></div>
        </div>
        @empty<div class="notice">Ingen fakturaer endnu.</div>@endforelse
    </div>

    <div class="clientSection">
        <div class="sectionTitleRow"><div><div class="miniLabel">SUBSCRIPTIONS</div><h2>Abonnementer</h2></div></div>
        @forelse($subscriptions as $subscription)
        <div class="clientRow">
            <div><div class="miniLabel">SUBSCRIPTION #{{$subscription->id}}</div><h3>{{number_format($subscription->amount,2,',','.')}} {{$subscription->currency}} / {{$subscription->interval}}</h3><p>Næste faktura {{$subscription->next_invoice_at ? \Carbon\Carbon::parse($subscription->next_invoice_at)->format('d.m.Y') : '—'}}
@if((int)($subscription->sale_months_used ?? 0) < (int)($subscription->sale_months_total ?? 0)) · <span class="discountInline">Udsalg: -{{number_format((float)$subscription->sale_discount_amount,2,',','.')}} {{$subscription->currency}} · måned {{((int)$subscription->sale_months_used)+1}}/{{(int)$subscription->sale_months_total}}</span>@endif
@if(!empty($subscription->discount_code) && (int)($subscription->discount_months_used ?? 0) < (int)($subscription->discount_months_total ?? 0)) · <span class="discountInline">{{$subscription->discount_code}}: -{{number_format((float)$subscription->discount_amount,2,',','.')}} {{$subscription->currency}} · måned {{((int)$subscription->discount_months_used)+1}}/{{(int)$subscription->discount_months_total}}</span>@endif</p></div>
            <span class="statusBadge {{$subscription->status==='active'?'good':''}}">{{strtoupper($subscription->status)}}</span>
        </div>
        @empty<div class="notice">Ingen aktive abonnementer endnu.</div>@endforelse
    </div>

    <div class="clientSection">
        <div class="sectionTitleRow"><div><div class="miniLabel">ORDERS</div><h2>Ordrehistorik</h2></div></div>
        @forelse($orders as $o)
        <div class="clientRow">
            <div><div class="miniLabel">ORDER #{{$o->id}}</div><h3>{{$o->product->name ?? 'Serverprodukt'}}</h3><p>{{$o->server_name}} · {{number_format($o->amount,2,',','.')}} {{$o->currency}}</p></div>
            <div style="display:flex;gap:8px;align-items:center"><span class="statusBadge">{{$o->status}}</span>@if($o->status==='awaiting_payment')<a class="btn" href="{{route('store.checkout',$o)}}">Checkout</a>@endif
                @if(in_array($o->status,['awaiting_payment','failed'],true) && !$o->paid_at && !$o->payment_reference && !$o->server_id)
                <form method="POST" action="{{route('store.order.remove',$o)}}" onsubmit="return confirm('Slet denne ubetalte ordre?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn" style="color:#f3a0b0;border-color:#633447;background:#251621">Slet</button>
                </form>
                @endif</div>
        </div>
        @empty<div class="notice">Ingen ordrer på kontoen endnu.</div>@endforelse
    </div>
</main>
</div>
@push('styles')
<style>
.clientShell{display:grid;grid-template-columns:250px minmax(0,1fr);gap:28px;padding:36px 0 75px}.clientSide{height:max-content;position:sticky;top:126px;border:1px solid var(--line);border-radius:17px;background:#07131f;padding:12px}.clientIdentity{display:flex;align-items:center;gap:11px;padding:10px 9px 17px;border-bottom:1px solid var(--line);margin-bottom:9px}.clientIdentity .avatar{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--blue),var(--purple));display:grid;place-items:center;font-weight:900}.clientIdentity strong,.clientIdentity small{display:block}.clientIdentity strong{font-size:13px}.clientIdentity small{font-size:10px;color:#68819d;max-width:160px;overflow:hidden;text-overflow:ellipsis}.clientSide nav{display:grid;gap:3px}.clientSide nav a{padding:10px 11px;border-radius:9px;color:#8198b3;font-size:12px;font-weight:750}.clientSide nav a:hover,.clientSide nav a.active{background:#102640;color:#e0efff}.clientPanel{margin-top:12px;padding:13px;border-radius:11px;background:#0a1b2c;border:1px solid #193652}.clientPanel p{font-size:11px;color:#7890aa}.clientPanel .btn{width:100%;min-height:36px;font-size:11px}.clientHead{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:25px}.clientHead h1{font-size:34px;margin:5px 0}.clientHead p{margin:0}.clientCards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.clientStat{padding:18px;border:1px solid var(--line);border-radius:14px;background:#081522}.clientStat span{font-size:9px;color:#69819c;font-weight:850;letter-spacing:.09em}.clientStat strong{display:block;font-size:24px;margin-top:4px}.clientSection{margin-top:30px}.sectionTitleRow h2{font-size:22px;margin:3px 0 10px}.clientRow{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:17px 19px;border:1px solid var(--line);border-radius:13px;background:#081522;margin-top:8px}.clientRow h3{font-size:14px;margin:0 0 3px}.clientRow p{font-size:11px;margin:0;color:#7087a2}.statusBadge{display:inline-flex;padding:6px 9px;border-radius:999px;background:#102840;color:#8ecbff;font-size:9px;font-weight:900}.statusBadge.good{background:#0d3025;color:#88e7bf}.statusBadge.warn{background:#30250d;color:#ffd077}.statusBadge.bad{background:#34161d;color:#ff9eae}.discountInline{color:#76d9ad;font-weight:800}@media(max-width:800px){.clientShell{grid-template-columns:1fr}.clientSide{position:static}.clientSide nav{display:flex;overflow:auto}.clientSide nav a{white-space:nowrap}.clientPanel{display:none}.clientCards{grid-template-columns:1fr}.clientHead{align-items:flex-start;flex-direction:column}}@media(max-width:580px){.clientRow{align-items:flex-start;flex-direction:column}}
</style>
@endpush
@endsection