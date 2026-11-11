@extends('store.layout')
@section('title','VPS Hosting · Nodexa')
@section('description','VPS Hosting med automatisk provisionering og Nodexa kundeportal.')
@section('content')

<main class="nx-theme-page nx-vps-page nx-vps-dino">
    <section class="nx-dino-vps-head"><div class="container nx-dino-vps-grid"><div><span class="nx-dino-banner">CLOUD INFRASTRUCTURE</span><h1>Klar til din egen VPS?</h1><p>Vælg ressourcer, operativsystem og hostname. Hele serveren administreres i Nodexa.</p><a href="#nxVpsPlans" class="nx-theme-primary">Vælg VPS-pakke ↗</a></div><div class="nx-dino-vps-card"><div>NODEXA // VPS CLOUD</div><strong>Din maskine.<br>Din kontrol.</strong><small>VPS • OS • Billing</small></div></div></section>
    <section class="nx-theme-section nx-dino-vps-content"><div class="container" id="nxVpsPlans"><div class="nx-dino-title"><div><span class="nx-theme-overline">VIRTUAL SERVERS</span><h2>Find din VPS.</h2></div><a href="{{route('store.hosting')}}" class="nx-theme-secondary">Game hosting ↗</a></div>@if(!$configured)<div class="nx-theme-vps-warning" role="status">VPS-bestilling er midlertidigt utilgængelig, fordi VPS-tjenesten ikke er tilgængelig.</div>@endif@include('store.frontend.parts.vps-cards',['variant'=>'dino-vps'])</div></section>
</main>
@endsection
