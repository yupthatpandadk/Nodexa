@extends('layouts.admin')

@section('title',$article->exists ? 'Rediger artikel' : 'Ny artikel')

@section('content-header')
    <h1>{{ $article->exists ? 'Rediger artikel' : 'Ny artikel' }} <small>Vidensbase</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.knowledgebase') }}">Vidensbase</a></li>
        <li class="active">{{ $article->exists ? 'Rediger' : 'Ny artikel' }}</li>
    </ol>
@endsection

@section('content')
<form method="POST" action="{{ $article->exists ? route('admin.knowledgebase.articles.update',$article) : route('admin.knowledgebase.articles.store') }}">
    @csrf
    @if($article->exists) @method('PATCH') @endif
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Artikelindhold</h3></div>
                <div class="box-body">
                    <div class="form-group"><label>Titel</label><input class="form-control input-lg" name="title" required maxlength="180" value="{{ old('title',$article->title) }}" placeholder="Fx Sådan opretter du en Minecraft-server"></div>
                    <div class="form-group"><label>Kort beskrivelse</label><textarea class="form-control" name="summary" rows="2" maxlength="500" placeholder="Vises i søgeresultater og artikeloversigter.">{{ old('summary',$article->summary) }}</textarea></div>
                    <div class="form-group">
                        <label>Indhold</label>
                        <textarea class="form-control" name="content" rows="22" required maxlength="100000" style="font-family:Menlo,Monaco,Consolas,monospace;line-height:1.6" placeholder="Skriv artiklen her...">{{ old('content',$article->content) }}</textarea>
                        <p class="help-block">Linjeskift bevares automatisk på den offentlige artikel.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">Indstillinger</h3></div>
                <div class="box-body">
                    <div class="form-group"><label>Kategori</label><select class="form-control" name="category_id" required><option value="">Vælg kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" {{ (string) old('category_id',$article->category_id) === (string) $category->id ? 'selected' : '' }}>{{ $category->icon }} {{ $category->name }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Sortering</label><input class="form-control" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order',$article->sort_order ?? 0) }}"></div>
                    <div class="checkbox"><label><input type="hidden" name="published" value="0"><input type="checkbox" name="published" value="1" {{ old('published',$article->exists ? $article->published : true) ? 'checked' : '' }}> Publiceret</label></div>
                    <div class="checkbox"><label><input type="hidden" name="featured" value="0"><input type="checkbox" name="featured" value="1" {{ old('featured',$article->featured) ? 'checked' : '' }}> Udvalgt artikel</label></div>
                    @if($article->exists)
                    <hr>
                    <div class="text-muted small">Slug: <code>{{ $article->slug }}</code></div>
                    <div class="text-muted small" style="margin-top:7px">Visninger: {{ number_format($article->views,0,',','.') }}</div>
                    @if($article->published)<a class="btn btn-default btn-block" style="margin-top:14px" href="{{ route('knowledgebase.article',$article) }}" target="_blank"><i class="fa fa-external-link"></i> Se offentlig artikel</a>@endif
                    @endif
                </div>
                <div class="box-footer"><button class="btn btn-primary btn-block"><i class="fa fa-save"></i> {{ $article->exists ? 'Gem artikel' : 'Opret artikel' }}</button></div>
            </div>

            @if($article->exists)
            <div class="box box-danger">
                <div class="box-header with-border"><h3 class="box-title">Slet artikel</h3></div>
                <div class="box-body"><p class="text-muted">Artiklen fjernes permanent fra vidensbasen.</p></div>
                <div class="box-footer"><button type="submit" form="delete-kb-article" class="btn btn-danger btn-block" onclick="return confirm('Slet denne artikel permanent?')">Slet artikel</button></div>
            </div>
            @endif
        </div>
    </div>
</form>
@if($article->exists)<form id="delete-kb-article" method="POST" action="{{ route('admin.knowledgebase.articles.delete',$article) }}">@csrf @method('DELETE')</form>@endif
@endsection