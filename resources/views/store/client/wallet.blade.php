@extends('store.layout')
@section('title','Wallet · Client Area')
@section('content')
<section class="pagehero" style="padding-bottom:38px">
    <div class="container">
        <div class="eyebrow">NODEXA WALLET</div>
        <h1>Din Wallet.</h1>
        <p class="lead">Din kredit til fremtidige hostingordrer. Saldo og historik er altid tilgængelige her.</p>
    </div>
</section>
<section class="section" style="padding-top:35px">
    <div class="container" style="max-width:1060px">
        <div class="walletBalance">
            <div><div class="miniLabel">TILGÆNGELIG KREDIT</div>
                <div class="walletAmount">{{ number_format($balanceCents / 100,2,',','.') }} <span>DKK</span></div>
                <p>Betal nye game-servere og VPS'er med Wallet i kassen, når saldoen dækker hele ordren.</p>
            </div>
            <a class="btn primary" href="{{ route('store.hosting') }}">Brug kredit →</a>
        </div>
        <div class="walletNotes">
            <div><strong>Sådan får du kredit</strong><p>Nodexa-support kan tilføje tilgodehavender eller efter aftale udbetale en refundering som Wallet-kredit.</p></div>
            <div><strong>Ingen kontantudbetaling her</strong><p>Wallet er intern DKK-kredit til køb hos Nodexa. Kontakt support ved spørgsmål om refundering til din oprindelige betalingsmetode.</p></div>
        </div>
        <div class="walletHead"><h2>Transaktionshistorik</h2><a href="{{ route('store.client.billing') }}">Billing & ordrer</a></div>
        <div class="walletTransactions">
            @forelse($transactions as $transaction)
            <div class="walletTransaction">
                <div>
                    <strong>{{ $transaction->type === 'admin_credit' ? 'Kredit tilføjet' : ($transaction->type === 'refund_credit' ? 'Refundering til Wallet' : 'Wallet-betaling') }}</strong>
                    <span>{{ $transaction->description ?: 'Nodexa Wallet' }}</span>
                    <small>{{ \Carbon\Carbon::parse($transaction->created_at)->format('d.m.Y H:i') }}</small>
                </div>
                <div class="walletRight">
                    <strong class="{{ $transaction->amount_cents >= 0 ? 'plus' : 'minus' }}">{{ $transaction->amount_cents >= 0 ? '+' : '−' }}{{ number_format(abs($transaction->amount_cents) / 100,2,',','.') }} DKK</strong>
                    <small>Saldo efter: {{ number_format($transaction->balance_after_cents / 100,2,',','.') }} DKK</small>
                </div>
            </div>
            @empty
            <div class="walletEmpty">Du har endnu ingen Wallet-transaktioner.</div>
            @endforelse
        </div>
    </div>
</section>
@push('styles')
<style>
.walletBalance{display:flex;align-items:center;justify-content:space-between;gap:25px;background:linear-gradient(125deg,#0b2234,#101d38);border:1px solid #2b4763;border-radius:19px;padding:31px}.walletAmount{font-weight:850;font-size:clamp(34px,6vw,55px);letter-spacing:-.04em;color:#f5faff;margin:6px 0}.walletAmount span{font-size:15px;color:#83a5c8;letter-spacing:0}.walletBalance p{font-size:12px;color:#8fa8c2;margin:5px 0 0;max-width:480px}.walletNotes{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:20px 0 32px}.walletNotes>div{border:1px solid #19324c;border-radius:13px;padding:18px;background:#091724}.walletNotes strong{font-size:13px}.walletNotes p{font-size:12px;color:#8198b3;margin:5px 0 0}.walletHead{display:flex;justify-content:space-between;align-items:center;gap:16px}.walletHead h2{font-size:24px}.walletHead a{font-size:12px;color:#87bcf5}.walletTransactions{border:1px solid #1d344c;border-radius:15px;overflow:hidden}.walletTransaction{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:19px;background:#081522;border-bottom:1px solid #1c3248}.walletTransaction:last-child{border-bottom:0}.walletTransaction strong,.walletTransaction span,.walletTransaction small{display:block}.walletTransaction strong{font-size:13px}.walletTransaction span{font-size:12px;color:#9cb0c6;margin-top:4px}.walletTransaction small{font-size:10px;color:#667f9c;margin-top:4px}.walletRight{text-align:right;white-space:nowrap}.walletRight .plus{color:#74dfb1}.walletRight .minus{color:#d3e3f4}.walletEmpty{padding:32px;text-align:center;color:#8fa5bd}@media(max-width:700px){.walletBalance{flex-direction:column;align-items:flex-start}.walletNotes{grid-template-columns:1fr}.walletTransaction{align-items:flex-start}.walletRight{white-space:normal}}
</style>
@endpush
@endsection
