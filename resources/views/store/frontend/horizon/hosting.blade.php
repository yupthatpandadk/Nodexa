@extends('store.layout')
@section('title','Server Hosting · Nodexa')
@section('description','Vælg Minecraft, FiveM eller anden game server hosting i Nodexa.')
@section('content')

<main class="nx-theme-page nx-catalog-page nx-catalog-horizon">
    <section class="nx-horizon-catalog-header"><div class="container nx-horizon-catalog-heading"><div><span class="nx-theme-overline">HOSTING SERVICES / 01</span><h1>Game Server Hosting</h1><p>Sammenlign aktive serverpakker, og vælg den rigtige ressourceprofil.</p></div><div class="nx-horizon-catalog-index"><b>NODEXA CATALOG</b><span>Kapacitet. Overblik. Kontrol.</span></div></div></section>
    <section class="nx-theme-section nx-horizon-catalog-main"><div class="container"><div class="nx-horizon-toolbar"><span>FILTRÉR EFTER SPIL</span>@include('store.frontend.parts.catalog-filters')<a href="{{route('store.vps')}}">Se VPS →</a></div><div class="nx-horizon-catalog-table"><div class="nx-horizon-table-head"><span>PLAN / SPIL</span><span>RESSOURCER OG PRIS</span><span>HANDLING</span></div><div id="nxThemeCatalog">@include('store.frontend.parts.game-cards',['variant'=>'catalog'])</div><div class="nx-theme-empty" id="nxThemeNoMatches" hidden>Ingen pakker i den valgte kategori. Vælg et andet spil.</div></div></div></section>
</main>
@endsection
