@extends('store.layout')
@section('title','Server Hosting · Nodexa')
@section('description','Vælg Minecraft, FiveM eller anden game server hosting i Nodexa.')
@section('content')

<main class="nx-theme-page nx-catalog-page nx-catalog-xl">
    <section class="nx-xl-catalog-hero"><div class="container"><span class="nx-xl-kicker">✦ DISCOVER YOUR NEXT SERVER</span><h1>Skabt til dit næste eventyr.</h1><p>Et hostingkatalog med plads til både små fællesskaber og større spilverdener.</p><div class="nx-xl-catalog-pills"><span>Game Servers</span><span>Minecraft</span><span>FiveM</span><span>Control Panel</span></div></div></section>
    <section class="nx-theme-section nx-xl-catalog-body"><div class="container"><div class="nx-xl-catalog-toolbar"><div><span class="nx-theme-overline">GAME HOSTING</span><h2>Vælg dit setup</h2></div>@include('store.frontend.parts.catalog-filters')</div><div id="nxThemeCatalog">@include('store.frontend.parts.game-cards',['variant'=>'catalog'])</div><div class="nx-theme-empty" id="nxThemeNoMatches" hidden>Ingen pakker i den valgte kategori. Vælg et andet spil.</div><div class="nx-xl-catalog-foot"><b>Klar til mere kontrol?</b><span>VPS-hosting med selvvalgt operativsystem.</span><a href="{{route('store.vps')}}">Opdag VPS ↗</a></div></div></section>
</main>
@endsection
