@extends('store.layout')
@section('title','Kurv')
@section('content')
<section class="pagehero" style="padding-bottom:44px">
    <div class="container">
        <div class="eyebrow">NODEXA CART</div>
        <h1>Din kurv.</h1>
        <p class="lead">Saml game servers og VPS’er i én checkout og betal dem samlet.</p>
    </div>
</section>

<section class="section" style="padding-top:34px">
<div class="container" style="max-width:1050px">
    @if(session('success'))<div class="notice" style="margin-bottom:16px">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice cartError">{{ $errors->first() }}</div>@endif

    <div class="cartLayout">
        <div class="cartItems">
            @forelse($summary['items'] as $item)
                <article class="cartItem">
                    <div class="cartIcon">{{ $item['type']==='flax_vps' ? '☁' : '▣' }}</div>
                    <div class="cartMain">
                        <div class="cartTop">
                            <div>
                                <div class="miniLabel">{{ $item['type']==='flax_vps' ? 'VPS HOSTING' : 'GAME SERVER' }}</div>
                                <h2>{{ $item['name'] }}</h2>
                            </div>
                            <strong class="cartPrice">{{ number_format((float)$item['price'],2,',','.') }} {{ $item['currency'] }}<small>/md</small></strong>
                        </div>

                        @if($item['type']==='game_server')
                            <div class="cartMeta">
                                <span>Servernavn: <b>{{ $item['configuration']['server_name'] }}</b></span>
                                <span>{{ $item['meta']['game'] }}</span>
                                <span>{{ $item['meta']['memory'] }} MB RAM</span>
                                <span>{{ $item['meta']['cpu'] }}% CPU</span>
                                <span>{{ $item['meta']['disk'] }} MB disk</span>
                            </div>
                        @else
                            <div class="cartMeta">
                                <span>VPS-navn: <b>{{ $item['configuration']['hostname'] }}</b></span>
                                <span>{{ $item['configuration']['os_label'] }}</span>
                                <span>{{ $item['meta']['vcpu'] ?: '—' }} vCPU</span>
                                <span>{{ $item['meta']['memory_mb'] ? number_format($item['meta']['memory_mb']/1024,1,',','.') . ' GB RAM' : '—' }}</span>
                                <span>{{ $item['meta']['disk_gb'] ?: '—' }} GB disk</span>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('store.cart.remove',$item['key']) }}" style="margin-top:13px">
                            @csrf @method('DELETE')
                            <button class="cartRemove" type="submit">Fjern fra kurv</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="card emptyState">
                    <div class="icon">🛒</div>
                    <h2>Kurven er tom</h2>
                    <p class="muted">Tilføj en game server eller VPS for at starte en samlet ordre.</p>
                    <div style="display:flex;gap:9px;justify-content:center;flex-wrap:wrap">
                        <a class="btn primary" href="{{ route('store.hosting') }}">Se servere</a>
                        <a class="btn" href="{{ route('store.vps') }}">Se VPS’er</a>
                    </div>
                </div>
            @endforelse
        </div>

        <aside class="card cartSummary">
            <div class="miniLabel">ORDER SUMMARY</div>
            <h2>{{ $summary['count'] }} {{ $summary['count']===1 ? 'vare' : 'varer' }}</h2>
            <div class="summaryLine"><span>Månedligt subtotal</span><strong>{{ number_format((float)$summary['total'],2,',','.') }} DKK</strong></div>
            <div class="summaryLine"><span>Rabat</span><strong>0,00 DKK</strong></div>
            <div class="summaryTotal"><span>Samlet pr. måned</span><strong>{{ number_format((float)$summary['total'],2,',','.') }} DKK</strong></div>
            <p class="micro">Hver service fortsætter som sit eget månedlige abonnement efter første samlede checkout.</p>

            @if($summary['count'] > 0)
                <a class="btn primary" style="width:100%;margin-top:12px" href="{{ route('store.cart.checkout') }}">Fortsæt til checkout →</a>
                <form method="POST" action="{{ route('store.cart.clear') }}">@csrf @method('DELETE')<button class="btn" style="width:100%;margin-top:8px">Tøm kurv</button></form>
            @endif
        </aside>
    </div>
</div>
</section>

@push('styles')
<style>
.cartLayout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(290px,.55fr);gap:18px;align-items:start}.cartItems{display:grid;gap:12px}.cartItem{display:flex;gap:14px;padding:18px;border:1px solid var(--line);border-radius:15px;background:#081522}.cartIcon{width:44px;height:44px;flex:0 0 auto;border-radius:11px;background:#102a43;border:1px solid #214968;display:grid;place-items:center;color:#8fcaff;font-weight:900}.cartMain{min-width:0;flex:1}.cartTop{display:flex;justify-content:space-between;gap:16px}.cartTop h2{margin:3px 0 0;font-size:18px}.cartPrice{white-space:nowrap;font-size:16px}.cartPrice small{display:block;text-align:right;font-size:9px;color:#7189a4;font-weight:650}.cartMeta{display:flex;gap:7px;flex-wrap:wrap;margin-top:12px}.cartMeta span{padding:6px 8px;border-radius:8px;border:1px solid #1a3550;background:#091a2a;color:#829ab5;font-size:10px}.cartMeta b{color:#d8e9fb}.cartRemove{border:0;background:transparent;color:#e2818e;padding:0;font:750 10px inherit;cursor:pointer}.cartSummary{position:sticky;top:96px}.cartSummary h2{font-size:22px}.summaryLine,.summaryTotal{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid var(--line);font-size:12px}.summaryLine span{color:#8096b0}.summaryTotal{border:0;padding-top:17px}.summaryTotal strong{font-size:17px}.cartError{margin-bottom:16px;border-color:#69333d;color:#ffabb8}@media(max-width:800px){.cartLayout{grid-template-columns:1fr}.cartSummary{position:static}}@media(max-width:560px){.cartItem{align-items:flex-start}.cartTop{flex-direction:column}.cartPrice small{text-align:left}}
</style>
@endpush
@endsection