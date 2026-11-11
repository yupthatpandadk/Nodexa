@extends('store.layout')
@section('title','VPS Hosting · Nodexa')
@section('description','VPS Hosting med automatisk provisionering og Nodexa kundeportal.')
@section('content')

<main class="nx-theme-page nx-vps-page nx-vps-latch">
    <section class="nx-latch-vps-head"><div class="container nx-latch-vps-headgrid"><div><span class="nx-theme-overline">NODEXA / VIRTUAL INFRASTRUCTURE</span><h1>VPS Hosting.<br><em>Kontrol fra start.</em></h1><p>Vælg en VPS, konfigurér operativsystem og hostname, og administrér den fra Nodexas kundeportal.</p><a href="#nxVpsPlans" class="nx-theme-primary">Se VPS-pakker ↗</a></div><div class="nx-latch-vps-aside"><b>VIRTUAL MACHINES</b><div><span>01</span><strong>Konfigurér</strong></div><div><span>02</span><strong>Betal</strong></div><div><span>03</span><strong>Administrér</strong></div></div></div></section>
    <section class="nx-theme-section"><div class="container" id="nxVpsPlans"><div class="nx-latch-sectiontop"><p class="nx-theme-overline">CATALOG / VPS</p><h2>Vælg ressourcerne.</h2></div>@if(!$configured)<div class="nx-theme-vps-warning" role="status">VPS-bestilling er midlertidigt utilgængelig, fordi VPS-tjenesten ikke er tilgængelig.</div>@endif@include('store.frontend.parts.vps-cards',['variant'=>'latch-vps'])</div></section>
</main>
@endsection
