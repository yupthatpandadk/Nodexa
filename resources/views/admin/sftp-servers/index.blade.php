@extends('layouts.admin')

@section('title', 'SFTP File Manager')

@section('content-header')
    <h1>SFTP File Manager<small>Tilføj eksterne Windows- og Linux-servere og redigér deres filer fra Nodexa.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">SFTP File Manager</li>
    </ol>
@endsection

@section('content')
@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
@if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

<div class="row">
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-folder-open"></i> Tilknyttede SFTP-servere</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead><tr><th>Navn</th><th>Forbindelse</th><th>Rodmappe</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($servers as $server)
                        <tr>
                            <td><strong>{{ $server->name }}</strong><br><small class="text-muted">{{ $server->username }}</small></td>
                            <td><code>{{ $server->host }}:{{ $server->port }}</code></td>
                            <td><code>{{ $server->root_path }}</code></td>
                            <td><span class="label label-{{ $server->enabled ? 'success' : 'default' }}">{{ $server->enabled ? 'Aktiv' : 'Deaktiveret' }}</span></td>
                            <td class="text-right" style="white-space:nowrap">
                                <a class="btn btn-xs btn-primary" href="{{ route('admin.sftp-servers.files', $server) }}"><i class="fa fa-folder-open"></i> File Manager</a>
                                <form method="POST" action="{{ route('admin.sftp-servers.test', $server) }}" style="display:inline">@csrf<button class="btn btn-xs btn-default"><i class="fa fa-plug"></i></button></form>
                                <form method="POST" action="{{ route('admin.sftp-servers.delete', $server) }}" style="display:inline" onsubmit="return confirm('Fjern serveren fra Nodexa? Filerne på serveren slettes ikke.')">@csrf @method('DELETE')<button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted" style="padding:30px">Ingen SFTP-servere er oprettet endnu.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="box box-success">
            <form method="POST" action="{{ route('admin.sftp-servers.store') }}">
                @csrf
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plus"></i> Tilføj SFTP-server</h3></div>
                <div class="box-body">
                    <div class="form-group"><label>Navn</label><input class="form-control" name="name" value="{{ old('name') }}" placeholder="FiveM Windows VM" required></div>
                    <div class="row">
                        <div class="col-xs-8"><div class="form-group"><label>Host / IP</label><input class="form-control" name="host" value="{{ old('host') }}" placeholder="10.10.10.25" required></div></div>
                        <div class="col-xs-4"><div class="form-group"><label>Port</label><input class="form-control" type="number" name="port" value="{{ old('port', 22) }}" min="1" max="65535" required></div></div>
                    </div>
                    <div class="form-group"><label>Brugernavn</label><input class="form-control" name="username" value="{{ old('username') }}" autocomplete="off" required></div>
                    <div class="form-group"><label>Adgangskode</label><input class="form-control" type="password" name="password" autocomplete="new-password" required><p class="help-block">Gemmes krypteret i Nodexa-databasen.</p></div>
                    <div class="form-group"><label>SFTP rodmappe</label><input class="form-control" name="root_path" value="{{ old('root_path', '/') }}" placeholder="/C:/FiveM/server-data" required><p class="help-block">File Manager kan kun arbejde inden for denne mappe. På Windows OpenSSH kan den fx være <code>/C:/FiveM/server-data</code>.</p></div>
                </div>
                <div class="box-footer"><button class="btn btn-success pull-right"><i class="fa fa-plus"></i> Opret og test forbindelse</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
