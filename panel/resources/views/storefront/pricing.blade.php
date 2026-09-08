@extends('storefront.layout')
@section('title', 'Planer')
@section('description', 'Se aktive Nodexa game hosting-pakker og priser.')
@section('content')
<section class="nx-page-hero"><div class="nx-shell"><span class="nx-eyebrow">GAME HOSTING & PLANER</span><h1>Vælg dit spil.<br><em>Find din pakke.</em></h1><p>Priser, spil og ressourcer hentes direkte fra de aktive pakker, der er oprettet i Nodexa Admin.</p></div></section>
<section class="nx-section nx-section-tight"><div class="nx-shell">
@if($categories->isEmpty() && $products->isEmpty())
<div class="nx-card"><h3>Ingen aktive pakker endnu</h3><p>Opret en kategori og en pakke under Admin → Billing. De vises automatisk her.</p></div>
@else
@foreach($categories as $category)
@php($categoryProducts=$products->where('category_id',$category->id))
@if($categoryProducts->isNotEmpty())
<div class="nx-section-head" style="margin-top:28px"><div><span class="nx-kicker">GAME HOSTING</span><h2>{{$category->name}}</h2></div>@if($category->description)<p>{{$category->description}}</p>@endif</div>
<div class="nx-plan-grid">
@foreach($categoryProducts as $plan)
<article class="nx-plan"><small>{{$category->name}}</small><h3>{{$plan->name}}</h3><p>{{$plan->description ?: 'Game server hosting på Nodexa.'}}</p><div class="nx-plan-list">
@if($plan->memory_mb)<span>{{number_format($plan->memory_mb/1024,$plan->memory_mb%1024?1:0,',','.')}} GB RAM</span>@endif
@if($plan->cpu_percent)<span>{{$plan->cpu_percent}}% CPU</span>@endif
@if($plan->disk_mb)<span>{{number_format($plan->disk_mb/1024,$plan->disk_mb%1024?1:0,',','.')}} GB NVMe</span>@endif
@if($plan->databases_limit)<span>{{$plan->databases_limit}} databaser</span>@endif
@if($plan->backups_limit)<span>{{$plan->backups_limit}} backups</span>@endif
@if($plan->allocations_limit)<span>{{$plan->allocations_limit}} port{{(int)$plan->allocations_limit===1?'':'s'}}</span>@endif
<span>Nodexa kontrolpanel</span></div><div class="nx-plan-price"><strong style="font-size:22px;color:var(--nx-text,#fff)">{{number_format((float)$plan->price,2,',','.')}} {{$plan->currency}}</strong><br><small>{{['monthly'=>'pr. måned','quarterly'=>'hver 3. måned','semiannually'=>'halvårligt','annually'=>'årligt','one_time'=>'engangsbetaling'][$plan->billing_cycle]??$plan->billing_cycle}}</small></div><a class="nx-btn nx-btn-primary" style="width:100%;margin-top:12px" href="{{url('/client')}}">Bestil {{$plan->name}} →</a></article>
@endforeach
</div>
@endif
@endforeach
@php($uncategorized=$products->whereNull('category_id'))
@if($uncategorized->isNotEmpty())
<div class="nx-section-head" style="margin-top:28px"><div><span class="nx-kicker">GAME HOSTING</span><h2>Andre pakker</h2></div></div><div class="nx-plan-grid">@foreach($uncategorized as $plan)<article class="nx-plan"><small>GAME HOSTING</small><h3>{{$plan->name}}</h3><p>{{$plan->description}}</p><div class="nx-plan-list">@if($plan->memory_mb)<span>{{number_format($plan->memory_mb/1024,1,',','.')}} GB RAM</span>@endif @if($plan->cpu_percent)<span>{{$plan->cpu_percent}}% CPU</span>@endif @if($plan->disk_mb)<span>{{number_format($plan->disk_mb/1024,1,',','.')}} GB NVMe</span>@endif<span>Nodexa kontrolpanel</span></div><div class="nx-plan-price"><strong>{{number_format((float)$plan->price,2,',','.')}} {{$plan->currency}}</strong></div><a class="nx-btn nx-btn-primary" style="width:100%;margin-top:12px" href="{{url('/client')}}">Bestil →</a></article>@endforeach</div>
@endif
@endif
</div></section>
<section class="nx-section"><div class="nx-shell"><div class="nx-section-head"><div><span class="nx-kicker">INKLUDERET I PLATFORMEN</span><h2>Mere end bare RAM.</h2></div><p>Administrér serveren direkte gennem Nodexa.</p></div><div class="nx-card-grid"><article class="nx-card"><div class="nx-card-icon">›_</div><h3>Console & power</h3><p>Start, restart, stop og live serveroutput.</p></article><article class="nx-card"><div class="nx-card-icon">↻</div><h3>Backups</h3><p>Backup og restore direkte fra serveren.</p></article><article class="nx-card"><div class="nx-card-icon">▤</div><h3>Files & SFTP</h3><p>Filhåndtering og SFTP-adgang.</p></article></div></div></section>
@endsection