@extends('layouts.admin')

@section('title', 'Minecraft Plugin Manager')

@section('content-header')
    <h1>Minecraft Plugin Manager <small>Konfigurer addonet og vælg hvilke servertyper der har adgang.</small></h1>
@endsection

@section('content')
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
                        <h3 class="box-title"><i class="fa fa-puzzle-piece" style="color:#9b7cff;margin-right:8px"></i> Minecraft Plugin Manager</h3>
                        <div class="text-muted" style="margin-top:6px">Modrinth integration · /plugins</div>
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
                        <div class="col-md-4"><strong>Understøtter</strong><p class="text-muted">Paper, Purpur, Spigot, Folia & Bukkit</p></div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label>Plugin Manager</label>
                        <div style="border:1px solid #26384d;border-radius:10px;padding:14px">
                            <label style="font-weight:normal;margin:0">
                                <input type="checkbox" name="enabled" value="1" {{ $addon->enabled ? 'checked' : '' }}>
                                Aktivér Minecraft Plugin Manager
                            </label>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:22px">
                        <label>Tilladte Eggs</label>
                        <p class="text-muted">Plugin-fanen vises kun på servere, hvis Egg er markeret her.</p>
                        <div class="row">
                            @foreach($eggs as $egg)
                                <div class="col-lg-4 col-md-6" style="margin-bottom:10px">
                                    <label style="font-weight:normal;display:block;padding:13px;border:1px solid #26384d;border-radius:10px;cursor:pointer">
                                        <input type="checkbox" name="egg_ids[]" value="{{ $egg->id }}" {{ in_array($egg->id, $selectedEggs, true) ? 'checked' : '' }}>
                                        <strong style="margin-left:5px">{{ $egg->name }}</strong>
                                        <br><small class="text-muted" style="margin-left:20px">{{ optional($egg->nest)->name }} · Egg #{{ $egg->id }}</small>
                                    </label>
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
