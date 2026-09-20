@extends('layouts.admin')

@section('title', 'Addon Manager')

@section('content-header')
    <h1>Addon Manager <small>Administrer Nodexa-funktioner som kan aktiveres på kundernes servere.</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-4 col-md-5 col-sm-6 col-xs-12">
        <a href="{{ route('admin.addons.show', $addon->id) }}" style="display:block;text-decoration:none;color:inherit">
            <div class="box" style="border:1px solid #26384d;border-radius:14px;overflow:hidden;min-height:230px;transition:.2s ease">
                <div class="box-body" style="padding:24px">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:15px">
                        <div style="width:52px;height:52px;border-radius:12px;background:rgba(124,58,237,.16);display:flex;align-items:center;justify-content:center;font-size:24px;color:#9b7cff">
                            <i class="fa fa-puzzle-piece"></i>
                        </div>
                        {!! $addon->enabled ? '<span class="label label-success">Aktiv</span>' : '<span class="label label-default">Deaktiveret</span>' !!}
                    </div>
                    <h3 style="margin:22px 0 7px;font-size:18px;color:#fff">Minecraft Plugin Manager</h3>
                    <p class="text-muted" style="line-height:1.6;margin-bottom:18px">Installer og administrer Minecraft plugins direkte fra Modrinth.</p>
                    <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #26384d;padding-top:15px">
                        <span class="text-muted"><i class="fa fa-cube"></i> Minecraft</span>
                        <span style="color:#9b7cff;font-weight:600">Administrer <i class="fa fa-chevron-right" style="font-size:10px;margin-left:5px"></i></span>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-lg-4 col-md-5 col-sm-6 col-xs-12">
        <a href="{{ route('admin.addons.show', $modAddon->id) }}" style="display:block;text-decoration:none;color:inherit">
            <div class="box" style="border:1px solid #26384d;border-radius:14px;overflow:hidden;min-height:230px">
                <div class="box-body" style="padding:24px">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start">
                        <div style="width:52px;height:52px;border-radius:12px;background:rgba(6,182,212,.14);display:flex;align-items:center;justify-content:center;font-size:24px;color:#22d3ee"><i class="fa fa-cubes"></i></div>
                        {!! $modAddon->enabled ? '<span class="label label-success">Aktiv</span>' : '<span class="label label-default">Deaktiveret</span>' !!}
                    </div>
                    <h3 style="margin:22px 0 7px;font-size:18px;color:#fff">Minecraft Mod Manager</h3>
                    <p class="text-muted" style="line-height:1.6;margin-bottom:18px">Mods fra Modrinth med automatisk Forge, NeoForge, Fabric og Quilt filtrering.</p>
                    <div style="display:flex;justify-content:space-between;border-top:1px solid #26384d;padding-top:15px"><span class="text-muted"><i class="fa fa-cube"></i> Minecraft</span><span style="color:#22d3ee;font-weight:600">Administrer <i class="fa fa-chevron-right"></i></span></div>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
