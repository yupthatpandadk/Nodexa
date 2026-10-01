@extends('store.layout')
@section('title',$article->title.' · Vidensbase')
@section('description',$article->summary ?: 'Nodexa Vidensbase')
@push('styles')@include('store.knowledgebase.partials.styles')@endpush
@section('content')
<section class="kbShell"><div class="container"><div class="kbBreadcrumbs"><a href="{{ route('knowledgebase.index') }}">Vidensbase</a><span>›</span><a href="{{ route('knowledgebase.category',$article->category) }}">{{ $article->category->name }}</a><span>›</span><span>{{ $article->title }}</span></div><div class="kbArticleLayout"><article class="kbArticleCard"><div class="kbBadge">{{ $article->category->name }}</div><h1>{{ $article->title }}</h1>@if($article->summary)<div class="kbArticleSummary">{{ $article->summary }}</div>@endif<div class="kbContent">{!! $renderedContent !!}</div>
<div style="margin-top:30px;padding:18px;border:1px solid #1c3b58;border-radius:13px;background:#0a1928">
    <strong>Var denne artikel nyttig?</strong>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px">
        <form method="POST" action="{{ route('knowledgebase.feedback',$article) }}">@csrf<input type="hidden" name="helpful" value="1"><button class="btn" type="submit">👍 Ja ({{ $helpfulYes }})</button></form>
        <form method="POST" action="{{ route('knowledgebase.feedback',$article) }}">@csrf<input type="hidden" name="helpful" value="0"><button class="btn" type="submit">👎 Nej ({{ $helpfulNo }})</button></form>
        @if(session('kb_feedback'))<span class="muted" style="font-size:12px">{{ session('kb_feedback') }}</span>@endif
    </div>
</div>
<div class="kbMeta" style="margin-top:20px;padding-top:18px;border-top:1px solid #17314b"><span>Opdateret {{ $article->updated_at->format('d.m.Y') }}</span><span>·</span><span>{{ number_format($article->views,0,',','.') }} visninger</span></div></article><aside class="kbAside"><div class="kbAsideBox"><h3>Relaterede artikler</h3>@forelse($related as $item)<a href="{{ route('knowledgebase.article',$item) }}">{{ $item->title }}</a>@empty<span class="muted" style="font-size:12px">Ingen relaterede artikler endnu.</span>@endforelse</div><div class="kbAsideBox" style="margin-top:12px"><h3>Stadig brug for hjælp?</h3><a href="{{ route('store.support') }}">Gå til Support Center →</a>@auth<a href="{{ route('store.client.tickets.create') }}">Opret supportticket →</a>@endauth</div></aside></div></div></section>
@endsection