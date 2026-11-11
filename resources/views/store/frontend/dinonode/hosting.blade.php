@extends('store.layout')
@section('title','Server Hosting · Nodexa')
@section('description','Vælg Minecraft, FiveM eller anden game server hosting i Nodexa.')
@section('content')

<main class="nx-theme-page nx-catalog-page nx-catalog-dino">
    <section class="nx-dino-catalog-hero"><div class="container nx-dino-catalog-head"><div><span class="nx-dino-banner">GAME SERVER SELECTOR</span><h1>Find din serverpakke.</h1><p>Minecraft, FiveM og flere spil på ét og samme kontrolpanel.</p></div><a href="{{route('store.vps')}}" class="nx-theme-secondary">VPS CLOUD ↗</a></div></section>
    <section class="nx-dino-catalog-filters"><div class="container"><div><strong>VÆLG SPIL</strong><p>Filtrér hostingpakker</p></div>@include('store.frontend.parts.catalog-filters')</div></section>
    <section class="nx-theme-section nx-dino-catalog-main"><div class="container"><div class="nx-dino-catalog-caption"><span>AVAILABLE HOSTING PLANS</span><span>NODEXA / CONTROL</span></div><div id="nxThemeCatalog">@include('store.frontend.parts.game-cards',['variant'=>'catalog'])</div><div class="nx-theme-empty" id="nxThemeNoMatches" hidden>Ingen pakker i den valgte kategori. Vælg et andet spil.</div></div></section>
</main>
@endsection
