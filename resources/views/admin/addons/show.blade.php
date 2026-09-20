@extends('layouts.admin')

@section('title', $addon->name === 'minecraft-mod-manager' ? 'Minecraft Mod Manager' : 'Minecraft Plugin Manager')

@section('content-header')
    <h1>{{ $addon->name === 'minecraft-mod-manager' ? 'Minecraft Mod Manager' : 'Minecraft Plugin Manager' }} <small>Konfigurer addonet og vælg hvilke servertyper der har adgang.</small></h1>
@endsection

@section('content')
<style>
.ndx-toggle{position:relative;display:inline-block;width:46px;height:25px;margin:0;vertical-align:middle}
.ndx-toggle input{opacity:0;width:0;height:0}
.ndx-slider{position:absolute;cursor:pointer;inset:0;background:#334155;border:1px solid #475569;border-radius:999px;transition:.2s}
.ndx-slider:before{content:"";position:absolute;width:17px;height:17px;left:3px;top:3px;background:#cbd5e1;border-radius:50%;transition:.2s;box-shadow:0 1px 4px rgba(0,0,0,.35)}
.ndx-toggle input:checked + .ndx-slider{background:#7c3aed;border-color:#8b5cf6}
.ndx-toggle input:checked + .ndx-slider:before{transform:translateX(21px);background:#fff}
.ndx-toggle-row{display:flex!important;align-items:center;justify-content:space-between;gap:14px}
.ndx-toggle-copy{min-width:0;flex:1}
</style>
@php
    $selectedEggs = collect(explode(',', (string) $addon->egg_ids))->filter()->map(fn ($id) => (int) $id)->all();
@endphp
<div class="row">
    <div class="col-md-12">
        <div style="margin-bottom:18px">
            <a href="{{ route('admin.addons') }}" class="btn btn-default"><i class="fa fa-arrow-left"></i> Tilbage til Addons</a>
        </div>
        <div class="box" style="border:1px solid #26384d;border-radius:14px;overflow:hidden">
            <div class="box-header with-border" style="padding:18px 20px">
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <h3 class="box-title"><i class="fa fa-puzzle-piece" style="color:#9b7cff;margin-right:8px"></i> {{ $addon->name === 'minecraft-mod-manager' ? 'Minecraft Mod Manager' : 'Minecraft Plugin Manager' }}</h3>
                        <div class="text-muted" style="margin-top:6px">Modrinth integration · {{ $addon->name === 'minecraft-mod-manager' ? '/mods' : '/plugins' }}</div>
                    </div>
                    {!! $addon->enabled ? '<span class="label label-success">Aktiv</span>' : '<span class="label label-default">Deaktiveret</span>' !!}
                </div>
            </div>
            <form method="POST" action="{{ route('admin.addons.update', $addon->id) }}">
                @csrf
                @method('PATCH')
                <div class="box-body" style="padding:22px">
                    <div class="row">
                        <div class="col-md-4"><strong>Spil</strong><p class="text-muted">Minecraft</p></div>
                        <div class="col-md-4"><strong>Provider</strong><p class="text-muted">Modrinth</p></div>
                        <div class="col-md-4"><strong>Understøtter</strong><p class="text-muted">{{ $addon->name === 'minecraft-mod-manager' ? 'Forge, NeoForge, Fabric & Quilt' : 'Paper, Purpur, Spigot, Folia & Bukkit' }}</p></div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label>{{ $addon->name === 'minecraft-mod-manager' ? 'Mod Manager' : 'Plugin Manager' }}</label>
                        <div style="border:1px solid #26384d;border-radius:10px;padding:14px">
                            <div class="ndx-toggle-row">
                                <div class="ndx-toggle-copy">
                                    <strong>Aktivér {{ $addon->name === 'minecraft-mod-manager' ? 'Minecraft Mod Manager' : 'Minecraft Plugin Manager' }}</strong>
                                    <div class="text-muted" style="font-size:12px;margin-top:3px">Vis manageren på de valgte Minecraft-servere.</div>
                                </div>
                                <label class="ndx-toggle">
                                    <input type="checkbox" name="enabled" value="1" {{ $addon->enabled ? 'checked' : '' }}>
                                    <span class="ndx-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:22px">
                        <label>Tilladte Eggs</label>
                        <p class="text-muted">{{ $addon->name === 'minecraft-mod-manager' ? 'Mods-fanen' : 'Plugin-fanen' }} vises kun på servere, hvis Egg er aktiveret her.</p>
                        <div class="row">
                            @foreach($eggs as $egg)
                                <div class="col-lg-4 col-md-6" style="margin-bottom:10px">
                                    <div class="ndx-toggle-row" style="padding:13px;border:1px solid #26384d;border-radius:10px;min-height:62px">
                                        <div class="ndx-toggle-copy">
                                            <strong>{{ $egg->name }}</strong>
                                            <br><small class="text-muted">{{ optional($egg->nest)->name }} · Egg #{{ $egg->id }}</small>
                                        </div>
                                        <label class="ndx-toggle">
                                            <input type="checkbox" name="egg_ids[]" value="{{ $egg->id }}" {{ in_array($egg->id, $selectedEggs, true) ? 'checked' : '' }}>
                                            <span class="ndx-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="box-footer" style="padding:16px 22px">
                    <button class="btn btn-primary"><i class="fa fa-save"></i> Gem ændringer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
