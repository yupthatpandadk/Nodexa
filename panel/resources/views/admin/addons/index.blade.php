@extends('layouts.admin')

@section('title')
    Addons
@endsection

@section('content-header')
    <h1>Nodexa Addons<small>Installér valgfrie funktioner uden at ændre grundsystemet.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Addons</li>
    </ol>
@endsection

@section('content')
@if(session('addon_status'))
    <div class="alert alert-{{ session('addon_status.type', 'info') }}">
        {{ session('addon_status.message') }}
    </div>
@endif

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-puzzle-piece"></i> Addon Manager</h3>
            </div>
            <div class="box-body">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <div>
                        <strong>Nodexa v{{ $nodexaVersion }}</strong>
                        <p class="text-muted" style="margin:5px 0 0;">Kun addons der allerede ligger i Nodexas betroede addon-katalog kan installeres fra denne side.</p>
                    </div>
                    <span class="label label-primary" style="padding:8px 12px;">{{ count($addons) }} tilgængelig{{ count($addons) === 1 ? '' : 'e' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@if(count($addons) === 0)
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-default">
                <div class="box-body text-center" style="padding:42px 20px;">
                    <i class="fa fa-puzzle-piece" style="font-size:42px;color:var(--nodexa-accent);margin-bottom:14px;"></i>
                    <h3 style="margin-top:0;">Ingen addons fundet</h3>
                    <p class="text-muted">Når Nodexa-addons bliver tilføjet til kataloget, dukker de automatisk op her.</p>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="row equal-height">
@foreach($addons as $addon)
    <div class="col-xs-12 col-sm-6 col-lg-4">
        <div class="box {{ $addon['enabled'] ? 'box-success' : 'box-default' }}" style="width:100%;">
            <div class="box-body" style="display:flex;flex-direction:column;height:100%;padding:20px;">
                <div style="display:flex;align-items:flex-start;gap:14px;">
                    <div style="width:44px;height:44px;min-width:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(var(--nodexa-accent-rgb),.12);border:1px solid var(--nodexa-border);color:var(--nodexa-accent);font-size:19px;">
                        <i class="fa {{ $addon['icon'] }}"></i>
                    </div>
                    <div style="min-width:0;flex:1;">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                            <h3 style="font-size:17px;margin:0;">{{ $addon['name'] }}</h3>
                            <span class="label {{ $addon['installed'] ? ($addon['enabled'] ? 'label-success' : 'label-default') : 'label-primary' }}">
                                {{ $addon['installed'] ? ($addon['enabled'] ? 'Aktiv' : 'Deaktiveret') : 'Ikke installeret' }}
                            </span>
                        </div>
                        <div class="text-muted" style="font-size:12px;margin-top:4px;">v{{ $addon['version'] }} · {{ $addon['author'] }} · {{ $addon['category'] }}</div>
                    </div>
                </div>

                <p style="margin:18px 0;flex:1;">{{ $addon['description'] ?: 'Ingen beskrivelse.' }}</p>

                <div style="font-size:12px;margin-bottom:14px;">
                    <span class="text-muted">Kræver Nodexa v{{ $addon['min_nodexa'] }}+</span>
                    @if(!$addon['compatible'])
                        <span class="label label-danger" style="margin-left:6px;">Ikke kompatibel</span>
                    @endif
                    @if($addon['update_available'])
                        <span class="label label-warning" style="margin-left:6px;">Opdatering tilgængelig</span>
                    @endif
                </div>

                @if(!$addon['installed'])
                    <form method="POST" action="{{ route('admin.addons.install', $addon['id']) }}">
                        @csrf
                        <button class="btn btn-primary btn-block" type="submit" {{ !$addon['compatible'] ? 'disabled' : '' }}>
                            <i class="fa fa-download"></i> Installér addon
                        </button>
                    </form>
                @else
                    <div style="display:flex;gap:8px;">
                        <form method="POST" action="{{ route('admin.addons.toggle', $addon['id']) }}" style="flex:1;">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ $addon['enabled'] ? '0' : '1' }}">
                            <button class="btn {{ $addon['enabled'] ? 'btn-default' : 'btn-primary' }} btn-block" type="submit">
                                <i class="fa {{ $addon['enabled'] ? 'fa-pause' : 'fa-play' }}"></i> {{ $addon['enabled'] ? 'Deaktivér' : 'Aktivér' }}
                            </button>
                        </form>
                        @if($addon['update_available'])
                            <form method="POST" action="{{ route('admin.addons.install', $addon['id']) }}" style="flex:1;">
                                @csrf
                                <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-refresh"></i> Opdatér</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.addons.uninstall', $addon['id']) }}" onsubmit="return confirm('Afillstallér {{ addslashes($addon['name']) }}?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger" type="submit" title="Afinstallér"><i class="fa fa-trash"></i></button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endforeach
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-shield"></i> Sikker addon-model</h3></div>
            <div class="box-body">
                <p style="margin-bottom:6px;">Addon Manager installerer ikke vilkårlige ZIP/PHP-filer direkte fra browseren. Et addon skal først være lagt i Nodexas lokale, betroede addon-katalog med et gyldigt <code>addon.json</code>.</p>
                <p class="text-muted" style="margin:0;">Det gør det muligt senere at tilføje et officielt Nodexa Marketplace med signaturkontrol uden at gøre Admin til en fjern-root-installationskanal.</p>
            </div>
        </div>
    </div>
</div>
@endsection
