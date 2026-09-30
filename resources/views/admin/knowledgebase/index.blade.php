@extends('layouts.admin')

@section('title','Vidensbase')

@section('content-header')
    <h1>Vidensbase <small>Administrér kategorier og artikler som i et WHMCS-lignende knowledgebase-system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Vidensbase</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Ny kategori</h3></div>
            <form method="POST" action="{{ route('admin.knowledgebase.categories.store') }}">
                @csrf
                <div class="box-body">
                    <div class="form-group"><label>Navn</label><input class="form-control" name="name" required maxlength="120" placeholder="Fx Kom godt i gang"></div>
                    <div class="form-group"><label>Beskrivelse</label><textarea class="form-control" name="description" rows="3" maxlength="500"></textarea></div>
                    <div class="row">
                        <div class="col-sm-6"><div class="form-group"><label>Ikon</label><input class="form-control" name="icon" maxlength="40" value="📚" placeholder="📚"></div></div>
                        <div class="col-sm-6"><div class="form-group"><label>Sortering</label><input class="form-control" type="number" name="sort_order" min="0" max="9999" value="0"></div></div>
                    </div>
                    <label class="checkbox-inline" style="margin-left:0"><input type="hidden" name="published" value="0"><input type="checkbox" name="published" value="1" checked> Publiceret</label>
                </div>
                <div class="box-footer"><button class="btn btn-primary"><i class="fa fa-plus"></i> Opret kategori</button></div>
            </form>
        </div>

        @foreach($categories as $category)
        <div class="box">
            <form method="POST" action="{{ route('admin.knowledgebase.categories.update',$category) }}">
                @csrf @method('PATCH')
                <div class="box-header with-border"><h3 class="box-title">{{ $category->icon }} {{ $category->name }}</h3><span class="pull-right label label-default">{{ $category->articles_count }} artikler</span></div>
                <div class="box-body">
                    <div class="form-group"><label>Navn</label><input class="form-control" name="name" required value="{{ $category->name }}"></div>
                    <div class="form-group"><label>Beskrivelse</label><textarea class="form-control" name="description" rows="2">{{ $category->description }}</textarea></div>
                    <div class="row">
                        <div class="col-sm-6"><div class="form-group"><label>Ikon</label><input class="form-control" name="icon" value="{{ $category->icon }}"></div></div>
                        <div class="col-sm-6"><div class="form-group"><label>Sortering</label><input class="form-control" type="number" name="sort_order" value="{{ $category->sort_order }}"></div></div>
                    </div>
                    <label class="checkbox-inline" style="margin-left:0"><input type="hidden" name="published" value="0"><input type="checkbox" name="published" value="1" {{ $category->published ? 'checked' : '' }}> Publiceret</label>
                </div>
                <div class="box-footer">
                    <button class="btn btn-primary btn-sm">Gem</button>
                    <button type="submit" form="delete-kb-category-{{ $category->id }}" class="btn btn-danger btn-sm pull-right" onclick="return confirm('Slet kategorien og alle artikler i den?')">Slet</button>
                </div>
            </form>
            <form id="delete-kb-category-{{ $category->id }}" method="POST" action="{{ route('admin.knowledgebase.categories.delete',$category) }}">@csrf @method('DELETE')</form>
        </div>
        @endforeach
    </div>

    <div class="col-md-8">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Artikler</h3>
                <div class="box-tools"><a class="btn btn-primary btn-sm" href="{{ route('admin.knowledgebase.articles.create') }}"><i class="fa fa-plus"></i> Ny artikel</a></div>
            </div>
            <div class="box-body no-padding">
                @if($articles->isEmpty())
                    <div style="padding:35px;text-align:center;color:#7f8c8d">Ingen artikler endnu. Opret din første artikel.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover" style="margin-bottom:0">
                        <thead><tr><th>Titel</th><th>Kategori</th><th>Status</th><th>Visninger</th><th>Opdateret</th><th></th></tr></thead>
                        <tbody>
                        @foreach($articles as $article)
                            <tr>
                                <td><strong>{{ $article->title }}</strong>@if($article->featured) <span class="label label-info">Udvalgt</span>@endif</td>
                                <td>{{ $article->category?->name }}</td>
                                <td>@if($article->published)<span class="label label-success">Publiceret</span>@else<span class="label label-default">Kladde</span>@endif</td>
                                <td>{{ number_format($article->views,0,',','.') }}</td>
                                <td>{{ $article->updated_at->format('d.m.Y H:i') }}</td>
                                <td class="text-right"><a class="btn btn-default btn-xs" href="{{ route('admin.knowledgebase.articles.edit',$article) }}">Rediger</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        <div class="callout callout-info">
            <h4>Offentlig vidensbase</h4>
            <p>Besøgende kan søge og læse publicerede artikler på <a href="{{ route('knowledgebase.index') }}" target="_blank">{{ route('knowledgebase.index') }}</a>.</p>
        </div>
    </div>
</div>
@endsection