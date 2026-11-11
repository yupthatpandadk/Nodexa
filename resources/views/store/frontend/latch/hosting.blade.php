@extends('store.layout')
@section('title','Server Hosting · Nodexa')
@section('description','Vælg Minecraft, FiveM eller anden game server hosting i Nodexa.')
@section('content')

<main class="nx-theme-page nx-catalog-page nx-catalog-latch">
    <section class="nx-latch-catalog-head"><div class="container"><div class="nx-theme-overline">NODEXA / CATALOG 001</div><div class="nx-latch-catalog-heading"><div><h1>Serverkatalog.</h1><p>Vælg den kapacitet, dit projekt kræver. Fuld kontrol fra Nodexa.</p></div><a class="nx-theme-secondary" href="{{route('store.vps')}}">VPS hosting ↗</a></div></div></section>
    <section class="nx-theme-section nx-latch-catalog-content"><div class="container nx-latch-catalog-layout">
        <aside class="nx-latch-catalog-side"><span class="nx-theme-overline">KATEGORIER</span>@include('store.frontend.parts.catalog-filters')<div class="nx-latch-catalog-support"><b>Brug for hjælp?</b><p>Vælg pakke efter RAM, CPU og lager. Spørg vores support, hvis du er i tvivl.</p><a href="{{route('store.support')}}">Kontakt support ↗</a></div></aside>
        <div class="nx-latch-catalog-list"><div class="nx-latch-catalog-listheading"><span>AKTIVE SERVERPAKKER</span><span>PRISER / MÅNED</span></div><div id="nxThemeCatalog">@include('store.frontend.parts.game-cards',['variant'=>'catalog'])</div><div class="nx-theme-empty" id="nxThemeNoMatches" hidden>Ingen pakker i den valgte kategori. Vælg et andet spil.</div></div>
    </div></section>
</main>
@endsection
