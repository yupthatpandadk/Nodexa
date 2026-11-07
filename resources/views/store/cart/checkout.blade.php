@extends('store.layout')
@section('title','Checkout')
@section('description','Gennemgå din Nodexa-ordre og opret alle services samlet.')
@section('content')

<section class="checkoutHero">
    <div class="container checkoutHeroInner">
        <a href="{{ route('store.cart') }}" class="backLink">← Tilbage til kurven</a>
        <div class="checkoutKicker"><span></span> SIKKER CHECKOUT</div>
        <h1>Gennemgå din ordre.</h1>
        <p>Kontrollér dine services og konfigurationer. Alt samles under ét ordrenummer.</p>

        <div class="checkoutSteps" aria-label="Checkout status">
            <div class="checkoutStep done"><i>✓</i><span>Kurv</span></div>
            <div class="checkoutStepLine active"></div>
            <div class="checkoutStep active"><i>2</i><span>Gennemgå</span></div>
            <div class="checkoutStepLine"></div>
            <div class="checkoutStep"><i>3</i><span>Ordre</span></div>
        </div>
    </div>
</section>

<section class="checkoutSection">
<div class="container checkoutContainer">

    @if(session('success'))
        <div class="checkoutAlert success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="checkoutAlert error">{{ $errors->first() }}</div>
    @endif

    <div class="checkoutLayout">

        <main class="checkoutMain">
            <section class="checkoutPanel">
                <div class="panelHeading">
                    <div>
                        <span class="panelEyebrow">SERVICES</span>
                        <h2>Din bestilling</h2>
                    </div>
                    <span class="itemCount">{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'vare' : 'varer' }}</span>
                </div>

                <div class="serviceList">
                    @foreach($summary['items'] as $index => $item)
                        <article class="checkoutService">
                            <div class="serviceNumber">{{ $index + 1 }}</div>

                            <div class="serviceContent">
                                <div class="serviceHeader">
                                    <div>
                                        <span class="serviceType">
                                            {{ $item['type'] === 'flax_vps' ? 'VPS Hosting' : 'Game Server' }}
                                        </span>
                                        <h3>{{ $item['name'] }}</h3>
                                    </div>

                                    <div class="servicePrice">
                                        <strong>{{ number_format((float)$item['price'],2,',','.') }} {{ $item['currency'] }}</strong>
                                        <span>pr. måned</span>
                                    </div>
                                </div>

                                @if($item['type'] === 'flax_vps')
                                    <div class="serviceIdentity">
                                        <span>VPS-navn</span>
                                        <strong>{{ $item['configuration']['hostname'] }}</strong>
                                    </div>

                                    <div class="serviceSpecs">
                                        <span><small>Operativsystem</small><b>{{ $item['configuration']['os_label'] }}</b></span>
                                        <span><small>CPU</small><b>{{ $item['meta']['vcpu'] ?: '—' }} vCPU</b></span>
                                        <span><small>RAM</small><b>{{ $item['meta']['memory_mb'] ? rtrim(rtrim(number_format($item['meta']['memory_mb']/1024,1,',','.'),'0'),',') . ' GB' : '—' }}</b></span>
                                        <span><small>Disk</small><b>{{ $item['meta']['disk_gb'] ?: '—' }} GB</b></span>
                                        @if(!empty($item['meta']['bandwidth']))
                                            <span><small>Netværk</small><b>{{ $item['meta']['bandwidth'] }}</b></span>
                                        @endif
                                    </div>
                                @else
                                    <div class="serviceIdentity">
                                        <span>Servernavn</span>
                                        <strong>{{ $item['configuration']['server_name'] }}</strong>
                                    </div>

                                    <div class="serviceSpecs">
                                        <span><small>Spil</small><b>{{ $item['meta']['game'] }}</b></span>
                                        <span><small>RAM</small><b>{{ $item['meta']['memory'] }} MB</b></span>
                                        <span><small>CPU</small><b>{{ $item['meta']['cpu'] }}%</b></span>
                                        <span><small>Disk</small><b>{{ $item['meta']['disk'] }} MB</b></span>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="editCartRow">
                    <span>Skal noget ændres?</span>
                    <a href="{{ route('store.cart') }}">Redigér kurv →</a>
                </div>
            </section>

            <section class="checkoutPanel accountPanel">
                <div class="panelHeading compact">
                    <div>
                        <span class="panelEyebrow">KONTO</span>
                        <h2>Kundeoplysninger</h2>
                    </div>
                    <span class="accountState">● Logget ind</span>
                </div>

                <div class="accountCard">
                    <div class="accountAvatar">
                        {{ strtoupper(substr(Auth::user()->name_first ?: Auth::user()->email,0,1)) }}
                    </div>
                    <div class="accountInfo">
                        <strong>{{ trim(Auth::user()->name_first . ' ' . Auth::user()->name_last) ?: Auth::user()->email }}</strong>
                        <span>{{ Auth::user()->email }}</span>
                    </div>
                    <a href="{{ route('store.client.profile') }}">Redigér</a>
                </div>
            </section>

            <section class="checkoutInfoGrid">
                <div class="checkoutInfo">
                    <i>↻</i>
                    <div>
                        <strong>Månedlig service</strong>
                        <span>Hver service fortsætter som sit eget abonnement efter første samlede ordre.</span>
                    </div>
                </div>
                <div class="checkoutInfo">
                    <i>⚡</i>
                    <div>
                        <strong>Automatisk aktivering</strong>
                        <span>Når betalingen registreres, behandles alle services i ordren automatisk.</span>
                    </div>
                </div>
            </section>
        </main>

        <aside class="checkoutSummary">
            <div class="summaryCard">
                <span class="panelEyebrow">ORDREOVERSIGT</span>
                <h2>Samlet checkout</h2>

                <div class="summaryRows">
                    @foreach($summary['items'] as $item)
                        <div class="summaryItem">
                            <div>
                                <strong>{{ $item['name'] }}</strong>
                                <span>{{ $item['type'] === 'flax_vps' ? ($item['configuration']['hostname'] ?? 'VPS') : ($item['configuration']['server_name'] ?? 'Server') }}</span>
                            </div>
                            <b>{{ number_format((float)$item['price'],2,',','.') }} kr</b>
                        </div>
                    @endforeach
                </div>

                <div class="summaryDivider"></div>

                <div class="couponBox">
                    @if($summary['coupon'])
                        <div class="couponApplied">
                            <div>
                                <span>Rabatkode</span>
                                <strong>{{ $summary['coupon']['code'] }}</strong>
                                <small>{{ $summary['coupon']['label'] }} rabat</small>
                            </div>
                            <form method="POST" action="{{ route('store.cart.coupon.remove') }}">
                                @csrf @method('DELETE')
                                <button type="submit">Fjern</button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('store.cart.coupon') }}" class="couponForm">
                            @csrf
                            <label for="coupon_code">Rabatkode</label>
                            <div>
                                <input id="coupon_code" name="code" maxlength="40" placeholder="Indtast kode" autocomplete="off">
                                <button type="submit">Anvend</button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="summaryDivider"></div>

                <div class="summaryRow">
                    <span>Subtotal pr. måned</span>
                    <strong>{{ number_format((float)$summary['subtotal'],2,',','.') }} DKK</strong>
                </div>
                <div class="summaryRow">
                    <span>Rabat@if($summary['coupon']) · {{ $summary['coupon']['code'] }}@endif</span>
                    <strong class="{{ $summary['discount'] > 0 ? 'discountValue' : '' }}">-{{ number_format((float)$summary['discount'],2,',','.') }} DKK</strong>
                </div>

                <div class="summaryTotal">
                    <div>
                        <span>Samlet pr. måned</span>
                        <small>{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'service' : 'services' }}</small>
                    </div>
                    <strong>{{ number_format((float)$summary['total'],2,',','.') }} <small>DKK</small></strong>
                </div>

                <div class="paymentNotice">
                    <span class="paymentNoticeIcon">{{ $stripeEnabled ? 'S' : 'i' }}</span>
                    @if($stripeEnabled)
                        <p><strong>Betaling med Stripe.</strong> Efter du bekræfter, sendes du sikkert til Stripe. Stripe får Nodexas gemte slutbeløb på <b>{{ number_format((float)$summary['total'],2,',','.') }} DKK</b> — rabatten er allerede trukket fra.</p>
                    @else
                        <p><strong>Stripe er ikke aktiveret endnu.</strong> Ordren kan oprettes og efterfølgende registreres betalt af admin.</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('store.cart.place') }}" class="placeOrderForm">
                    @csrf
                    <button class="placeOrderButton" type="submit">
                        <span>
                            @if($stripeEnabled)
                                <small>Stripe Checkout · {{ number_format((float)$summary['total'],2,',','.') }} DKK</small>
                                Betal med Stripe
                            @else
                                <small>Bekræft bestilling</small>
                                Opret samlet ordre
                            @endif
                        </span>
                        <b>→</b>
                    </button>
                </form>

                <div class="summarySecurity">
                    <span>✓ Samlet ordrenummer</span>
                    <span>✓ Ingen dobbelt provisionering</span>
                </div>
            </div>

            <a class="summaryBack" href="{{ route('store.cart') }}">← Tilbage til kurven</a>
        </aside>

    </div>
