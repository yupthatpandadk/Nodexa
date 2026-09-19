@extends('layouts.admin')

@section('title')
    Dashboard
@endsection

@section('content-header')
    <h1>Nodexa Control Center<small>Overblik over din infrastruktur.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Nodexa</a></li>
        <li class="active">Dashboard</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-th-large"></i>&nbsp; Administration</h3>
            </div>
            <div class="box-body">
                <h3 style="margin-top:0;color:#f4f7fb;">Velkommen til Nodexa</h3>
                <p>Administrér servere, nodes, brugere og platformens konfiguration fra ét samlet control center.</p>
                <div style="margin-top:20px;">
                    <a href="{{ route('admin.servers') }}" class="btn btn-primary"><i class="fa fa-server"></i> Servere</a>
                    <a href="{{ route('admin.nodes') }}" class="btn btn-default"><i class="fa fa-sitemap"></i> Nodes</a>
                    <a href="{{ route('admin.users') }}" class="btn btn-default"><i class="fa fa-users"></i> Brugere</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-heartbeat"></i>&nbsp; Platform</h3></div>
            <div class="box-body">
                <p><strong>Nodexa Panel</strong></p>
                <p class="text-muted">Game infrastructure management</p>
                <a href="{{ route('admin.updates') }}" class="btn btn-default btn-block"><i class="fa fa-cloud-download"></i> Åbn Update Center</a>
            </div>
        </div>
    </div>
</div>
@endsection
