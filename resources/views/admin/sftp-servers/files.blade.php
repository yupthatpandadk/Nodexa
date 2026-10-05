@extends('layouts.admin')

@section('title', $server->name . ' Files')

@section('content-header')
    <h1>{{ $server->name }}<small>SFTP File Manager · {{ $server->host }}:{{ $server->port }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.sftp-servers') }}">SFTP File Manager</a></li>
        <li class="active">{{ $server->name }}</li>
    </ol>
@endsection

@section('content')
@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

<style>
.sftp-toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.sftp-path{word-break:break-all}.sftp-row-actions{white-space:nowrap}.sftp-name{max-width:520px;word-break:break-word}.sftp-editor{font-family:Menlo,Monaco,Consolas,monospace;min-height:55vh;resize:vertical}.sftp-mobile-card{display:none}
@media(max-width:767px){.sftp-desktop{display:none}.sftp-mobile-card{display:block;border-bottom:1px solid #eee;padding:12px}.sftp-mobile-card .name{font-weight:600;word-break:break-all;margin-bottom:8px}.sftp-mobile-card .actions{display:flex;gap:6px;flex-wrap:wrap}.sftp-toolbar .btn{flex:1 1 auto}.sftp-editor{min-height:60vh;font-size:13px}.box-body{padding:10px}}
</style>

@php
    $parent = '';
    if ($path !== '') {
        $bits = explode('/', $path);
        array_pop($bits);
        $parent = implode('/', $bits);
    }
@endphp

<div class="box box-primary">
    <div class="box-header with-border">
        <div class="sftp-toolbar">
            <a class="btn btn-default btn-sm" href="{{ route('admin.sftp-servers') }}"><i class="fa fa-arrow-left"></i> Servere</a>
            @if($path !== '')<a class="btn btn-default btn-sm" href="{{ route('admin.sftp-servers.files', ['sftpServer' => $server->id, 'path' => $parent]) }}"><i class="fa fa-level-up"></i> Op</a>@endif
            <form method="POST" action="{{ route('admin.sftp-servers.test', $server) }}" style="display:inline">@csrf<button class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Test</button></form>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#uploadModal"><i class="fa fa-upload"></i> Upload</button>
            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#newFileModal"><i class="fa fa-file-o"></i> Ny fil</button>
            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#newFolderModal"><i class="fa fa-folder-o"></i> Ny mappe</button>
        </div>
    </div>
    <div class="box-body"><strong>Mappe:</strong> <code class="sftp-path">/{{ $path }}</code></div>

    <div class="table-responsive sftp-desktop">
        <table class="table table-hover" style="margin-bottom:0">
            <thead><tr><th>Navn</th><th>Størrelse</th><th>Ændret</th><th></th></tr></thead>
            <tbody>
            @foreach($items as $item)
                @php $itemPath = ltrim(($path ? $path.'/' : '').$item['name'], '/'); @endphp
                <tr>
                    <td class="sftp-name">
                        @if($item['type'] === 'directory')
                            <i class="fa fa-folder text-yellow"></i> <a href="{{ route('admin.sftp-servers.files', ['sftpServer' => $server->id, 'path' => $itemPath]) }}">{{ $item['name'] }}</a>
                        @else
                            <i class="fa fa-file-text-o"></i> <a href="{{ route('admin.sftp-servers.files', ['sftpServer' => $server->id, 'path' => $path, 'edit' => $itemPath]) }}">{{ $item['name'] }}</a>
                        @endif
                    </td>
                    <td>{{ $item['type'] === 'directory' ? '—' : number_format($item['size'] / 1024, 1, ',', '.') . ' KB' }}</td>
                    <td>{{ $item['mtime'] ? date('d/m/Y H:i', $item['mtime']) : '—' }}</td>
                    <td class="text-right sftp-row-actions">
                        @if($item['type'] === 'file')<a class="btn btn-xs btn-default" href="{{ route('admin.sftp-servers.files.download', ['sftpServer' => $server->id, 'path' => $itemPath]) }}"><i class="fa fa-download"></i></a>@endif
                        <button class="btn btn-xs btn-default" onclick="renameItem(@js($itemPath))"><i class="fa fa-pencil"></i></button>
                        <form method="POST" action="{{ route('admin.sftp-servers.files.delete', $server) }}" style="display:inline" onsubmit="return confirm('Slet {{ addslashes($item['name']) }}?')">@csrf<input type="hidden" name="path" value="{{ $itemPath }}"><button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="sftp-mobile-card-wrap">
    @foreach($items as $item)
        @php $itemPath = ltrim(($path ? $path.'/' : '').$item['name'], '/'); @endphp
        <div class="sftp-mobile-card">
            <div class="name"><i class="fa {{ $item['type'] === 'directory' ? 'fa-folder text-yellow' : 'fa-file-text-o' }}"></i> {{ $item['name'] }}</div>
            <div class="text-muted small" style="margin-bottom:8px">{{ $item['type'] === 'directory' ? 'Mappe' : number_format($item['size'] / 1024, 1, ',', '.') . ' KB' }}</div>
            <div class="actions">
                @if($item['type'] === 'directory')
                    <a class="btn btn-primary btn-sm" href="{{ route('admin.sftp-servers.files', ['sftpServer' => $server->id, 'path' => $itemPath]) }}"><i class="fa fa-folder-open"></i> Åbn</a>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('admin.sftp-servers.files', ['sftpServer' => $server->id, 'path' => $path, 'edit' => $itemPath]) }}"><i class="fa fa-pencil"></i> Redigér</a>
                    <a class="btn btn-default btn-sm" href="{{ route('admin.sftp-servers.files.download', ['sftpServer' => $server->id, 'path' => $itemPath]) }}"><i class="fa fa-download"></i></a>
                @endif
                <button class="btn btn-default btn-sm" onclick="renameItem(@js($itemPath))"><i class="fa fa-i-cursor"></i></button>
                <form method="POST" action="{{ route('admin.sftp-servers.files.delete', $server) }}" style="display:inline" onsubmit="return confirm('Slet dette element?')">@csrf<input type="hidden" name="path" value="{{ $itemPath }}"><button class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button></form>
            </div>
        </div>
    @endforeach
    </div>
</div>

@if($edit)
<div class="box box-success">
    <form method="POST" action="{{ route('admin.sftp-servers.files.save', $server) }}">
        @csrf
        <input type="hidden" name="path" value="{{ $edit }}">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-code"></i> {{ $edit }}</h3><div class="box-tools"><button class="btn btn-success btn-sm"><i class="fa fa-save"></i> Gem fil</button></div></div>
        <div class="box-body"><textarea class="form-control sftp-editor" name="contents" spellcheck="false">{{ $contents }}</textarea></div>
        <div class="box-footer text-right"><button class="btn btn-success"><i class="fa fa-save"></i> Gem ændringer</button></div>
    </form>
</div>
@endif

<div class="modal fade" id="uploadModal"><div class="modal-dialog"><div class="modal-content"><form method="POST" enctype="multipart/form-data" action="{{ route('admin.sftp-servers.files.upload', $server) }}">@csrf<div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4>Upload fil</h4></div><div class="modal-body"><input type="hidden" name="directory" value="{{ $path }}"><input type="file" class="form-control" name="file" required><p class="help-block">Maks. 100 MB pr. upload.</p></div><div class="modal-footer"><button class="btn btn-primary"><i class="fa fa-upload"></i> Upload</button></div></form></div></div></div>
<div class="modal fade" id="newFileModal"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.sftp-servers.files.create-file', $server) }}">@csrf<div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4>Ny fil</h4></div><div class="modal-body"><label>Filnavn</label><input class="form-control" id="newFileName" required><input type="hidden" name="path" id="newFilePath"></div><div class="modal-footer"><button class="btn btn-success" onclick="document.getElementById('newFilePath').value=@js($path ? $path.'/':'')+document.getElementById('newFileName').value">Opret</button></div></form></div></div></div>
<div class="modal fade" id="newFolderModal"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.sftp-servers.files.create-folder', $server) }}">@csrf<div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4>Ny mappe</h4></div><div class="modal-body"><label>Mappenavn</label><input class="form-control" id="newFolderName" required><input type="hidden" name="path" id="newFolderPath"></div><div class="modal-footer"><button class="btn btn-success" onclick="document.getElementById('newFolderPath').value=@js($path ? $path.'/':'')+document.getElementById('newFolderName').value">Opret</button></div></form></div></div></div>

<form id="renameForm" method="POST" action="{{ route('admin.sftp-servers.files.rename', $server) }}" style="display:none">@csrf<input name="from" id="renameFrom"><input name="to" id="renameTo"></form>
@endsection

@section('footer-scripts')
@parent
<script>
function renameItem(from) {
    var name = prompt('Nyt navn eller sti:', from);
    if (!name || name === from) return;
    document.getElementById('renameFrom').value = from;
    document.getElementById('renameTo').value = name;
    document.getElementById('renameForm').submit();
}
</script>
@endsection
