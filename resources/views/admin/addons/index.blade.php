@extends('layouts.admin')

@section('title', 'Addon Manager')

@section('content-header')
    <h1>Addon Manager <small>Administrer Nodexa-funktioner som kan aktiveres på kundernes servere.</small></h1>
@endsection

@section('content')
@php
    $selectedEggs = collect(explode(',', (string) $addon->egg_ids))->filter()->map(fn ($id) => (int) $id)->all();
@endphp
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-puzzle-piece"></i> Minecraft Plugin Manager</h3>
                <span class="pull-right">{!! $addon->enabled ? '<span class="label label-success">Aktiv</span>' : '<span class="label label-default">Deaktiveret</span>' !!}</span>
            </div>
            <form method="POST" action="{{ route('admin.addons.update', $addon->id) }}">
                @csrf
                @method('PATCH')
                <div class="box-body">
                    <p class="text-muted">
                        Giver Minecraft-serverejere en Plugin Manager i serverpanelet. Plugins hentes fra Modrinth og installeres direkte i <code>/plugins</code>.
                    </p>

                    <div class="row" style="margin-top:20px">
                        <div class="col-md-4">
                            <strong>Spil</strong>
                            <p class="text-muted">Minecraft</p>
                        </div>
                        <div class="col-md-4">
                            <strong>Provider</strong>
                            <p class="text-muted">Modrinth</p>
                        </div>
                        <div class="col-md-4">
                            <strong>Understøtter</strong>
                            <p class="text-muted">Paper, Purpur, Spigot, Folia & Bukkit</p>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Aktivér Plugin Manager</label>
                        <div>
                            <label style="font-weight:normal">
                                <input type="checkbox" name="enabled" value="1" {{ $addon->enabled ? 'checked' : '' }}>
                                Vis Plugin Manager på tilladte Minecraft-servere
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tilladte Eggs</label>
                        <p class="text-muted">Vælg hvilke Minecraft Eggs der skal have Plugin Manager. Hvis ingen vælges, vises den ikke på nogen servere.</p>
                        <div class="row">
                            @foreach($eggs as $egg)
                                <div class="col-md-4" style="margin-bottom:8px">
                                    <label style="font-weight:normal;display:block;padding:10px;border:1px solid #26384d;border-radius:6px">
                                        <input type="checkbox" name="egg_ids[]" value="{{ $egg->id }}" {{ in_array($egg->id, $selectedEggs, true) ? 'checked' : '' }}>
                                        <strong>{{ $egg->name }}</strong>
                                        <br><small class="text-muted">{{ optional($egg->nest)->name }} · Egg #{{ $egg->id }}</small>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button class="btn btn-primary"><i class="fa fa-save"></i> Gem Plugin Manager</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
