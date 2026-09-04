@extends('layouts.admin')

@php
    $nodexaVersionData = [];
    $nodexaVersionFile = '/var/lib/nodexa/version.json';
    if (is_readable($nodexaVersionFile)) {
        $decoded = json_decode((string) file_get_contents($nodexaVersionFile), true);
        $nodexaVersionData = is_array($decoded) ? $decoded : [];
    }
    $nodexaVersion = (string) ($nodexaVersionData['version'] ?? 'unknown');
    $nodexaCommit = (string) ($nodexaVersionData['commit'] ?? '');
@endphp

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Nodexa Administration<small>Et hurtigt overblik over dit Nodexa-system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Overview</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-server"></i> Nodexa System Information</h3>
            </div>
            <div class="box-body">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--nodexa-muted);margin-bottom:4px;">Installeret system</div>
                        <div style="font-size:22px;font-weight:700;line-height:1.2;">Nodexa <code>v{{ $nodexaVersion }}</code></div>
                        <p class="text-muted" style="margin:8px 0 0;">Du kører Nodexa version <strong>v{{ $nodexaVersion }}</strong>.</p>
                    </div>
                    @if($nodexaCommit !== '')
                        <div style="text-align:right;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--nodexa-muted);margin-bottom:4px;">Commit</div>
                            <code>{{ substr($nodexaCommit, 0, 12) }}</code>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ route('admin.updates') }}"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-cloud-download"></i> Update Center</button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://github.com/yupthatpandadk/Nodexa" target="_blank" rel="noopener noreferrer"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-github"></i> Nodexa GitHub</button></a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ route('admin.nodes') }}"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-sitemap"></i> Nodes</button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ route('admin.servers') }}"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-server"></i> Servers</button></a>
    </div>
</div>
@endsection
