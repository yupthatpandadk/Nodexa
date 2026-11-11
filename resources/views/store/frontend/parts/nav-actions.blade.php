@auth
<a class="nx-head-wallet" href="{{ route('store.client.wallet') }}" title="Wallet · {{ number_format($nodexaWalletBalanceCents / 100, 2, ',', '.') }} DKK">Wallet <strong>{{ number_format($nodexaWalletBalanceCents/100,2,',','.') }} kr.</strong></a>
<a class="nx-head-cart" href="/cart" aria-label="Kurv">Kurv @if($nodexaCartCount > 0)<b>{{ $nodexaCartCount }}</b>@endif</a>
<a class="nx-head-account" href="{{ route('store.client') }}">Min konto</a>
<a class="nx-head-panel" href="{{ rtrim(config('app.url'), '/') }}/panel">Panel ↗</a>
<details class="nx-head-profile">
    <summary aria-label="Åbn profil og konto">Konto ▾</summary>
    <div class="nx-head-profile-menu">
        <a href="{{ route('store.client') }}">Dashboard</a>
        <a href="{{ route('store.client.servers') }}">Mine servere</a>
        <a href="{{ route('store.client.vps') }}">Mine VPS'er</a>
        <a href="{{ route('store.orders') }}">Ordrer</a>
        <a href="{{ route('store.client.billing') }}">Fakturaer og betaling</a>
        <a href="{{ route('store.client.wallet') }}">Wallet & kredit</a>
        <a href="{{ route('store.client.tickets') }}">Support tickets</a>
        <a href="{{ route('store.client.profile') }}">Profil</a>
        <form method="POST" action="{{ route('auth.logout') }}">@csrf<button type="submit">Log ud</button></form>
    </div>
</details>
@else
<a class="nx-head-account" href="/auth/login">Log ind</a>
<a class="nx-head-panel" href="/auth/register">Opret konto ↗</a>
@endauth