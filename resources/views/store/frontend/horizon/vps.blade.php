@extends('store.layout')
@section('title','VPS Hosting · Nodexa')
@section('description','VPS Hosting med automatisk provisionering og Nodexa kundeportal.')
@section('content')

<main class="nx-theme-page nx-vps-page nx-vps-horizon">
    <section class="nx-horizon-vps-head"><div class="container nx-horizon-vps-grid"><div><span class="nx-theme-overline">INFRASTRUCTURE / 02</span><h1>VPS Cloud</h1><p>Virtuelle servere med et ligetil bestillingsforløb, operativsystemvalg og administration i Nodexa.</p><a href="#nxVpsPlans" class="nx-theme-primary">Gennemse VPS-ressourcer →</a></div><div class="nx-horizon-vps-aside"><small>VPS SERVICES</small><strong>Provisioning</strong><span>Konfiguration → Betaling → Kontrolpanel</span></div></div></section>
    <section class="nx-theme-section nx-horizon-vps-content"><div class="container" id="nxVpsPlans"><div class="nx-horizon-heading"><div><p class="nx-theme-overline">RESSOURCE OVERVIEW</p><h2>Tilgængelige VPS-pakker</h2></div><p>Sammenlign CPU, RAM og lagerplads.</p></div>@if(!$configured)<div class="nx-theme-vps-warning" role="status">VPS-bestilling er midlertidigt utilgængelig, fordi VPS-tjenesten ikke er tilgængelig.</div>@endif@include('store.frontend.parts.vps-cards',['variant'=>'horizon-vps'])</div></section>
</main>
@endsection
