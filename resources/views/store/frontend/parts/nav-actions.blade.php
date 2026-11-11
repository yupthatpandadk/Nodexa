@auth
<a class="nx-head-wallet" href="{{ route('store.client.wallet') }}" title="Wallet · {{ number_format($nodexaWalletBalanceCents / 100, 2, ',', '.') }} DKK">Wallet <strong>{{ number_format($nodexaWalletBalanceCents/100,2,',','.') }} kr.</strong></a>
<a class="nx-head-cart" href="/cart" aria-label="Kurv">Kurv @if($nodexaCartCount > 0)<b>{{ $nodexaCartCount }}</b>@endif</a>
<a class="nx-head-account" href="{{ route('store.client') }}">Min konto</a>
<a class="nx-head-panel" href="{{ rtrim(config('app.url'), '/') }}/panel">Panel ↗</a>
@else
<a class="nx-head-account" href="/auth/login">Log ind</a>
<a class="nx-head-panel" href="/auth/register">Opret konto ↗</a>
@endauth