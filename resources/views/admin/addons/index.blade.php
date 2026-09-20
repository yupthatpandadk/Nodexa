@extends('layouts.admin')

@section('title', 'Addon Manager')

@section('content-header')
    <h1>Addon Manager <small>Administrer addons opdelt efter spil og kategori.</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plus"></i> Opret addon</h3></div>
            <form method="POST" action="{{ route('admin.addons.store') }}">
                @csrf
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label>Spil</label><input name="game" class="form-control" placeholder="Minecraft" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Kategori</label><input name="category" class="form-control" placeholder="Plugins" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Navn</label><input name="name" class="form-control" placeholder="EssentialsX" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Version</label><input name="version" class="form-control" placeholder="2.21.0"></div></div>
                    </div>
                    <div class="form-group"><label>Beskrivelse</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label>Kompatible versioner</label><input name="compatible_versions" class="form-control" placeholder="1.20.x - 1.21.x"></div></div>
                        <div class="col-md-4"><div class="form-group"><label>Installationssti</label><input name="install_path" class="form-control" placeholder="/plugins/" required></div></div>
                        <div class="col-md-4"><div class="form-group"><label>Egg IDs</label><input name="egg_ids" class="form-control" placeholder="1,5,12"></div></div>
                    </div>
                    <div class="form-group"><label>Download URL</label><input type="url" name="download_url" class="form-control" placeholder="https://..." required></div>
                    <label><input type="checkbox" name="enabled" value="1" checked> Aktiv</label>
                </div>
                <div class="box-footer"><button class="btn btn-primary"><i class="fa fa-plus"></i> Opret addon</button></div>
            </form>
        </div>
    </div>

    <div class="col-md-12">
        @forelse($addons as $game => $gameAddons)
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-gamepad"></i> {{ $game }}</h3>
                    <span class="pull-right text-muted">{{ $gameAddons->count() }} addons</span>
                </div>
                <div class="box-body">
                    @foreach($gameAddons->groupBy('category') as $category => $items)
                        <h4 style="margin-top:18px">{{ $category }}</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead><tr><th>Addon</th><th>Version</th><th>Kompatibilitet</th><th>Eggs</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                @foreach($items as $addon)
                                    <tr>
                                        <td><strong>{{ $addon->name }}</strong><br><small class="text-muted">{{ $addon->description }}</small></td>
                                        <td>{{ $addon->version ?: '—' }}</td>
                                        <td>{{ $addon->compatible_versions ?: 'Alle' }}</td>
                                        <td>{{ $addon->egg_ids ?: 'Alle' }}</td>
                                        <td>{!! $addon->enabled ? '<span class="label label-success">Aktiv</span>' : '<span class="label label-default">Deaktiveret</span>' !!}</td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-xs btn-primary" data-toggle="collapse" data-target="#addon-{{ $addon->id }}">Rediger</button>
                                            <form method="POST" action="{{ route('admin.addons.delete', $addon->id) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-xs btn-danger" onclick="return confirm('Slet dette addon?')">Slet</button></form>
                                        </td>
                                    </tr>
                                    <tr id="addon-{{ $addon->id }}" class="collapse"><td colspan="6">
                                        <form method="POST" action="{{ route('admin.addons.update', $addon->id) }}">@csrf @method('PATCH')
                                            <div class="row">
                                                <div class="col-md-3"><input name="game" class="form-control" value="{{ $addon->game }}" required></div>
                                                <div class="col-md-3"><input name="category" class="form-control" value="{{ $addon->category }}" required></div>
                                                <div class="col-md-3"><input name="name" class="form-control" value="{{ $addon->name }}" required></div>
                                                <div class="col-md-3"><input name="version" class="form-control" value="{{ $addon->version }}"></div>
                                            </div><br>
                                            <textarea name="description" class="form-control" rows="2">{{ $addon->description }}</textarea><br>
                                            <div class="row">
                                                <div class="col-md-4"><input name="compatible_versions" class="form-control" value="{{ $addon->compatible_versions }}"></div>
                                                <div class="col-md-4"><input name="install_path" class="form-control" value="{{ $addon->install_path }}" required></div>
                                                <div class="col-md-4"><input name="egg_ids" class="form-control" value="{{ $addon->egg_ids }}"></div>
                                            </div><br>
                                            <input type="url" name="download_url" class="form-control" value="{{ $addon->download_url }}" required><br>
                                            <label><input type="checkbox" name="enabled" value="1" {{ $addon->enabled ? 'checked' : '' }}> Aktiv</label>
                                            <button class="btn btn-primary btn-sm pull-right">Gem ændringer</button>
                                        </form>
                                    </td></tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="box"><div class="box-body text-center text-muted" style="padding:45px"><i class="fa fa-puzzle-piece fa-3x"></i><h4>Ingen addons endnu</h4><p>Opret det første addon ovenfor.</p></div></div>
        @endforelse
    </div>
</div>
@endsection
