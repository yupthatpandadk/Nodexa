@extends('store.layout')
@section('title','CFX EUP Keys · Client Area')

@push('styles')
<style>
.eupShell{padding:42px 0 80px}.eupHead{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px}.eupHead h1{font-size:36px;margin:5px 0}.eupHead p{margin:0;color:#8198b3}.eupGrid{display:grid;gap:14px}.eupCard{border:1px solid #17314b;border-radius:17px;background:#081522;padding:20px}.eupTop{display:flex;justify-content:space-between;align-items:flex-start;gap:18px}.eupTop h2{font-size:19px;margin:0 0 5px}.eupMeta{font-size:11px;color:#7089a5}.eupBadge{display:inline-flex;padding:6px 9px;border-radius:999px;background:#102840;color:#8ecbff;font-size:9px;font-weight:900;text-transform:uppercase}.eupBadge.good{background:#0d3025;color:#87e8bf}.eupBadge.warn{background:#30250d;color:#ffd27d}.eupBadge.bad{background:#35171e;color:#ff9ead}.keyBox{margin-top:17px;padding:16px;border-radius:12px;border:1px solid #2b5277;background:#07131f}.keyLabel{display:block;color:#6f89a5;font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;margin-bottom:7px}.keyValue{display:flex;align-items:center;gap:10px}.keyValue code{flex:1;color:#c8e9ff;background:transparent;border:0;padding:0;font:600 13px/1.6 Menlo,Monaco,Consolas,monospace;overflow-wrap:anywhere}.copyBtn{white-space:nowrap}.eupNotice{margin-top:14px;padding:12px;border-radius:10px;border:1px solid #294663;background:#0a1928;color:#8fa9c2;font-size:12px}.eupBilling{display:flex;gap:16px;flex-wrap:wrap;margin-top:14px;color:#718aa4;font-size:11px}.emptyEup{text-align:center;padding:50px 20px;border:1px dashed #2b4866;border-radius:16px;color:#8098b3;background:#07131f}@media(max-width:650px){.eupHead,.eupTop{align-items:flex-start;flex-direction:column}.keyValue{align-items:flex-start;flex-direction:column}.copyBtn{width:100%}}
</style>
@endpush

@section('content')
<section class="eupShell"><div class="container">
    <div class="eupHead">
        <div><div class="eyebrow">CLIENT AREA / CFX EUP</div><h1>Mine CFX EUP Keys</h1><p>Se dine tildelte EUP keys og status på månedlige abonnementer.</p></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" href="{{ route('store.client.billing') }}">Billing</a><a class="btn" href="{{ route('store.client') }}">← Dashboard</a></div>
    </div>

    <div class="eupGrid">
        @forelse($orders as $order)
        <article class="eupCard">
            <div class="eupTop">
                <div><h2>{{ $order->description }}</h2><div class="eupMeta">Service #{{ $order->id }} @if($order->key_label)· {{ $order->key_label }}@endif</div></div>
                <span class="eupBadge {{ $order->status==='active'?'good':($order->status==='suspended'?'bad':'warn') }}">{{ str_replace('_',' ',strtoupper($order->status)) }}</span>
            </div>

            @if($order->plain_key)
            <div class="keyBox">
                <span class="keyLabel">Din CFX EUP Key</span>
                <div class="keyValue"><code id="eup-key-{{ $order->id }}">{{ $order->plain_key }}</code><button class="btn copyBtn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('eup-key-{{ $order->id }}').textContent);this.textContent='Kopieret ✓';">Kopiér key</button></div>
            </div>
            <div class="eupNotice">Opbevar keyen sikkert. Del den kun med personer, som skal administrere den relevante CFX/FiveM-service.</div>
            @elseif($order->status==='awaiting_payment')
            <div class="eupNotice">Keyen er reserveret til dig, men bliver først vist når den første faktura er registreret som betalt.</div>
            @elseif($order->status==='suspended')
            <div class="eupNotice">Denne service er suspenderet på grund af manglende betaling. Keyen bliver synlig igen, når alle forfaldne fakturaer er betalt.</div>
            @elseif($order->status==='cancelled')
            <div class="eupNotice">Denne EUP service er annulleret, og keyen er ikke længere tildelt din konto.</div>
            @else
            <div class="eupNotice">Keyen er ikke tilgængelig i den nuværende servicestatus.</div>
            @endif

            <div class="eupBilling">
                @if($order->subscription_id)<span><strong>{{ number_format($order->monthly_price,2,',','.') }} {{ $order->currency }}</strong> / måned</span>@else<span>Direkte tildeling · ingen månedlig betaling</span>@endif
                @if($order->next_invoice_at)<span>Næste faktura: {{ \Carbon\Carbon::parse($order->next_invoice_at)->format('d.m.Y') }}</span>@endif
                @if($order->first_invoice_number)<span>Første faktura: {{ $order->first_invoice_number }} · {{ $order->first_invoice_status }}</span>@endif
            </div>
        </article>
        @empty
        <div class="emptyEup"><div style="font-size:30px;margin-bottom:10px">🔑</div><h2>Ingen CFX EUP Keys endnu</h2><p>Når en admin tildeler eller opretter et EUP-abonnement til dig, vises det her.</p></div>
        @endforelse
    </div>
</div></section>
@endsection
