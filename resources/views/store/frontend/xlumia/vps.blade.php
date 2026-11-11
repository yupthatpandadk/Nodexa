@extends('store.layout')
@section('title','VPS Hosting · Nodexa')
@section('description','VPS Hosting med automatisk provisionering og Nodexa kundeportal.')
@section('content')

<main class="nx-theme-page nx-vps-page nx-vps-xl">
    <section class="nx-xl-vps-head"><div class="container"><span class="nx-xl-kicker">✦ NODEXA VIRTUAL CLOUD</span><h1>Din egen cloud.<br><span>På dine vilkår.</span></h1><p>VPS til projekter, der kræver mere frihed. Vælg pakken, og fortsæt til OS-opsætning.</p><a href="#nxVpsPlans" class="nx-theme-primary">Udforsk VPS-pakker ↗</a></div></section>
    <section class="nx-theme-section nx-xl-vps-content"><div class="container" id="nxVpsPlans"><div class="nx-xl-section-intro"><p class="nx-theme-overline">CLOUD PRODUCTS</p><h2>Vælg dit nye miljø.</h2></div>@if(!$configured)<div class="nx-theme-vps-warning" role="status">VPS-bestilling er midlertidigt utilgængelig, fordi VPS-tjenesten ikke er tilgængelig.</div>@endif@include('store.frontend.parts.vps-cards',['variant'=>'xlumia-vps'])</div></section>
</main>
@endsection