</div>
</section>

@push('styles')
<style>
.checkoutHero{padding:58px 0 38px;border-bottom:1px solid #10243a;background:radial-gradient(620px 260px at 50% 0,rgba(38,104,171,.18),transparent 72%),#06101a}.checkoutHeroInner{max-width:1050px;text-align:center}.backLink{display:block;width:max-content;margin-bottom:25px;color:#7089a5;font-size:11px;font-weight:750}.backLink:hover{color:#c7dcf1}.checkoutKicker{display:flex;align-items:center;justify-content:center;gap:8px;color:#7896b7;font-size:9px;font-weight:900;letter-spacing:.15em}.checkoutKicker span{width:6px;height:6px;border-radius:50%;background:#47d99c;box-shadow:0 0 10px rgba(71,217,156,.6)}.checkoutHero h1{font-size:clamp(36px,5vw,52px);line-height:1;margin:12px 0}.checkoutHero p{margin:0 auto;color:#8297b0;font-size:14px;max-width:580px}.checkoutSteps{display:flex;justify-content:center;align-items:center;margin-top:28px}.checkoutStep{display:flex;align-items:center;gap:7px;color:#58728e;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.checkoutStep i{width:26px;height:26px;border:1px solid #203c59;background:#0a1826;border-radius:50%;display:grid;place-items:center;font-style:normal}.checkoutStep.active{color:#bcd8f5}.checkoutStep.active i{border-color:#3b79ad;background:#16436a;color:#fff}.checkoutStep.done{color:#77b49f}.checkoutStep.done i{border-color:#24684f;background:#103c2e;color:#70e2af}.checkoutStepLine{width:58px;height:1px;background:#18304a;margin:0 9px}.checkoutStepLine.active{background:#2c6d9f}.checkoutSection{padding:36px 0 76px}.checkoutContainer{max-width:1100px}.checkoutLayout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(315px,.55fr);gap:22px;align-items:start}.checkoutMain{display:grid;gap:16px}.checkoutPanel,.summaryCard{border:1px solid #18304a;border-radius:16px;background:#081522;box-shadow:0 14px 40px rgba(0,0,0,.1)}.checkoutPanel{padding:22px}.panelHeading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding-bottom:18px;border-bottom:1px solid #142a40}.panelHeading.compact{padding-bottom:14px}.panelEyebrow{display:block;color:#587592;font-size:8px;font-weight:900;letter-spacing:.15em;text-transform:uppercase}.panelHeading h2,.summaryCard h2{font-size:20px;margin:4px 0 0}.itemCount{padding:5px 8px;border:1px solid #1d3b59;border-radius:7px;background:#0b1b2b;color:#88a5c3;font-size:9px;font-weight:850}.serviceList{display:grid}.checkoutService{display:flex;gap:14px;padding:19px 0;border-bottom:1px solid #142a40}.checkoutService:last-child{border-bottom:0}.serviceNumber{width:29px;height:29px;flex:0 0 29px;border-radius:8px;background:#102840;border:1px solid #1e4669;color:#8fc7f5;display:grid;place-items:center;font-size:10px;font-weight:900}.serviceContent{min-width:0;flex:1}.serviceHeader{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.serviceType{color:#5f7e9d;font-size:8px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.serviceHeader h3{margin:3px 0 0;font-size:16px}.servicePrice{text-align:right;white-space:nowrap}.servicePrice strong{display:block;font-size:14px}.servicePrice span{display:block;color:#627c98;font-size:9px}.serviceIdentity{display:flex;align-items:center;gap:9px;margin-top:11px;padding:8px 10px;border-radius:9px;background:#091827;border:1px solid #162f48;font-size:10px}.serviceIdentity span{color:#617d9b}.serviceIdentity strong{color:#cfe0f1}.serviceSpecs{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}.serviceSpecs>span{min-width:85px;padding:7px 9px;border-radius:8px;background:#07131f;border:1px solid #142c44}.serviceSpecs small{display:block;color:#58708b;font-size:7px;font-weight:850;text-transform:uppercase;letter-spacing:.07em}.serviceSpecs b{display:block;margin-top:2px;color:#aac1d7;font-size:9px;font-weight:750}.editCartRow{display:flex;justify-content:space-between;gap:15px;padding-top:15px;border-top:1px solid #142a40;color:#657e9a;font-size:10px}.editCartRow a{color:#89bff0;font-weight:800}.accountPanel{padding-bottom:20px}.accountState{color:#67d3a4;font-size:9px;font-weight:800}.accountCard{display:flex;align-items:center;gap:12px;padding-top:16px}.accountAvatar{width:38px;height:38px;border-radius:10px;background:linear-gradient(145deg,#315eff,#7657ff);display:grid;place-items:center;font-weight:900}.accountInfo{min-width:0;flex:1}.accountInfo strong,.accountInfo span{display:block}.accountInfo strong{font-size:12px}.accountInfo span{color:#687f9b;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.accountCard>a{color:#83baf0;font-size:9px;font-weight:800}.checkoutInfoGrid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.checkoutInfo{display:flex;gap:11px;padding:15px;border:1px solid #162e46;border-radius:13px;background:#07131f}.checkoutInfo i{width:28px;height:28px;flex:0 0 28px;border-radius:8px;background:#0f263d;border:1px solid #204564;color:#80bdf0;display:grid;place-items:center;font-style:normal;font-size:12px}.checkoutInfo strong,.checkoutInfo span{display:block}.checkoutInfo strong{font-size:10px}.checkoutInfo span{margin-top:3px;color:#657e99;font-size:9px;line-height:1.5}.checkoutSummary{position:sticky;top:96px}.summaryCard{padding:22px}.summaryRows{display:grid;margin-top:18px}.summaryItem{display:flex;justify-content:space-between;gap:12px;padding:10px 0}.summaryItem strong,.summaryItem span{display:block}.summaryItem strong{font-size:10px}.summaryItem span{margin-top:2px;color:#5f7895;font-size:8px}.summaryItem>b{font-size:10px;white-space:nowrap}.summaryDivider{height:1px;background:#17304a;margin:8px 0}.summaryRow{display:flex;justify-content:space-between;gap:14px;padding:7px 0;color:#7890aa;font-size:10px}.summaryRow strong{color:#c6d6e7}.summaryTotal{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-top:13px;padding:15px 0;border-top:1px solid #1a344e}.summaryTotal span,.summaryTotal small{display:block}.summaryTotal span{font-size:11px;font-weight:800}.summaryTotal>div small{margin-top:2px;color:#607b98;font-size:8px}.summaryTotal>strong{font-size:24px;letter-spacing:-.04em}.summaryTotal>strong small{display:inline;font-size:9px;color:#6d849e;letter-spacing:0}.paymentNotice{display:flex;gap:9px;margin-top:5px;padding:11px;border:1px solid #20415d;border-radius:10px;background:#0a1b2b}.paymentNoticeIcon{width:20px;height:20px;flex:0 0 20px;border-radius:50%;background:#173b5e;color:#9dd1ff;display:grid;place-items:center;font-size:10px;font-weight:900}.paymentNotice p{margin:0;color:#718aa5;font-size:9px;line-height:1.55}.paymentNotice strong{color:#b8cce0}.placeOrderForm{margin-top:13px}.placeOrderButton{width:100%;min-height:54px;padding:9px 13px;border:1px solid #4f77ff;border-radius:11px;background:linear-gradient(135deg,#346fff,#7657ff);color:#fff;display:flex;align-items:center;justify-content:space-between;gap:12px;font:800 12px inherit;cursor:pointer;box-shadow:0 12px 28px rgba(70,83,255,.2);transition:.15s ease}.placeOrderButton:hover{transform:translateY(-1px);box-shadow:0 15px 34px rgba(70,83,255,.28)}.placeOrderButton span{text-align:left}.placeOrderButton small{display:block;font-size:8px;font-weight:700;opacity:.72;margin-bottom:2px}.placeOrderButton b{font-size:18px}.summarySecurity{display:flex;gap:10px;flex-wrap:wrap;margin-top:11px;color:#5e7995;font-size:8px}.summaryBack{display:block;margin-top:10px;text-align:center;color:#67819c;font-size:9px;font-weight:750}.checkoutAlert{margin-bottom:15px;padding:12px 14px;border-radius:10px;font-size:11px}.checkoutAlert.error{background:#29131a;border:1px solid #69333d;color:#ffabb8}.checkoutAlert.success{background:#0e2b22;border:1px solid #245d49;color:#84e4b9}.couponBox{padding:10px 0}.couponForm>label{display:block;color:#718aa5;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px}.couponForm>div{display:flex;gap:7px}.couponForm input{min-width:0;flex:1;height:38px;border:1px solid #203b56;border-radius:8px;background:#06111d;color:#fff;padding:0 10px;font:700 10px inherit;text-transform:uppercase;outline:none}.couponForm input:focus{border-color:#4d8dc9;box-shadow:0 0 0 2px rgba(61,141,255,.1)}.couponForm button,.couponApplied button{border:1px solid #28527a;border-radius:8px;background:#102941;color:#a8d4fa;padding:0 11px;font:800 9px inherit;cursor:pointer}.couponApplied{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px;border:1px solid #245c48;border-radius:10px;background:#0c251d}.couponApplied span,.couponApplied strong,.couponApplied small{display:block}.couponApplied span{color:#6a9d88;font-size:8px;text-transform:uppercase;letter-spacing:.08em}.couponApplied strong{margin-top:2px;color:#9ce8c4;font-size:11px}.couponApplied small{margin-top:2px;color:#628e7b;font-size:8px}.couponApplied button{height:32px;border-color:#335849;background:#123126;color:#8fd9b5}.discountValue{color:#67d9a2!important}

@media(max-width:850px){.checkoutLayout{grid-template-columns:1fr}.checkoutSummary{position:static}.checkoutSummary{order:-1}.summaryRows{display:none}.summaryCard{padding:19px}.checkoutInfoGrid{grid-template-columns:1fr 1fr}}
@media(max-width:580px){.checkoutHero{padding:34px 0 27px}.backLink{margin-bottom:18px}.checkoutHero h1{font-size:34px}.checkoutStep span{display:none}.checkoutStepLine{width:38px}.checkoutSection{padding-top:20px}.checkoutPanel{padding:16px}.serviceHeader{gap:8px}.serviceHeader h3{font-size:14px}.servicePrice strong{font-size:12px}.checkoutService{gap:10px}.serviceNumber{width:25px;height:25px;flex-basis:25px}.serviceSpecs>span{min-width:calc(50% - 4px);flex:1}.checkoutInfoGrid{grid-template-columns:1fr}.summaryTotal>strong{font-size:22px}}
</style>
@endpush
@endsection
